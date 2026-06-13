<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Services\InvoiceSignatureService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class SignExistingInvoices extends Command
{
    protected $signature = 'invoices:sign-existing {--dry-run : Show what would be signed without writing}';

    protected $description = 'Seal already-issued invoices into the authenticity chain (backfill).';

    public function handle(InvoiceSignatureService $signature): int
    {
        // Issued documents are those that already carry a number but have not
        // been sealed yet. Process in creation order so the chain is stable.
        $invoices = Invoice::query()
            ->whereNotNull('invoice_number')
            ->whereNull('verification_hash')
            ->with('items')
            ->orderBy('id')
            ->get();

        if ($invoices->isEmpty()) {
            $this->info('No unsigned issued invoices found. Nothing to do.');

            return self::SUCCESS;
        }

        $this->info("Found {$invoices->count()} invoice(s) to sign.");
        $dryRun = (bool) $this->option('dry-run');
        $signed = 0;
        $hashed = 0;

        foreach ($invoices as $invoice) {
            if ($dryRun) {
                $this->line("  [dry-run] would sign {$invoice->invoice_number}");

                continue;
            }

            $signature->signOnIssue($invoice);
            $signed++;

            if ($this->backfillPdfChecksum($invoice)) {
                $hashed++;
            }

            $this->line("  signed {$invoice->invoice_number} → {$invoice->verification_code}");
        }

        if ($dryRun) {
            $this->info('Dry run complete. No changes were written.');

            return self::SUCCESS;
        }

        $this->info("Done. Signed {$signed} invoice(s); recorded {$hashed} PDF checksum(s).");

        return self::SUCCESS;
    }

    private function backfillPdfChecksum(Invoice $invoice): bool
    {
        if (! $invoice->pdf_path || $invoice->pdf_sha256 !== null) {
            return false;
        }

        if (! Storage::disk('public')->exists($invoice->pdf_path)) {
            return false;
        }

        $invoice->forceFill([
            'pdf_sha256' => hash('sha256', Storage::disk('public')->get($invoice->pdf_path)),
        ])->save();

        return true;
    }
}
