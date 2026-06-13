<?php

namespace App\Services;

use App\Models\Invoice;
use RuntimeException;

/**
 * Produces and verifies the tamper-evident signature that proves an invoice
 * was issued by this system.
 *
 * Design (internal verification only, maximum security):
 *  - HMAC-SHA256 over a canonical string of the invoice's immutable fields,
 *    using a secret key that lives on the server and never in the database.
 *  - Each issued invoice's signature folds in the previous issued invoice's
 *    signature, forming a hash chain so insertions/deletions/edits in the
 *    database become detectable on audit.
 */
class InvoiceSignatureService
{
    /**
     * Stable string used as the "previous hash" of the very first invoice in
     * the chain.
     */
    public const GENESIS = 'FACTURAPRO-GENESIS';

    /**
     * Seal an invoice into the chain at issue time.
     *
     * Idempotent: an already-signed invoice is returned untouched so re-issuing
     * or converting never rewrites history.
     */
    public function signOnIssue(Invoice $invoice): Invoice
    {
        if ($invoice->verification_hash !== null) {
            return $invoice;
        }

        if (! is_string($invoice->invoice_number) || trim($invoice->invoice_number) === '') {
            throw new RuntimeException('An invoice must have a number before it can be signed.');
        }

        $previousHash = $this->latestChainHash($invoice);
        $hash = $this->computeHash($invoice, $previousHash);

        $invoice->forceFill([
            'previous_hash' => $previousHash,
            'verification_hash' => $hash,
            'verification_code' => $this->codeFromHash($hash),
            'signed_at' => $invoice->signed_at ?? now(),
        ])->save();

        return $invoice;
    }

    /**
     * True when the stored signature still matches the invoice's current data.
     */
    public function matches(Invoice $invoice): bool
    {
        if ($invoice->verification_hash === null) {
            return false;
        }

        return hash_equals(
            $invoice->verification_hash,
            $this->computeHash($invoice, $invoice->previous_hash ?? self::GENESIS),
        );
    }

    /**
     * Recompute the HMAC for an invoice given the previous link in the chain.
     */
    public function computeHash(Invoice $invoice, ?string $previousHash): string
    {
        return hash_hmac('sha256', $this->canonicalString($invoice, $previousHash), $this->key());
    }

    /**
     * Canonical, order-stable representation of the immutable invoice fields.
     *
     * Deliberately excludes amount_received / balance_due / status so that
     * registering a payment never invalidates the signature.
     */
    public function canonicalString(Invoice $invoice, ?string $previousHash): string
    {
        $items = $invoice->relationLoaded('items') ? $invoice->items : $invoice->items()->get();

        $itemsFingerprint = $items
            ->sortBy('sort_order')
            ->map(static fn ($item): string => implode('|', [
                trim((string) $item->description),
                (string) $item->quantity,
                (string) $item->unit_cost,
                (string) $item->tax_rate,
                (string) $item->line_total,
            ]))
            ->implode('||');

        return implode("\n", [
            'v1',
            (string) $invoice->invoice_number,
            (string) $invoice->document_type,
            (string) ($invoice->seller_tax_id ?? ''),
            (string) ($invoice->client_tax_id ?? ''),
            (string) ($invoice->client_name ?? ''),
            $invoice->invoice_date?->toDateString() ?? '',
            (string) $invoice->currency_code,
            (string) $invoice->subtotal,
            (string) $invoice->tax_total,
            (string) $invoice->total,
            hash('sha256', $itemsFingerprint),
            $previousHash ?? self::GENESIS,
        ]);
    }

    /**
     * Resolve a verification request (invoice number + security code) into a
     * structured result. Shared by the web page and the API endpoint.
     *
     * Possible statuses:
     *  - not_found:     no invoice with that number exists / it is not signed
     *  - code_mismatch: the number exists but the code does not match
     *  - altered:       code matches but the stored data was tampered with
     *  - authentic:     genuine, untampered, system-issued document
     *
     * @return array{status: string, invoice: ?Invoice}
     */
    public function verifyByCode(?string $number, ?string $code): array
    {
        $number = is_string($number) ? trim($number) : '';
        $code = is_string($code) ? strtoupper(trim($code)) : '';

        if ($number === '') {
            return ['status' => 'not_found', 'invoice' => null];
        }

        $invoice = Invoice::query()
            ->whereNotNull('verification_hash')
            ->where('invoice_number', $number)
            ->first();

        if ($invoice === null) {
            return ['status' => 'not_found', 'invoice' => null];
        }

        if ($code === '' || ! hash_equals((string) $invoice->verification_code, $code)) {
            return ['status' => 'code_mismatch', 'invoice' => null];
        }

        return [
            'status' => $this->matches($invoice) ? 'authentic' : 'altered',
            'invoice' => $invoice,
        ];
    }

    /**
     * Internal URL a verifier lands on after scanning the QR code.
     */
    public function verificationUrl(Invoice $invoice): string
    {
        $base = config('invoice.verification_url');

        if (! is_string($base) || $base === '') {
            $base = rtrim((string) config('app.url'), '/').'/invoices/verify';
        }

        return rtrim($base, '/').'?'.http_build_query([
            'number' => $invoice->invoice_number,
            'code' => $invoice->verification_code,
        ]);
    }

    /**
     * Short, human-friendly code derived from the signature, e.g.
     * "A1B2-C3D4-E5F6-7890". Printed on the document for manual checks.
     */
    public function codeFromHash(string $hash): string
    {
        $segment = strtoupper(substr($hash, 0, 16));

        return implode('-', str_split($segment, 4));
    }

    /**
     * Latest signature already in the chain (the link a new invoice points to).
     */
    private function latestChainHash(Invoice $exclude): string
    {
        $previous = Invoice::query()
            ->whereNotNull('verification_hash')
            ->where('id', '!=', $exclude->getKey())
            ->orderByDesc('signed_at')
            ->orderByDesc('id')
            ->first();

        return $previous?->verification_hash ?? self::GENESIS;
    }

    private function key(): string
    {
        $configured = config('invoice.signing_key');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $appKey = (string) config('app.key');

        if ($appKey === '') {
            throw new RuntimeException('Cannot sign invoices: neither INVOICE_SIGNING_KEY nor APP_KEY is set.');
        }

        // Derive a stable, dedicated key from APP_KEY when no explicit key is set.
        return hash_hmac('sha256', 'facturapro:invoice-signing', $appKey);
    }
}
