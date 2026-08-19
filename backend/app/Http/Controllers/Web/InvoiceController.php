<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Client;
use App\Models\Currency;
use App\Models\FiscalProfile;
use App\Models\FiscalProfileLogo;
use App\Models\Invoice;
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
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceCalculationService $calculator,
        private readonly InvoiceClientResolver $clientResolver,
        private readonly InvoiceNumberService $numberService,
        private readonly InvoiceStatusService $statusService,
        private readonly InvoicePdfService $pdfService,
        private readonly InvoiceSignatureService $signatureService,
        private readonly ActivityLogService $activityLog,
    ) {}

    public function index(): View
    {
        $fiscalProfiles = auth()->user()->availableFiscalProfiles();
        $fiscalProfileIds = $fiscalProfiles->pluck('id');

        $invoices = Invoice::query()
            ->with('fiscalProfile')
            ->whereIn('fiscal_profile_id', $fiscalProfileIds)
            ->when(request('status'), fn ($query, string $status) => $query->where('status', $status))
            ->when(
                in_array(request('document_type'), [Invoice::DOCUMENT_TYPE_INVOICE, Invoice::DOCUMENT_TYPE_QUOTATION], true),
                fn ($query) => $query->where('document_type', request('document_type')),
            )
            ->when(request('fiscal_profile_id'), fn ($query, string $profileId) => $query->where('fiscal_profile_id', $profileId))
            ->when(request('search'), function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('invoice_number', 'like', "%{$search}%")
                        ->orWhere('client_name', 'like', "%{$search}%")
                        ->orWhere('seller_name', 'like', "%{$search}%");
                });
            })
            ->latest('invoice_date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('invoices.index', compact('invoices', 'fiscalProfiles'));
    }

    public function create(): View
    {
        return view('invoices.create', [
            ...$this->catalogs(),
            'lockedFields' => $this->lockedFieldsForUser(),
            'invoice' => new Invoice([
                'document_type' => 'invoice',
                'invoice_date' => now()->toDateString(),
                'amount_received' => 0,
                'legal_text' => $this->defaultLegalFooter(),
                'conformity_text' => $this->defaultConformityText(),
            ]),
            'items' => collect([(object) [
                'description' => '',
                'quantity' => 1,
                'unit_cost' => 0,
                'tax_id' => null,
            ]]),
            'action' => route('web.invoices.store'),
            'method' => 'POST',
            'submitLabel' => 'Guardar borrador',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        if ($this->quotationReceivesPayment($data)) {
            return back()
                ->withErrors(['amount_received' => 'Los presupuestos no aceptan importes recibidos.'])
                ->withInput();
        }

        $data = $this->stripLockedFields($data);

        $invoice = DB::transaction(function () use ($data): Invoice {
            $documentType = $data['document_type'];
            $invoice = Invoice::query()->create($this->invoicePayload($data));

            $calculated = $this->calculator->calculate(
                $this->hydrateTaxes($data['items']),
                $this->amountReceivedForDocument($documentType, $data['amount_received'] ?? 0),
                discountPercent: $data['discount_percent'] ?? '0',
                travelAmount: $data['travel_amount'] ?? '0',
            );

            foreach ($calculated['items'] as $index => $item) {
                $invoice->items()->create([...$item, 'sort_order' => $index]);
            }

            $invoice->update([
                'logo_path' => array_key_exists('logo_path', $data)
                    ? (($data['logo_path'] ?? '') !== '' ? $data['logo_path'] : null)
                    : $invoice->logo_path,
                'amount_received' => $calculated['amount_received'],
                'subtotal' => $calculated['subtotal'],
                'discount_total' => $calculated['discount_total'],
                'travel_amount' => $calculated['travel_amount'],
                'taxable_base' => $calculated['taxable_base'],
                'tax_total' => $calculated['tax_total'],
                'total' => $calculated['total'],
                'balance_due' => $calculated['balance_due'],
            ]);

            $this->saveIntervention($invoice, $data);

            $this->activityLog->record('invoice.created', $invoice, ['invoice_id' => $invoice->id], auth()->user(), request());

            return $invoice;
        });

        return redirect()->route('web.invoices.show', $invoice)->with('status', 'Factura borrador creada.');
    }

    public function show(Invoice $invoice): View
    {
        return view('invoices.show', ['invoice' => $invoice->load(['items', 'payments'])]);
    }

    public function preview(Invoice $invoice): View
    {
        return view($invoice->pdfView(), [
            'invoice' => $invoice->load(Invoice::PDF_RELATIONS),
            'legalText' => $invoice->legal_text ?: $this->defaultLegalFooter(),
        ]);
    }

    public function edit(Invoice $invoice): View|RedirectResponse
    {
        if ($invoice->status !== InvoiceStatusService::DRAFT) {
            return redirect()
                ->route('web.invoices.show', $invoice)
                ->withErrors(['invoice' => 'Solo se pueden editar facturas en borrador.']);
        }

        return view('invoices.create', [
            ...$this->catalogs(),
            'lockedFields' => $this->lockedFieldsForUser(),
            'invoice' => $invoice->load(['items', 'intervention']),
            'items' => $invoice->items,
            'action' => route('web.invoices.update', $invoice),
            'method' => 'PUT',
            'submitLabel' => 'Actualizar borrador',
        ]);
    }

    public function update(Request $request, Invoice $invoice): RedirectResponse
    {
        if ($invoice->status !== InvoiceStatusService::DRAFT) {
            return redirect()
                ->route('web.invoices.show', $invoice)
                ->withErrors(['invoice' => 'Solo se pueden editar facturas en borrador.']);
        }

        $data = $this->validated($request);

        if ($this->quotationReceivesPayment($data)) {
            return back()
                ->withErrors(['amount_received' => 'Los presupuestos no aceptan importes recibidos.'])
                ->withInput();
        }

        $data = $this->stripLockedFields($data);

        DB::transaction(function () use ($data, $invoice): void {
            $payload = $this->invoicePayload($data, $invoice);
            $invoice->update($payload);

            $calculated = $this->calculator->calculate(
                $this->hydrateTaxes($data['items']),
                $this->amountReceivedForDocument($data['document_type'], $data['amount_received'] ?? 0),
                discountPercent: $data['discount_percent'] ?? '0',
                travelAmount: $data['travel_amount'] ?? '0',
            );

            $invoice->items()->delete();

            foreach ($calculated['items'] as $index => $item) {
                $invoice->items()->create([...$item, 'sort_order' => $index]);
            }

            $invoice->update([
                'amount_received' => $calculated['amount_received'],
                'subtotal' => $calculated['subtotal'],
                'discount_total' => $calculated['discount_total'],
                'travel_amount' => $calculated['travel_amount'],
                'taxable_base' => $calculated['taxable_base'],
                'tax_total' => $calculated['tax_total'],
                'total' => $calculated['total'],
                'balance_due' => $calculated['balance_due'],
                'updated_by' => auth()->id(),
            ]);

            $this->saveIntervention($invoice, $data);

            $this->activityLog->record('invoice.updated', $invoice, ['invoice_id' => $invoice->id], auth()->user(), request());
        });

        return redirect()->route('web.invoices.show', $invoice)->with('status', 'Factura borrador actualizada.');
    }

    public function issue(Invoice $invoice): RedirectResponse
    {
        if ($invoice->status === InvoiceStatusService::CANCELLED) {
            return back()->withErrors(['invoice' => 'No se puede emitir una factura anulada.']);
        }

        if ($invoice->invoice_number === null) {
            try {
                $invoice->invoice_number = $this->numberService->generateForInvoice($invoice);
            } catch (RuntimeException $exception) {
                return back()->withErrors(['invoice' => $exception->getMessage()]);
            }
        }

        if ($invoice->isQuotation()) {
            $invoice->amount_received = 0;
            $invoice->balance_due = $invoice->total;
        }

        $invoice->status = $this->statusService->statusWhenIssued($invoice);
        $invoice->save();
        $this->signatureService->signOnIssue($invoice->load('items'));
        $this->activityLog->record('invoice.issued', $invoice, ['invoice_number' => $invoice->invoice_number], auth()->user(), request());

        return back()->with('status', $invoice->isQuotation() ? 'Presupuesto emitido.' : 'Factura emitida.');
    }

    public function cancel(Invoice $invoice): RedirectResponse
    {
        $invoice->update(['status' => InvoiceStatusService::CANCELLED]);
        $this->activityLog->record('invoice.cancelled', $invoice, ['invoice_number' => $invoice->invoice_number], auth()->user(), request());

        return back()->with('status', $invoice->isQuotation() ? 'Presupuesto anulado.' : 'Factura anulada.');
    }

    public function generatePdf(Invoice $invoice): RedirectResponse
    {
        if ($invoice->invoice_number === null) {
            return back()->withErrors(['invoice' => 'Debe emitir la factura antes de generar el PDF final.']);
        }

        try {
            $path = $this->pdfService->generate($invoice);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['invoice' => $exception->getMessage()]);
        }

        $invoice->update([
            'pdf_path' => $path,
            'pdf_sha256' => Storage::disk('public')->exists($path)
                ? hash('sha256', Storage::disk('public')->get($path))
                : null,
        ]);
        $this->activityLog->record('invoice.pdf_generated', $invoice, ['pdf_path' => $path], auth()->user(), request());

        return back()->with('status', 'PDF generado correctamente.');
    }

    public function downloadPdf(Invoice $invoice): BinaryFileResponse|RedirectResponse
    {
        if (! $invoice->pdf_path || ! Storage::disk('public')->exists($invoice->pdf_path)) {
            return back()->withErrors(['invoice' => 'El PDF no esta disponible para esta factura.']);
        }

        $path = Storage::disk('public')->path($invoice->pdf_path);

        if (! is_file($path) || filesize($path) === 0) {
            return back()->withErrors(['invoice' => 'El PDF no esta disponible para esta factura.']);
        }

        return response()->download($path);
    }

    public function markPaid(Invoice $invoice): RedirectResponse
    {
        if (! $this->statusService->canReceivePayments($invoice)) {
            return back()->withErrors(['invoice' => 'Solo las facturas aceptan pagos.']);
        }

        if ($invoice->status === InvoiceStatusService::CANCELLED) {
            return back()->withErrors(['invoice' => 'No se puede pagar una factura anulada.']);
        }

        if ((float) $invoice->balance_due <= 0.0) {
            return back()->withErrors(['invoice' => 'La factura no tiene balance pendiente.']);
        }

        $amount = $invoice->balance_due;
        $invoice->payments()->create([
            'payment_date' => now()->toDateString(),
            'amount' => $amount,
            'method' => 'efectivo',
            'created_by' => auth()->id(),
        ]);

        $invoice->update([
            'amount_received' => $invoice->total,
            'balance_due' => 0,
            'pdf_path' => null,
            'pdf_sha256' => null,
            'status' => InvoiceStatusService::PAID,
        ]);
        $this->activityLog->record('invoice.payment_recorded', $invoice, ['amount' => $amount], auth()->user(), request());

        return back()->with('status', 'Factura marcada como pagada.');
    }

    public function convertQuotation(Invoice $invoice): RedirectResponse
    {
        if ($invoice->document_type !== 'quotation') {
            return back()->withErrors(['invoice' => 'Solo se pueden convertir presupuestos.']);
        }

        if ($invoice->converted_to_invoice_id !== null) {
            return back()->withErrors(['invoice' => 'Este presupuesto ya fue convertido.']);
        }

        if ($invoice->status === InvoiceStatusService::CANCELLED) {
            return back()->withErrors(['invoice' => 'No se puede convertir un presupuesto anulado.']);
        }

        if ($invoice->status === InvoiceStatusService::DRAFT || $invoice->invoice_number === null) {
            return back()->withErrors(['invoice' => 'Debe emitir el presupuesto antes de convertirlo en factura.']);
        }

        if (! in_array($invoice->status, [InvoiceStatusService::ISSUED, InvoiceStatusService::ACCEPTED], true)) {
            return back()->withErrors(['invoice' => 'Solo se pueden convertir presupuestos emitidos o aceptados.']);
        }

        try {
            $factura = DB::transaction(function () use ($invoice): Invoice {
            $term = $invoice->paymentTerm;
            $invoiceDate = CarbonImmutable::today();

            $factura = Invoice::query()->create([
                'document_type' => 'invoice',
                'source_quotation_id' => $invoice->id,
                'invoice_date' => $invoiceDate->toDateString(),
                'due_date' => $invoiceDate->addDays($term && $term->days > 0 ? $term->days : 30)->toDateString(),
                'payment_term_id' => $invoice->payment_term_id,
                'client_id' => $invoice->client_id,
                'client_name' => $invoice->client_name,
                'client_tax_id' => $invoice->client_tax_id,
                'client_address' => $invoice->client_address,
                'client_city' => $invoice->client_city,
                'client_email' => $invoice->client_email,
                'client_phone' => $invoice->client_phone,
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
                'discount_percent' => $invoice->discount_percent,
                'discount_total' => $invoice->discount_total,
                'travel_amount' => $invoice->travel_amount,
                'taxable_base' => $invoice->taxable_base,
                'tax_total' => $invoice->tax_total,
                'total' => $invoice->total,
                'balance_due' => $invoice->total,
                'status' => InvoiceStatusService::DRAFT,
                'prepared_by' => $invoice->prepared_by,
                'received_by' => $invoice->received_by,
                'technician_name' => $invoice->technician_name,
                'work_reference' => $invoice->work_reference,
                'service_location' => $invoice->service_location,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            // Carry the technical intervention over, or the converted invoice
            // would lose the equipment, diagnosis and conclusions.
            if ($invoice->intervention !== null) {
                $factura->intervention()->create($invoice->intervention->only([
                    'equipment_type', 'equipment_brand', 'equipment_model', 'equipment_serial',
                    'equipment_location', 'units_indoor', 'units_outdoor',
                    'diagnostic_summary', 'technical_conclusions', 'service_scope', 'included_items',
                ]));
            }

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

            $invoice->update([
                'status' => InvoiceStatusService::CONVERTED,
                'converted_to_invoice_id' => $factura->id,
                'converted_at' => now(),
                'customer_accepted_at' => $invoice->customer_accepted_at ?? now(),
                'updated_by' => auth()->id(),
            ]);

            $this->activityLog->record(
                'invoice.quotation_converted',
                $invoice,
                ['quotation_id' => $invoice->id, 'invoice_id' => $factura->id],
                auth()->user(),
                request(),
            );

            return $factura;
            });
        } catch (RuntimeException $exception) {
            return back()->withErrors(['invoice' => $exception->getMessage()]);
        }

        return redirect()
            ->route('web.invoices.show', $factura)
            ->with('status', 'Presupuesto convertido en factura '.$factura->invoice_number.'.');
    }

    public function registerPayment(Request $request, Invoice $invoice): RedirectResponse
    {
        if ($invoice->status === InvoiceStatusService::CANCELLED) {
            return back()->withErrors(['payment' => 'No se puede registrar pagos en una factura anulada.']);
        }

        if ($invoice->document_type !== 'invoice') {
            return back()->withErrors(['payment' => 'Solo las facturas aceptan pagos.']);
        }

        if ($invoice->status === InvoiceStatusService::DRAFT || $invoice->invoice_number === null) {
            return back()->withErrors(['payment' => 'Debe emitir la factura antes de registrar pagos.']);
        }

        $balance = (float) $invoice->balance_due;

        if ($balance <= 0.0) {
            return back()->withErrors(['payment' => 'La factura no tiene balance pendiente.']);
        }

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:'.$balance],
            'payment_date' => ['nullable', 'date'],
            'method' => ['nullable', 'string', 'max:64'],
            'reference' => ['nullable', 'string', 'max:128'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($data, $invoice): void {
            $invoice->payments()->create([
                'payment_date' => $data['payment_date'] ?? now()->toDateString(),
                'amount' => $data['amount'],
                'method' => $data['method'] ?? 'efectivo',
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $totalReceived = (float) $invoice->payments()->sum('amount');
            $newBalance = max(0.0, (float) $invoice->total - $totalReceived);

            $invoice->update([
                'amount_received' => $totalReceived,
                'balance_due' => $newBalance,
                'pdf_path' => null,
                'pdf_sha256' => null,
                'status' => $this->statusService->determine(
                    InvoiceStatusService::ISSUED,
                    $invoice->total,
                    $totalReceived,
                    $newBalance,
                    $invoice->due_date,
                ),
            ]);

            $this->activityLog->record('invoice.payment_recorded', $invoice, ['amount' => $data['amount']], auth()->user(), request());
        });

        return back()->with('status', 'Pago registrado.');
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogs(): array
    {
        $fiscalProfiles = auth()->user()->availableFiscalProfiles()->load('logos');
        $availableLogos = $this->availableLogos($fiscalProfiles->pluck('id')->all());
        $userId = auth()->id();
        $numberPreviews = $fiscalProfiles->mapWithKeys(function (FiscalProfile $profile) use ($userId): array {
            return [
                $profile->id => [
                    'invoice' => $this->numberService->preview($profile->id, 'invoice', userId: $userId),
                    'quotation' => $this->numberService->preview($profile->id, 'quotation', userId: $userId),
                    'logos' => $profile->logos->mapWithKeys(fn (FiscalProfileLogo $logo): array => [
                        $logo->path => [
                            'invoice' => $this->numberService->preview($profile->id, 'invoice', $logo->path, $userId),
                            'quotation' => $this->numberService->preview($profile->id, 'quotation', $logo->path, $userId),
                        ],
                    ])->all(),
                ],
            ];
        });

        return [
            'clients' => Client::query()->where('is_active', true)->orderBy('name')->get(),
            'currencies' => Currency::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('code')->get(),
            'paymentTerms' => PaymentTerm::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('days')->get(),
            'taxes' => Tax::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get(),
            'fiscalProfiles' => $fiscalProfiles,
            'numberPreviews' => $numberPreviews,
            'availableLogos' => $availableLogos,
            'bankAccounts' => BankAccount::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('label')->get(),
            'warranties' => Warranty::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('duration_months')->get(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'document_type' => ['required', Rule::in(['invoice', 'quotation'])],
            'invoice_date' => ['required', 'date'],
            'payment_term_id' => ['required', 'exists:payment_terms,id'],
            'client_id' => ['nullable', 'exists:clients,id', 'required_without:client_name'],
            'client_name' => ['nullable', 'string', 'max:255', 'required_without:client_id'],
            'client_tax_id' => ['nullable', 'string', 'max:255'],
            'client_address' => ['nullable', 'string', 'max:255'],
            'client_city' => ['nullable', 'string', 'max:255'],
            'client_phone' => ['nullable', 'string', 'max:255'],
            'client_email' => ['nullable', 'email', 'max:255'],
            'currency_id' => ['required', 'exists:currencies,id'],
            'fiscal_profile_id' => ['required', 'exists:fiscal_profiles,id', Rule::in(auth()->user()->availableFiscalProfileIds())],
            'logo_path' => ['nullable', 'string', 'max:255'],
            'bank_account_id' => ['nullable', 'exists:bank_accounts,id'],
            'warranty_id' => ['required', 'exists:warranties,id'],
            'legal_text' => ['nullable', 'string'],
            'conformity_text' => ['nullable', 'string'],
            'edit_legal_texts' => ['nullable', 'boolean'],
            'observations' => ['nullable', 'string'],
            'amount_received' => ['nullable', 'numeric', 'min:0'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'travel_amount' => ['nullable', 'numeric', 'min:0'],
            'prepared_by' => ['nullable', 'string', 'max:255'],
            'received_by' => ['nullable', 'string', 'max:255'],
            'technician_name' => ['nullable', 'string', 'max:255'],
            'work_reference' => ['nullable', 'string', 'max:255'],
            'service_location' => ['nullable', 'string', 'max:255'],
            // Datos tecnicos. Todos opcionales: el formulario oculta la seccion
            // que no corresponde al tipo de documento pero sigue enviandola.
            // Los max:1200 son el techo que cabe en las cajas del PDF.
            'intervention' => ['nullable', 'array'],
            'intervention.equipment_type' => ['nullable', 'string', 'max:255'],
            'intervention.equipment_brand' => ['nullable', 'string', 'max:255'],
            'intervention.equipment_model' => ['nullable', 'string', 'max:255'],
            'intervention.equipment_serial' => ['nullable', 'string', 'max:255'],
            'intervention.equipment_location' => ['nullable', 'string', 'max:255'],
            'intervention.units_indoor' => ['nullable', 'integer', 'min:0', 'max:255'],
            'intervention.units_outdoor' => ['nullable', 'integer', 'min:0', 'max:255'],
            'intervention.diagnostic_summary' => ['nullable', 'string', 'max:1200'],
            'intervention.technical_conclusions' => ['nullable', 'string', 'max:1200'],
            'intervention.service_scope' => ['nullable', 'string', 'max:1200'],
            'intervention.included_items' => ['nullable', 'string', 'max:1200'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'items.*.tax_id' => ['required', 'exists:taxes,id'],
        ]);

        $this->validateLogoForFiscalProfile($data);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function invoicePayload(array $data, ?Invoice $invoice = null): array
    {
        $client = $this->clientResolver->resolve($data);
        $currency = Currency::query()->findOrFail($data['currency_id']);
        $term = PaymentTerm::query()->findOrFail($data['payment_term_id']);
        $profile = isset($data['fiscal_profile_id']) ? FiscalProfile::query()->find($data['fiscal_profile_id']) : null;
        $account = isset($data['bank_account_id']) ? BankAccount::query()->find($data['bank_account_id']) : null;
        $warranty = isset($data['warranty_id']) ? Warranty::query()->find($data['warranty_id']) : null;
        $invoiceDate = CarbonImmutable::parse($data['invoice_date']);
        $clientSnapshot = $this->clientResolver->snapshot($client, $data);

        return [
            'document_type' => $data['document_type'],
            'invoice_date' => $invoiceDate->toDateString(),
            'due_date' => $this->dueDateFor($data['document_type'], $invoiceDate, $term),
            'payment_term_id' => $term->id,
            ...$clientSnapshot,
            'currency_id' => $currency->id,
            'currency_code' => $currency->code,
            'currency_symbol' => $currency->symbol,
            'currency_decimal_separator' => $currency->decimal_separator,
            'currency_thousand_separator' => $currency->thousand_separator,
            'currency_decimal_places' => $currency->decimal_places,
            'currency_symbol_position' => $currency->symbol_position,
            'fiscal_profile_id' => $profile?->id,
            'logo_path' => array_key_exists('logo_path', $data)
                ? (($data['logo_path'] ?? '') !== '' ? $data['logo_path'] : null)
                : ($invoice?->logo_path ?? $profile?->logo_path),
            'seller_name' => $profile?->name,
            'seller_tax_id' => $profile?->tax_id,
            'seller_address' => $profile?->address,
            'seller_city' => $profile?->city,
            'bank_account_id' => $account?->id,
            'warranty_id' => $warranty?->id,
            'warranty_text' => $warranty?->full_text,
            'legal_text' => $data['legal_text'] ?? $invoice?->legal_text ?? $this->defaultLegalFooter(),
            'conformity_text' => $data['conformity_text'] ?? $invoice?->conformity_text ?? $this->defaultConformityText(),
            // Igual que los textos legales: si viene bloqueada, se conserva la
            // que ya tenia el documento en vez de vaciarla.
            'observations' => $data['observations'] ?? $invoice?->observations
                ?? (($data['document_type'] ?? null) === Invoice::DOCUMENT_TYPE_QUOTATION ? Invoice::DEFAULT_QUOTATION_OBSERVATIONS : null),
            'amount_received' => $this->amountReceivedForDocument($data['document_type'], $data['amount_received'] ?? 0),
            'status' => $invoice?->status ?? InvoiceStatusService::DRAFT,
            'prepared_by' => $data['prepared_by'] ?? null,
            'received_by' => $data['received_by'] ?? null,
            'technician_name' => $data['technician_name'] ?? null,
            'work_reference' => $data['work_reference'] ?? null,
            'service_location' => $data['service_location'] ?? null,
            'discount_percent' => $data['discount_percent'] ?? 0,
            'created_by' => $invoice?->created_by ?? auth()->id(),
            'updated_by' => auth()->id(),
        ];
    }

    /**
     * Persist the technical intervention block that feeds the PDF.
     *
     * The form always posts the section, even when hidden for the other document
     * type, so an all-empty payload deletes the row instead of storing noise.
     *
     * @param  array<string, mixed>  $data
     */
    private function saveIntervention(Invoice $invoice, array $data): void
    {
        if ($invoice->isQuotation()) {
            if (blank($data['intervention']['included_items'] ?? null)) {
                $data['intervention']['included_items'] = Invoice::DEFAULT_QUOTATION_INCLUDED_ITEMS;
            }
            if (blank($data['intervention']['service_scope'] ?? null)) {
                $data['intervention']['service_scope'] = Invoice::DEFAULT_QUOTATION_SERVICE_SCOPE;
            }
        }

        if (! array_key_exists('intervention', $data) || ! is_array($data['intervention'])) {
            return;
        }

        $hasAnyValue = collect($data['intervention'])->contains(fn ($v) => filled($v));

        if (! $hasAnyValue) {
            $invoice->intervention()->delete();

            return;
        }

        $invoice->intervention()->updateOrCreate(
            ['invoice_id' => $invoice->id],
            $data['intervention'],
        );
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
     * Presupuestos y facturas vencen por defecto a 30 dias a futuro.
     */
    private function dueDateFor(string $documentType, CarbonImmutable $invoiceDate, PaymentTerm $term): string
    {
        $days = $documentType === Invoice::DOCUMENT_TYPE_QUOTATION ? 30 : ($term->days > 0 ? $term->days : 30);

        return $invoiceDate->addDays($days)->toDateString();
    }

    /**
     * Campos bloqueados por el panel administrativo (invoice.locked_fields).
     *
     * @return array<int, string>
     */
    private function lockedFields(): array
    {
        $value = Setting::query()->where('key', 'invoice.locked_fields')->value('value');

        return array_values(array_intersect(
            (array) ($value['fields'] ?? ['conformity_text', 'legal_text']),
            ['conformity_text', 'legal_text', 'observations'],
        ));
    }

    /**
     * Campos bloqueados para el usuario autenticado (los administradores con
     * permiso de configuracion pueden editarlos siempre).
     *
     * @return array<int, string>
     */
    private function lockedFieldsForUser(): array
    {
        if (auth()->user()?->hasPermission('configurar_sistema')) {
            return [];
        }

        return $this->lockedFields();
    }

    /**
     * Descarta del request los campos bloqueados para que conserven su valor
     * por defecto (crear) o el valor ya guardado (editar).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function stripLockedFields(array $data): array
    {
        // Sin pulsar "Habilitar edicion" los textos legales y observaciones conservan
        // el valor que ya tenian. Los campos tecnicos (diagnostico, conclusiones,
        // equipo, contenido de presupuesto) SIEMPRE se conservan y guardan.
        if (! (bool) ($data['edit_legal_texts'] ?? false)) {
            unset($data['legal_text'], $data['conformity_text'], $data['observations']);
        }

        unset($data['edit_legal_texts']);

        // El bloqueo administrativo pesa mas que el boton: si un campo esta
        // marcado en Campos bloqueados, el boton no lo desbloquea.
        foreach ($this->lockedFieldsForUser() as $field) {
            if (in_array($field, ['legal_text', 'conformity_text'], true)) {
                continue;
            }

            unset($data[$field]);
        }

        return $data;
    }

    private function amountReceivedForDocument(string $documentType, mixed $amountReceived): string|int|float
    {
        return $documentType === Invoice::DOCUMENT_TYPE_QUOTATION ? 0 : ($amountReceived ?? 0);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function quotationReceivesPayment(array $data): bool
    {
        return ($data['document_type'] ?? Invoice::DOCUMENT_TYPE_INVOICE) === Invoice::DOCUMENT_TYPE_QUOTATION
            && (float) ($data['amount_received'] ?? 0) > 0.0;
    }

    private function defaultLegalFooter(): ?string
    {
        return LegalText::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->value('legal_footer');
    }

    private function defaultConformityText(): ?string
    {
        return Invoice::DEFAULT_INTERVENTION_ACCEPTANCE;
    }

    private function validateLogoForFiscalProfile(array $data): void
    {
        if (blank($data['logo_path'] ?? null)) {
            if (FiscalProfileLogo::query()->where('fiscal_profile_id', $data['fiscal_profile_id'])->exists()) {
                throw ValidationException::withMessages([
                    'logo_path' => 'Debe seleccionar un logo para este perfil fiscal.',
                ]);
            }

            return;
        }

        $exists = FiscalProfileLogo::query()
            ->where('fiscal_profile_id', $data['fiscal_profile_id'])
            ->where('path', $data['logo_path'])
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'logo_path' => 'El logo seleccionado no pertenece a este perfil.',
            ]);
        }
    }

    /**
     * @param  array<int, int>  $profileIds
     */
    private function availableLogos(array $profileIds)
    {
        return FiscalProfileLogo::query()
            ->whereIn('fiscal_profile_id', $profileIds)
            ->orderBy('fiscal_profile_id')
            ->orderByDesc('is_default')
            ->orderBy('label')
            ->get(['fiscal_profile_id', 'path', 'label', 'is_default']);
    }
}
