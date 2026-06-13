<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    public const DOCUMENT_TYPE_INVOICE = 'invoice';

    public const DOCUMENT_TYPE_QUOTATION = 'quotation';

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
        'tax_total',
        'total',
        'balance_due',
        'status',
        'prepared_by',
        'received_by',
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
}
