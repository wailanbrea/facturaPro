<?php

namespace App\Console\Commands;

use App\Models\BankAccount;
use App\Models\Client;
use App\Models\Currency;
use App\Models\FiscalProfile;
use App\Models\Invoice;
use App\Models\PaymentTerm;
use App\Models\Tax;
use App\Models\User;
use App\Models\Warranty;
use App\Services\InvoiceCalculationService;
use App\Services\InvoiceSignatureService;
use App\Services\InvoiceStatusService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * DEV ONLY. Wipes all invoices and recreates exactly 5 consistent scenarios so
 * the dashboard shows every state (draft, issued/pending, partially paid, paid,
 * overdue). Totals are computed with the real InvoiceCalculationService and the
 * authenticity chain is rebuilt, so the data stays internally consistent.
 */
class DemoResetInvoices extends Command
{
    protected $signature = 'demo:reset-invoices {--force : Allow running in production}';

    protected $description = 'Reset invoice data to 5 consistent demo scenarios (dev only).';

    public function handle(
        InvoiceCalculationService $calculator,
        InvoiceStatusService $statusService,
        InvoiceSignatureService $signature,
    ): int {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('Refusing to run in production without --force.');

            return self::FAILURE;
        }

        $currency = Currency::query()->orderByDesc('is_default')->firstOrFail();
        $term = PaymentTerm::query()->orderByDesc('is_default')->firstOrFail();
        $tax = Tax::query()->where('is_active', true)->orderByDesc('is_default')->firstOrFail();
        $profile = FiscalProfile::query()->orderByDesc('is_default')->firstOrFail();
        $bank = BankAccount::query()->orderByDesc('is_default')->first();
        $warranty = Warranty::query()->orderByDesc('is_default')->first();
        $client = Client::query()->orderBy('id')->firstOrFail();
        $userId = User::query()->value('id');
        $today = CarbonImmutable::today();

        // [label, status, invoice_date, paid_fraction, overdue?, line_unit_cost]
        $scenarios = [
            ['Borrador',          InvoiceStatusService::DRAFT,          $today,                  0.0,  false, '500.00'],
            ['Emitida pendiente', InvoiceStatusService::ISSUED,         $today->subDays(3),      0.0,  false, '800.00'],
            ['Parcialmente pagada', InvoiceStatusService::PARTIALLY_PAID, $today->subDays(10),   0.5,  false, '1200.00'],
            ['Pagada',            InvoiceStatusService::PAID,           $today->subMonth(),      1.0,  false, '650.00'],
            ['Vencida',           InvoiceStatusService::OVERDUE,        $today->subDays(45),     0.0,  true,  '950.00'],
        ];

        DB::transaction(function () use (
            $scenarios, $calculator, $statusService, $signature,
            $currency, $term, $tax, $profile, $bank, $warranty, $client, $userId
        ): void {
            // Wipe existing invoices (items/payments cascade) and reset the chain.
            Invoice::query()->delete();

            foreach ($scenarios as [$label, $status, $date, $paidFraction, $overdue, $unitCost]) {
                $items = [[
                    'description' => $label.' — servicio de demostración',
                    'quantity' => '1',
                    'unit_cost' => $unitCost,
                    'tax_id' => $tax->id,
                    'tax_name' => $tax->name,
                    'tax_rate' => $tax->rate,
                ]];

                $calc = $calculator->calculate($items, '0');
                $total = $calc['total'];
                $amountReceived = bcmul($total, (string) $paidFraction, 4);
                $recalc = $calculator->calculate($items, $amountReceived);

                $isDraft = $status === InvoiceStatusService::DRAFT;
                $dueDate = $overdue
                    ? $date->addDays($term->days)            // already in the past for the overdue case
                    : $date->addDays($term->days);

                $invoice = Invoice::query()->create([
                    'document_type' => Invoice::DOCUMENT_TYPE_INVOICE,
                    'invoice_date' => $date->toDateString(),
                    'due_date' => $dueDate->toDateString(),
                    'payment_term_id' => $term->id,
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
                    'fiscal_profile_id' => $profile->id,
                    'seller_name' => $profile->name,
                    'seller_tax_id' => $profile->tax_id,
                    'seller_address' => $profile->address,
                    'seller_city' => $profile->city,
                    'bank_account_id' => $bank?->id,
                    'warranty_id' => $warranty?->id,
                    'warranty_text' => $warranty?->full_text,
                    'amount_received' => $recalc['amount_received'],
                    'subtotal' => $recalc['subtotal'],
                    'tax_total' => $recalc['tax_total'],
                    'total' => $recalc['total'],
                    'balance_due' => $recalc['balance_due'],
                    'status' => $status,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);

                foreach ($recalc['items'] as $index => $item) {
                    $invoice->items()->create([...$item, 'sort_order' => $index]);
                }

                // Back payments with real records so amount_received is consistent.
                if (bccomp($amountReceived, '0', 4) === 1) {
                    $invoice->payments()->create([
                        'payment_date' => $date->toDateString(),
                        'amount' => $amountReceived,
                        'method' => 'demo',
                        'created_by' => $userId,
                    ]);
                }

                // Issued documents get a number and enter the authenticity chain.
                if (! $isDraft) {
                    $invoice->invoice_number = sprintf('FAC-%06d', $invoice->id);
                    $invoice->save();
                    $signature->signOnIssue($invoice->load('items'));
                }
            }
        });

        $this->info('Recreated 5 demo invoices. Summary:');
        Invoice::query()->orderBy('id')->get()->each(function (Invoice $i): void {
            $this->line(sprintf(
                '  %-14s | %-13s | total %9s | recibido %9s | saldo %9s | vence %s',
                $i->invoice_number ?? '(borrador)',
                $i->status,
                $i->total,
                $i->amount_received,
                $i->balance_due,
                $i->due_date?->toDateString() ?? '-',
            ));
        });

        return self::SUCCESS;
    }
}
