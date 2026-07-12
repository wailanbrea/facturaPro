<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invoice_number_settings')) {
            DB::table('invoice_number_settings')->update(['allow_manual_number' => false]);
        }

        if (Schema::hasTable('report_settings')) {
            DB::table('report_settings')->update(['allow_manual_number' => false]);
        }
    }

    public function down(): void
    {
        // The manual option was removed intentionally; rollback does not re-enable it.
    }
};
