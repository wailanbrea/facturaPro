<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Invoice extends Model
{
    public const DOCUMENT_TYPE_INVOICE = 'invoice';

    public const DOCUMENT_TYPE_QUOTATION = 'quotation';

    /**
     * Relations every PDF template needs eager-loaded.
     *
     * Kept here so the six render/copy points cannot drift apart; `client` used
     * to be missing everywhere even though the template reads it.
     *
     * @var list<string>
     */
    public const PDF_RELATIONS = [
        'items',
        'client',
        'paymentTerm',
        'bankAccount.currency',
        'fiscalProfile',
        'warranty',
        'intervention',
    ];

    protected $fillable = [
        'invoice_number',
        'document_type',
        'converted_to_invoice_id',
        'source_quotation_id',
        'converted_at',
        'invoice_date',
        'due_date',
        'payment_term_id',
        'client_id',
        'client_name',
        'client_tax_id',
        'client_address',
        'client_city',
        'currency_id',
        'currency_code',
        'currency_symbol',
        'currency_decimal_separator',
        'currency_thousand_separator',
        'currency_decimal_places',
        'currency_symbol_position',
        'fiscal_profile_id',
        'logo_path',
        'seller_name',
        'seller_tax_id',
        'seller_address',
        'seller_city',
        'bank_account_id',
        'warranty_id',
        'warranty_text',
        'legal_text',
        'conformity_text',
        'observations',
        'amount_received',
        'subtotal',
        'discount_percent',
        'discount_total',
        'travel_amount',
        'taxable_base',
        'tax_total',
        'total',
        'balance_due',
        'status',
        'prepared_by',
        'received_by',
        'technician_name',
        'work_reference',
        'service_location',
        'customer_signature_path',
        'customer_accepted_at',
        'pdf_path',
        'verification_code',
        'verification_hash',
        'previous_hash',
        'signed_at',
        'pdf_sha256',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'due_date' => 'date',
            'currency_decimal_places' => 'integer',
            'amount_received' => 'decimal:4',
            'subtotal' => 'decimal:4',
            'discount_percent' => 'decimal:2',
            'discount_total' => 'decimal:4',
            'travel_amount' => 'decimal:4',
            'taxable_base' => 'decimal:4',
            'tax_total' => 'decimal:4',
            'total' => 'decimal:4',
            'balance_due' => 'decimal:4',
            'customer_accepted_at' => 'datetime',
            'converted_at' => 'datetime',
            'signed_at' => 'datetime',
        ];
    }

    public function convertedToInvoice(): BelongsTo
    {
        return $this->belongsTo(self::class, 'converted_to_invoice_id');
    }

    public function sourceQuotation(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_quotation_id');
    }

    public function paymentTerm(): BelongsTo
    {
        return $this->belongsTo(PaymentTerm::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function fiscalProfile(): BelongsTo
    {
        return $this->belongsTo(FiscalProfile::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function warranty(): BelongsTo
    {
        return $this->belongsTo(Warranty::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }

    public function intervention(): HasOne
    {
        return $this->hasOne(InvoiceIntervention::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isInvoiceDocument(): bool
    {
        return $this->document_type === self::DOCUMENT_TYPE_INVOICE;
    }

    public function isQuotation(): bool
    {
        return $this->document_type === self::DOCUMENT_TYPE_QUOTATION;
    }

    /**
     * Blade view that renders this document as a PDF.
     *
     * Invoices and quotations have independent templates instead of one file
     * branching on the document type.
     */
    public function pdfView(): string
    {
        return $this->isQuotation() ? 'pdf.quotation' : 'pdf.invoice';
    }

    /**
     * The base imponible, falling back to the subtotal for documents issued
     * before the column existed.
     */
    public function taxableBaseOrSubtotal(): string
    {
        return (string) ($this->taxable_base ?? $this->subtotal);
    }
}
