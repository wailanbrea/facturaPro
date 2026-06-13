<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            DB::table('invoices')
                ->where('document_type', 'quotation')
                ->update([
                    'amount_received' => 0,
                    'balance_due' => DB::raw('total'),
                    'updated_at' => now(),
                ]);

            DB::table('invoices')
                ->where('document_type', 'quotation')
                ->whereNotNull('converted_to_invoice_id')
                ->update([
                    'status' => 'converted',
                    'updated_at' => now(),
                ]);

            DB::table('invoices')
                ->where('document_type', 'quotation')
                ->whereIn('status', ['paid', 'partially_paid', 'overdue'])
                ->update([
                    'status' => 'issued',
                    'updated_at' => now(),
                ]);
        });
    }

    public function down(): void
    {
        // No-op: the previous values represented invalid payment state on quotations.
    }
};
