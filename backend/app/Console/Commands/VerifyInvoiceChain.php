<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Services\InvoiceSignatureService;
use Illuminate\Console\Command;

class VerifyInvoiceChain extends Command
{
    protected $signature = 'invoices:verify-chain';

    protected $description = 'Audit the integrity of the signed invoice chain and report tampering.';

    public function handle(InvoiceSignatureService $signature): int
    {
        $invoices = Invoice::query()
            ->whereNotNull('verification_hash')
            ->with('items')
            ->orderBy('signed_at')
            ->orderBy('id')
            ->get();

        if ($invoices->isEmpty()) {
            $this->info('No signed invoices to verify.');

            return self::SUCCESS;
        }

        $this->info("Verifying {$invoices->count()} signed invoice(s)...");

        $previousHash = InvoiceSignatureService::GENESIS;
        $problems = 0;

        foreach ($invoices as $invoice) {
            $issues = [];

            // 1. The data still matches its own signature.
            if (! $signature->matches($invoice)) {
                $issues[] = 'data does not match signature (record altered)';
            }

            // 2. The chain link points at the previous invoice's signature.
            if (($invoice->previous_hash ?? InvoiceSignatureService::GENESIS) !== $previousHash) {
                $issues[] = 'broken chain link (invoice inserted/removed/reordered)';
            }

            if ($issues !== []) {
                $problems++;
                $this->error("  ✗ {$invoice->invoice_number}: ".implode('; ', $issues));
            }

            $previousHash = (string) $invoice->verification_hash;
        }

        if ($problems > 0) {
            $this->error("Integrity check FAILED: {$problems} invoice(s) with problems.");

            return self::FAILURE;
        }

        $this->info('Integrity check passed: the chain is intact.');

        return self::SUCCESS;
    }
}
