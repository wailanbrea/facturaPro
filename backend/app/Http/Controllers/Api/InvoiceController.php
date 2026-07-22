<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\MarkInvoicePaidRequest;
use App\Http\Requests\Api\StoreInvoiceRequest;
use App\Http\Requests\Api\UpdateInvoiceRequest;
use App\Http\Resources\Api\InvoiceResource;
use App\Models\BankAccount;
use App\Models\Client;
use App\Models\Currency;
use App\Models\FiscalProfile;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\LegalText;
use App\Models\PaymentTerm;
use App\Models\Setting;
use App\Models\Tax;
use App\Models\Warranty;
use App\Services\ActivityLogService;
use App\Services\InvoiceCalculationService;
use App\Services\InvoiceClientResolver;
use App\Services\InvoiceNumberService;
use App\Services\InvoicePdfService;
use App\Services\InvoiceSignatureService;
use App\Services\InvoiceStatusService;
use App\Services\TechnicalReportSignatureService;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class InvoiceController extends Controller
{
    /**
     * Memoized active default legal text. `false` means "not yet loaded".
     */
    private LegalText|null|false $defaultLegalText = false;

    public function __construct(
        private readonly InvoiceCalculationService $calculator,
        private readonly InvoiceClientResolver $clientResolver,
        private readonly InvoiceStatusService $statusService,
        private readonly InvoiceNumberService $numberService,
        private readonly InvoicePdfService $pdfService,
        private readonly InvoiceSignatureService $signatureService,
        private readonly TechnicalReportSignatureService $reportSignatureService,
        private readonly ActivityLogService $activityLog,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $invoices = Invoice::query()
            ->with('items')
            ->when(request('status'), fn ($query, string $status) => $query->where('status', $status))
            ->when(
                in_array(request('document_type'), [Invoice::DOCUMENT_TYPE_INVOICE, Invoice::DOCUMENT_TYPE_QUOTATION], true),
                fn ($query) => $query->where('document_type', request('document_type')),
            )
            ->when(request('client_id'), fn ($query, string $clientId) => $query->where('client_id', $clientId))
            ->when(request('fiscal_profile_id'), fn ($query, string $profileId) => $query->where('fiscal_profile_id', $profileId))
            ->when(request('search'), function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('invoice_number', 'like', "%{$search}%")
                        ->orWhere('client_name', 'like', "%{$search}%")
                        ->orWhere('client_tax_id', 'like', "%{$search}%")
                        ->orWhere('seller_name', 'like', "%{$search}%");
                });
            })
            ->latest('invoice_date')
            ->paginate($this->perPage());

        return InvoiceResource::collection($invoices);
    }

    public function store(StoreInvoiceRequest $request): InvoiceResource
    {
        $invoice = DB::transaction(function () use ($request): Invoice {
            $data = $request->validated();
            $this->ensureManualNumberIsAllowed($data['invoice_number'] ?? null);

            $invoice = Invoice::query()->create($this->invoicePayload($data, InvoiceStatusService::DRAFT));
            $this->syncItemsAndTotals(
                $invoice,
                $data['items'],
                $this->amountReceivedForDocument($invoice->document_type, $data['amount_received'] ?? '0'),
            );
            $invoice->refresh();

            $this->activityLog->record('invoice.created', $invoice, ['invoice_id' => $invoice->id], $request->user(), $request);

            return $invoice;
        });

        return new InvoiceResource($invoice->load('items'));
    }

    public function show(Invoice $invoice): InvoiceResource
    {
        return new InvoiceResource($invoice->load(['items', 'payments']));
    }

    public function preview(Invoice $invoice): Response
    {
        return $this->previewResponse($invoice->load(['items', 'paymentTerm', 'bankAccount.currency', 'fiscalProfile', 'warranty']));
    }

    public function previewIssue(Invoice $invoice): Response|JsonResponse
    {
        if ($invoice->status === InvoiceStatusService::CANCELLED) {
            return response()->json(['message' => 'Cancelled invoices cannot be issued.'], 409);
        }

        $invoice->load(['items', 'paymentTerm', 'bankAccount.currency', 'fiscalProfile', 'warranty']);
        $preview = clone $invoice;
        $preview->status = InvoiceStatusService::ISSUED;
        $preview->invoice_number = $invoice->invoice_number ?? 'PROVISIONAL';

        return $this->previewResponse($preview);
    }

    public function previewDraft(StoreInvoiceRequest $request): Response
    {
        $data = $request->validated();
        $this->ensureManualNumberIsAllowed($data['invoice_number'] ?? null);

        return $this->previewResponse($this->draftPreviewInvoice($data));
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice): InvoiceResource|JsonResponse
    {
        if ($invoice->status === InvoiceStatusService::CANCELLED) {
            return response()->json(['message' => 'Cancelled invoices cannot be modified.'], 409);
        }

        $data = $request->validated();

        if ($this->changesDocumentTypeAfterDraft($invoice, $data)) {
            return response()->json(['message' => 'Issued documents cannot change document type.'], 409);
        }

        if ($invoice->status === InvoiceStatusService::CONVERTED) {
            return response()->json(['message' => 'Converted quotations cannot be modified.'], 409);
        }

        if ($invoice->status === InvoiceStatusService::PAID && $this->requestChangesAmounts($data)) {
            return response()->json(['message' => 'Paid invoices cannot modify monetary fields.'], 409);
        }

        if ($invoice->signed_at !== null && $this->requestChangesAmounts($data)) {
            return response()->json(['message' => 'Signed invoices cannot modify their authenticated fields.'], 409);
        }

        $invoice = DB::transaction(function () use ($request, $invoice, $data): Invoice {
            $this->ensureManualNumberIsAllowed($data['invoice_number'] ?? null, $invoice);

            $merged = $this->mergeInvoiceData($invoice, $data);
            $invoice->update($this->invoicePayload($merged, $invoice->status, $invoice));

            $items = $data['items'] ?? $invoice->items->map(fn ($item): array => [
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_cost' => $item->unit_cost,
                'tax_id' => $item->tax_id,
            ])->all();

            $this->syncItemsAndTotals(
                $invoice,
                $items,
                $this->amountReceivedForDocument($merged['document_type'] ?? $invoice->document_type, $merged['amount_received'] ?? $invoice->amount_received),
            );

            if ($invoice->status !== InvoiceStatusService::DRAFT) {
                $invoice->update([
                    'status' => $this->statusService->statusAfterAmountsChanged($invoice),
                ]);
            }

            $this->activityLog->record('invoice.updated', $invoice, ['invoice_id' => $invoice->id], $request->user(), $request);

            return $invoice->fresh('items');
        });

        return new InvoiceResource($invoice);
    }

    public function destroy(Invoice $invoice): Response|JsonResponse
    {
        if ($invoice->status !== InvoiceStatusService::DRAFT) {
            return response()->json(['message' => 'Only draft invoices can be deleted.'], 409);
        }

        $invoice->delete();

        return response()->noContent();
    }

    public function issue(Request $request, Invoice $invoice): InvoiceResource|JsonResponse
    {
        if ($invoice->status === InvoiceStatusService::CANCELLED) {
            return response()->json(['message' => 'Cancelled invoices cannot be issued.'], 409);
        }

        try {
            $invoice = DB::transaction(function () use ($request, $invoice): Invoice {
                if ($invoice->invoice_number === null) {
                    $invoice->invoice_number = $this->numberService->generateForInvoice($invoice);
                }

                if ($invoice->isQuotation()) {
                    $invoice->amount_received = 0;
                    $invoice->balance_due = $invoice->total;
                }

                $invoice->status = $this->statusService->statusWhenIssued($invoice);
                $invoice->save();

                $this->signatureService->signOnIssue($invoice->load('items'));

                $this->activityLog->record('invoice.issued', $invoice, ['invoice_number' => $invoice->invoice_number], $request->user(), $request);

                return $invoice->fresh('items');
            });
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return new InvoiceResource($invoice);
    }

    public function cancel(Request $request, Invoice $invoice): InvoiceResource
    {
        $invoice->update(['status' => InvoiceStatusService::CANCELLED]);
        $this->activityLog->record('invoice.cancelled', $invoice, ['invoice_number' => $invoice->invoice_number], $request->user(), $request);

        return new InvoiceResource($invoice->fresh('items'));
    }

    public function convert(Request $request, Invoice $invoice): JsonResponse
    {
        if ($invoice->document_type !== 'quotation') {
            return response()->json(['message' => 'Solo se pueden convertir presupuestos.'], 422);
        }

        if ($invoice->converted_to_invoice_id !== null) {
            return response()->json(['message' => 'Este presupuesto ya fue convertido.'], 422);
        }

        if ($invoice->status === InvoiceStatusService::CANCELLED) {
            return response()->json(['message' => 'No se puede convertir un presupuesto anulado.'], 422);
        }

        if ($invoice->status === InvoiceStatusService::DRAFT || $invoice->invoice_number === null) {
            return response()->json(['message' => 'Debe emitir el presupuesto antes de convertirlo en factura.'], 422);
        }

        if (! in_array($invoice->status, [InvoiceStatusService::ISSUED, InvoiceStatusService::ACCEPTED], true)) {
            return response()->json(['message' => 'Solo se pueden convertir presupuestos emitidos o aceptados.'], 422);
        }

        try {
            $factura = DB::transaction(function () use ($invoice, $request): Invoice {
                $term = $invoice->paymentTerm;
                $invoiceDate = CarbonImmutable::today();

                $factura = Invoice::query()->create([
                    'document_type' => 'invoice',
                    'source_quotation_id' => $invoice->id,
                    'invoice_date' => $invoiceDate->toDateString(),
                    'due_date' => $term ? $invoiceDate->addDays($term->days)->toDateString() : $invoice->due_date?->toDateString(),
                    'payment_term_id' => $invoice->payment_term_id,
                    'client_id' => $invoice->client_id,
                    'client_name' => $invoice->client_name,
                    'client_tax_id' => $invoice->client_tax_id,
                    'client_address' => $invoice->client_address,
                    'client_city' => $invoice->client_city,
                    'currency_id' => $invoice->currency_id,
                    'currency_code' => $invoice->currency_code,
                    'currency_symbol' => $invoice->currency_symbol,
                    'currency_decimal_separator' => $invoice->currency_decimal_separator,
                    'currency_thousand_separator' => $invoice->currency_thousand_separator,
                    'currency_decimal_places' => $invoice->currency_decimal_places,
                    'currency_symbol_position' => $invoice->currency_symbol_position,
                    'fiscal_profile_id' => $invoice->fiscal_profile_id,
                    'logo_path' => $invoice->logo_path,
                    'seller_name' => $invoice->seller_name,
                    'seller_tax_id' => $invoice->seller_tax_id,
                    'seller_address' => $invoice->seller_address,
                    'seller_city' => $invoice->seller_city,
                    'bank_account_id' => $invoice->bank_account_id,
                    'warranty_id' => $invoice->warranty_id,
                    'warranty_text' => $invoice->warranty_text,
                    'legal_text' => $invoice->legal_text,
                    'conformity_text' => $invoice->conformity_text,
                    'observations' => $invoice->observations,
                    'amount_received' => 0,
                    'subtotal' => $invoice->subtotal,
                    'tax_total' => $invoice->tax_total,
                    'total' => $invoice->total,
                    'balance_due' => $invoice->total,
                    'status' => InvoiceStatusService::ISSUED,
                    'prepared_by' => $invoice->prepared_by,
                    'received_by' => $invoice->received_by,
                    'created_by' => $request->user()->id,
                    'updated_by' => $request->user()->id,
                ]);

                foreach ($invoice->items as $item) {
                    $factura->items()->create([
                        'description' => $item->description,
                        'quantity' => $item->quantity,
                        'unit_cost' => $item->unit_cost,
                        'tax_id' => $item->tax_id,
                        'tax_name' => $item->tax_name,
                        'tax_rate' => $item->tax_rate,
                        'tax_amount' => $item->tax_amount,
                        'unit_price' => $item->unit_price,
                        'line_subtotal' => $item->line_subtotal,
                        'line_total' => $item->line_total,
                        'sort_order' => $item->sort_order,
                    ]);
                }

                $factura->invoice_number = $this->numberService->generateForInvoice($factura);
                $factura->save();
                $this->signatureService->signOnIssue($factura->load('items'));

                $invoice->update([
                    'status' => InvoiceStatusService::CONVERTED,
                    'converted_to_invoice_id' => $factura->id,
                    'converted_at' => now(),
                    'customer_accepted_at' => $invoice->customer_accepted_at ?? now(),
                    'updated_by' => $request->user()->id,
                ]);

                $this->activityLog->record(
                    'invoice.quotation_converted',
                    $invoice,
                    ['quotation_id' => $invoice->id, 'invoice_id' => $factura->id, 'invoice_number' => $factura->invoice_number],
                    $request->user(),
                    $request,
                );

                return $factura;
            });

            return response()->json(['data' => new InvoiceResource($factura->load('items'))]);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 400);
        }
    }

    public function markPaid(MarkInvoicePaidRequest $request, Invoice $invoice): InvoiceResource|JsonResponse
    {
        if (! $this->statusService->canReceivePayments($invoice)) {
            return response()->json(['message' => 'Only invoices can receive payments.'], 409);
        }

        if ($invoice->status === InvoiceStatusService::CANCELLED) {
            return response()->json(['message' => 'Cancelled invoices cannot receive payments.'], 409);
        }

        if ((float) $invoice->balance_due <= 0.0) {
            return response()->json(['message' => 'Invoice does not have pending balance.'], 409);
        }

        $invoice = DB::transaction(function () use ($request, $invoice): Invoice {
            $data = $request->validated();
            $amount = (string) ($data['amount'] ?? $invoice->balance_due);
            $newReceived = BigDecimal::of((string) $invoice->amount_received)->plus($amount);

            $invoice->payments()->create([
                'payment_date' => $data['payment_date'] ?? now()->toDateString(),
                'amount' => $amount,
                'method' => $data['method'] ?? null,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()?->id,
            ]);

            $calculated = $this->calculator->calculate($this->itemsForCalculation($invoice), (string) $newReceived);

            $invoice->update([
                'amount_received' => $calculated['amount_received'],
                'balance_due' => $calculated['balance_due'],
                'status' => $this->statusService->determine(
                    InvoiceStatusService::ISSUED,
                    $invoice->total,
                    $calculated['amount_received'],
                    $calculated['balance_due'],
                    $invoice->due_date,
                ),
            ]);

            $this->activityLog->record('invoice.payment_recorded', $invoice, ['amount' => $amount], $request->user(), $request);

            return $invoice->fresh(['items', 'payments']);
        });

        return new InvoiceResource($invoice);
    }

    public function verify(Request $request): JsonResponse
    {
        $result = $this->signatureService->verifyByCode(
            $request->query('number'),
            $request->query('code'),
        );
        $result['type'] = 'invoice';

        if ($result['status'] === 'not_found') {
            $reportResult = $this->reportSignatureService->verifyByCode(
                $request->query('number'),
                $request->query('code'),
            );
            $reportResult['type'] = 'report';
            $result = $reportResult['status'] === 'not_found' ? $result : $reportResult;
        }

        $invoice = $result['invoice'] ?? null;
        $report = $result['report'] ?? null;

        return response()->json([
            'status' => $result['status'],
            'type' => $result['type'],
            'authentic' => $result['status'] === 'authentic',
            'invoice' => $invoice === null ? null : [
                'invoice_number' => $invoice->invoice_number,
                'document_type' => $invoice->document_type,
                'seller_name' => $invoice->seller_name,
                'seller_tax_id' => $invoice->seller_tax_id,
                'client_name' => $invoice->client_name,
                'client_tax_id' => $invoice->client_tax_id,
                'invoice_date' => $invoice->invoice_date?->toDateString(),
                'currency_code' => $invoice->currency_code,
                'total' => (string) $invoice->total,
            ],
            'report' => $report === null ? null : [
                'report_number' => $report->report_number,
                'seller_name' => $report->seller_name,
                'seller_tax_id' => $report->seller_tax_id,
                'recipient_name' => $report->recipient_name,
                'recipient_tax_id' => $report->recipient_tax_id,
                'report_date' => $report->report_date?->toDateString(),
            ],
        ]);
    }

    public function generatePdf(Request $request, Invoice $invoice): JsonResponse
    {
        if ($invoice->invoice_number === null) {
            return response()->json(['message' => 'Invoice must be issued before generating the final PDF.'], 409);
        }

        try {
            $path = $this->pdfService->generate($invoice);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 500);
        }

        $invoice->update([
            'pdf_path' => $path,
            'pdf_sha256' => $this->pdfChecksum($path),
        ]);
        $this->activityLog->record('invoice.pdf_generated', $invoice, ['pdf_path' => $path], $request->user(), $request);

        return response()->json(['pdf_path' => $path]);
    }

    public function downloadPdf(Invoice $invoice): BinaryFileResponse|JsonResponse
    {
        if (! $invoice->pdf_path || ! Storage::disk('public')->exists($invoice->pdf_path)) {
            return response()->json(['message' => 'PDF is not available for this invoice.'], 404);
        }

        $path = Storage::disk('public')->path($invoice->pdf_path);

        if (! is_file($path) || filesize($path) === 0) {
            return response()->json(['message' => 'PDF is not available for this invoice.'], 404);
        }

        return response()->download($path);
    }

    private function pdfChecksum(string $relativePath): ?string
    {
        if (! Storage::disk('public')->exists($relativePath)) {
            return null;
        }

        return hash('sha256', Storage::disk('public')->get($relativePath));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function invoicePayload(array $data, string $status, ?Invoice $invoice = null, bool $persistClient = true): array
    {
        $client = $this->clientResolver->resolve($data, $persistClient);
        $currency = Currency::query()->findOrFail($data['currency_id']);
        $paymentTerm = PaymentTerm::query()->findOrFail($data['payment_term_id']);
        $fiscalProfile = isset($data['fiscal_profile_id']) ? FiscalProfile::query()->find($data['fiscal_profile_id']) : null;
        $bankAccount = isset($data['bank_account_id']) ? BankAccount::query()->find($data['bank_account_id']) : null;
        $warranty = isset($data['warranty_id']) ? Warranty::query()->find($data['warranty_id']) : null;
        $invoiceDate = CarbonImmutable::parse($data['invoice_date']);

        return [
            'invoice_number' => $data['invoice_number'] ?? $invoice?->invoice_number,
            'document_type' => $data['document_type'] ?? $invoice?->document_type ?? 'invoice',
            'invoice_date' => $invoiceDate->toDateString(),
            'due_date' => ($data['document_type'] ?? $invoice?->document_type ?? 'invoice') === Invoice::DOCUMENT_TYPE_QUOTATION
                ? $invoiceDate->addDays(30)->toDateString()
                : ($data['due_date'] ?? $invoiceDate->addDays($paymentTerm->days)->toDateString()),
            'payment_term_id' => $paymentTerm->id,
            'client_id' => $client->id,
            'client_name' => $client->name,
            'client_tax_id' => $client->tax_id,
            'client_address' => $client->address,
            'client_city' => $client->city,
            'currency_id' => $currency->id,
            'currency_code' => $currency->code,
            'currency_symbol' => $currency->symbol,
            'currency_decimal_separator' => $currency->decimal_separator,
            'currency_thousand_separator' => $currency->thousand_separator,
            'currency_decimal_places' => $currency->decimal_places,
            'currency_symbol_position' => $currency->symbol_position,
            'fiscal_profile_id' => $fiscalProfile?->id,
            'logo_path' => array_key_exists('logo_path', $data)
                ? (($data['logo_path'] ?? '') !== '' ? $data['logo_path'] : null)
                : ($invoice?->logo_path ?? $fiscalProfile?->logo_path),
            'seller_name' => $fiscalProfile?->name,
            'seller_tax_id' => $fiscalProfile?->tax_id,
            'seller_address' => $fiscalProfile?->address,
            'seller_city' => $fiscalProfile?->city,
            'bank_account_id' => $bankAccount?->id,
            'warranty_id' => $warranty?->id,
            // The catalog entry is canonical; clients must not replace it with the legal footer.
            'warranty_text' => $warranty?->full_text,
            'legal_text' => $data['legal_text'] ?? $invoice?->legal_text ?? $this->defaultLegalFooter(),
            'conformity_text' => $data['conformity_text'] ?? $invoice?->conformity_text ?? $this->defaultConformityText(),
            'observations' => $data['observations'] ?? null,
            'amount_received' => $this->amountReceivedForDocument($data['document_type'] ?? $invoice?->document_type ?? Invoice::DOCUMENT_TYPE_INVOICE, $data['amount_received'] ?? $invoice?->amount_received ?? 0),
            'status' => $status,
            'prepared_by' => $data['prepared_by'] ?? null,
            'received_by' => $data['received_by'] ?? null,
            'created_by' => $invoice?->created_by ?? request()->user()?->id,
            'updated_by' => request()->user()?->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function draftPreviewInvoice(array $data): Invoice
    {
        $payload = $this->invoicePayload($data, InvoiceStatusService::DRAFT, persistClient: false);
        $itemsForCalculation = $this->hydrateTaxes($data['items']);
        $calculated = $this->calculator->calculate(
            $itemsForCalculation,
            $data['amount_received'] ?? '0',
            $this->pricesIncludeTax(),
        );

        $invoice = new Invoice([
            ...$payload,
            'amount_received' => $calculated['amount_received'],
            'subtotal' => $calculated['subtotal'],
            'tax_total' => $calculated['tax_total'],
            'total' => $calculated['total'],
            'balance_due' => $calculated['balance_due'],
        ]);

        $invoice->setRelation('items', new EloquentCollection(
            collect($calculated['items'])->map(fn (array $item, int $index): InvoiceItem => new InvoiceItem([
                ...$item,
                'sort_order' => $index,
            ]))->all(),
        ));
        $invoice->setRelation('paymentTerm', PaymentTerm::query()->find($payload['payment_term_id']));
        $invoice->setRelation('bankAccount', isset($payload['bank_account_id'])
            ? BankAccount::query()->with('currency')->find($payload['bank_account_id'])
            : null);
        $invoice->setRelation('fiscalProfile', isset($payload['fiscal_profile_id'])
            ? FiscalProfile::query()->find($payload['fiscal_profile_id'])
            : null);

        return $invoice;
    }

    private function previewResponse(Invoice $invoice): Response
    {
        return response()->make(
            view('pdf.invoice', [
                'invoice' => $invoice,
                'legalText' => $invoice->legal_text,
            ])->render(),
            200,
            ['Content-Type' => 'text/html; charset=UTF-8'],
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncItemsAndTotals(Invoice $invoice, array $items, string|int|float $amountReceived): void
    {
        $itemsForCalculation = $this->hydrateTaxes($items);
        $calculated = $this->calculator->calculate(
            $itemsForCalculation,
            $amountReceived,
            $this->pricesIncludeTax(),
        );

        $invoice->items()->delete();

        foreach ($calculated['items'] as $index => $item) {
            $invoice->items()->create([
                ...$item,
                'sort_order' => $index,
            ]);
        }

        $invoice->update([
            'amount_received' => $calculated['amount_received'],
            'subtotal' => $calculated['subtotal'],
            'tax_total' => $calculated['tax_total'],
            'total' => $calculated['total'],
            'balance_due' => $calculated['balance_due'],
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function hydrateTaxes(array $items): array
    {
        $taxes = Tax::query()->whereIn('id', collect($items)->pluck('tax_id')->filter()->all())->get()->keyBy('id');

        return collect($items)->map(function (array $item) use ($taxes): array {
            $tax = $taxes->get($item['tax_id']);

            return [
                ...$item,
                'tax_name' => $tax?->name,
                'tax_rate' => $tax?->rate ?? '0',
            ];
        })->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function itemsForCalculation(Invoice $invoice): array
    {
        return $invoice->items()->get()->map(fn ($item): array => [
            'description' => $item->description,
            'quantity' => $item->quantity,
            'unit_cost' => $item->unit_cost,
            'tax_id' => $item->tax_id,
            'tax_name' => $item->tax_name,
            'tax_rate' => $item->tax_rate,
        ])->all();
    }

    private function pricesIncludeTax(): bool
    {
        $setting = Setting::query()->where('key', 'tax.prices_include_tax')->first();

        return (bool) data_get($setting?->value, 'enabled', false);
    }

    private function ensureManualNumberIsAllowed(?string $invoiceNumber, ?Invoice $invoice = null): void
    {
        if ($invoiceNumber === null || $invoiceNumber === $invoice?->invoice_number) {
            return;
        }

        abort(422, 'Manual invoice numbers are not allowed.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function changesDocumentTypeAfterDraft(Invoice $invoice, array $data): bool
    {
        if (! array_key_exists('document_type', $data) || $data['document_type'] === $invoice->document_type) {
            return false;
        }

        return $invoice->status !== InvoiceStatusService::DRAFT;
    }

    private function amountReceivedForDocument(string $documentType, mixed $amountReceived): string|int|float
    {
        return $documentType === Invoice::DOCUMENT_TYPE_QUOTATION ? 0 : ($amountReceived ?? 0);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function mergeInvoiceData(Invoice $invoice, array $data): array
    {
        return [
            'invoice_number' => $data['invoice_number'] ?? $invoice->invoice_number,
            'document_type' => $data['document_type'] ?? $invoice->document_type ?? 'invoice',
            'invoice_date' => $data['invoice_date'] ?? $invoice->invoice_date->toDateString(),
            'due_date' => array_key_exists('due_date', $data) ? $data['due_date'] : $invoice->due_date?->toDateString(),
            'payment_term_id' => $data['payment_term_id'] ?? $invoice->payment_term_id,
            'client_id' => array_key_exists('client_id', $data)
                ? $data['client_id']
                : (array_key_exists('client_name', $data) ? null : $invoice->client_id),
            'client_name' => $data['client_name'] ?? $invoice->client_name,
            'client_tax_id' => array_key_exists('client_tax_id', $data) ? $data['client_tax_id'] : $invoice->client_tax_id,
            'client_address' => array_key_exists('client_address', $data) ? $data['client_address'] : $invoice->client_address,
            'client_city' => $data['client_city'] ?? null,
            'client_phone' => $data['client_phone'] ?? null,
            'client_email' => $data['client_email'] ?? null,
            'currency_id' => $data['currency_id'] ?? $invoice->currency_id,
            'fiscal_profile_id' => $data['fiscal_profile_id'] ?? $invoice->fiscal_profile_id,
            'logo_path' => array_key_exists('logo_path', $data)
                ? $data['logo_path']
                : (array_key_exists('fiscal_profile_id', $data)
                    ? FiscalProfile::query()->find($data['fiscal_profile_id'])?->logo_path
                    : $invoice->logo_path),
            'bank_account_id' => $data['bank_account_id'] ?? $invoice->bank_account_id,
            'warranty_id' => $data['warranty_id'] ?? $invoice->warranty_id,
            'warranty_text' => array_key_exists('warranty_text', $data) ? $data['warranty_text'] : $invoice->warranty_text,
            'legal_text' => array_key_exists('legal_text', $data) ? $data['legal_text'] : $invoice->legal_text,
            'conformity_text' => array_key_exists('conformity_text', $data) ? $data['conformity_text'] : $invoice->conformity_text,
            'observations' => array_key_exists('observations', $data) ? $data['observations'] : $invoice->observations,
            'amount_received' => $data['amount_received'] ?? $invoice->amount_received,
            'prepared_by' => array_key_exists('prepared_by', $data) ? $data['prepared_by'] : $invoice->prepared_by,
            'received_by' => array_key_exists('received_by', $data) ? $data['received_by'] : $invoice->received_by,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function requestChangesAmounts(array $data): bool
    {
        return collect(['items', 'amount_received', 'currency_id', 'payment_term_id', 'client_id', 'client_name', 'client_tax_id', 'client_address', 'client_city', 'client_phone', 'client_email', 'invoice_date', 'due_date'])
            ->contains(fn (string $field): bool => array_key_exists($field, $data));
    }

    private function defaultLegalFooter(): ?string
    {
        return $this->defaultLegalText()?->legal_footer;
    }

    private function defaultConformityText(): ?string
    {
        return $this->defaultLegalText()?->conformity_text;
    }

    /**
     * Active default legal text, fetched once per request.
     */
    private function defaultLegalText(): ?LegalText
    {
        if ($this->defaultLegalText !== false) {
            return $this->defaultLegalText;
        }

        return $this->defaultLegalText = LegalText::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->first();
    }
}
