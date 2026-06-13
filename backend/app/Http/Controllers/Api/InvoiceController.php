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
use App\Models\InvoiceNumberSetting;
use App\Models\LegalText;
use App\Models\PaymentTerm;
use App\Models\Setting;
use App\Models\Tax;
use App\Models\Warranty;
use App\Services\ActivityLogService;
use App\Services\InvoiceCalculationService;
use App\Services\InvoiceNumberService;
use App\Services\InvoicePdfService;
use App\Services\InvoiceSignatureService;
use App\Services\InvoiceStatusService;
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
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceController extends Controller
{
    /**
     * Memoized active default legal text. `false` means "not yet loaded".
     */
    private LegalText|null|false $defaultLegalText = false;

    public function __construct(
        private readonly InvoiceCalculationService $calculator,
        private readonly InvoiceStatusService $statusService,
        private readonly InvoiceNumberService $numberService,
        private readonly InvoicePdfService $pdfService,
        private readonly InvoiceSignatureService $signatureService,
        private readonly ActivityLogService $activityLog,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $invoices = Invoice::query()
            ->with('items')
            ->when(request('status'), fn ($query, string $status) => $query->where('status', $status))
            ->when(request('client_id'), fn ($query, string $clientId) => $query->where('client_id', $clientId))
            ->when(request('search'), function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('invoice_number', 'like', "%{$search}%")
                        ->orWhere('client_name', 'like', "%{$search}%")
                        ->orWhere('client_tax_id', 'like', "%{$search}%");
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
        return $this->previewResponse($invoice->load(['items', 'paymentTerm', 'bankAccount.currency', 'fiscalProfile']));
    }

    public function previewIssue(Invoice $invoice): Response|JsonResponse
    {
        if ($invoice->status === InvoiceStatusService::CANCELLED) {
            return response()->json(['message' => 'Cancelled invoices cannot be issued.'], 409);
        }

        $invoice->load(['items', 'paymentTerm', 'bankAccount.currency', 'fiscalProfile']);
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

        return new InvoiceResource($invoice);
    }

    public function cancel(Request $request, Invoice $invoice): InvoiceResource
    {
        $invoice->update(['status' => InvoiceStatusService::CANCELLED]);
        $this->activityLog->record('invoice.cancelled', $invoice, ['invoice_number' => $invoice->invoice_number], $request->user(), $request);

        return new InvoiceResource($invoice->fresh('items'));
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

        $invoice = $result['invoice'];

        return response()->json([
            'status' => $result['status'],
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

    public function downloadPdf(Invoice $invoice): StreamedResponse|JsonResponse
    {
        if (! $invoice->pdf_path || ! Storage::disk('public')->exists($invoice->pdf_path)) {
            return response()->json(['message' => 'PDF is not available for this invoice.'], 404);
        }

        return Storage::disk('public')->download($invoice->pdf_path);
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
    private function invoicePayload(array $data, string $status, ?Invoice $invoice = null): array
    {
        $client = Client::query()->findOrFail($data['client_id']);
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
            'due_date' => $data['due_date'] ?? $invoiceDate->addDays($paymentTerm->days)->toDateString(),
            'payment_term_id' => $paymentTerm->id,
            'client_id' => $client->id,
            'client_name' => $client->name,
            'client_tax_id' => $client->tax_id,
            'client_address' => $client->address,
            'currency_id' => $currency->id,
            'currency_code' => $currency->code,
            'currency_symbol' => $currency->symbol,
            'currency_decimal_separator' => $currency->decimal_separator,
            'currency_thousand_separator' => $currency->thousand_separator,
            'currency_decimal_places' => $currency->decimal_places,
            'currency_symbol_position' => $currency->symbol_position,
            'fiscal_profile_id' => $fiscalProfile?->id,
            'seller_name' => $fiscalProfile?->name,
            'seller_tax_id' => $fiscalProfile?->tax_id,
            'seller_address' => $fiscalProfile?->address,
            'seller_city' => $fiscalProfile?->city,
            'bank_account_id' => $bankAccount?->id,
            'warranty_id' => $warranty?->id,
            'warranty_text' => $data['warranty_text'] ?? $warranty?->full_text,
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
        $payload = $this->invoicePayload($data, InvoiceStatusService::DRAFT);
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

        $setting = InvoiceNumberSetting::query()->first();

        abort_if(! $setting?->allow_manual_number, 422, 'Manual invoice numbers are not allowed.');
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
            'client_id' => $data['client_id'] ?? $invoice->client_id,
            'currency_id' => $data['currency_id'] ?? $invoice->currency_id,
            'fiscal_profile_id' => $data['fiscal_profile_id'] ?? $invoice->fiscal_profile_id,
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
        return collect(['items', 'amount_received', 'currency_id', 'payment_term_id', 'client_id', 'invoice_date', 'due_date'])
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
