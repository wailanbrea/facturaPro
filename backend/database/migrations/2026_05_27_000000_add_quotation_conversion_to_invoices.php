<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->foreignId('converted_to_invoice_id')
                ->nullable()
                ->after('document_type')
                ->constrained('invoices')
                ->nullOnDelete();
            $table->foreignId('source_quotation_id')
                ->nullable()
                ->after('converted_to_invoice_id')
                ->constrained('invoices')
                ->nullOnDelete();
            $table->timestamp('converted_at')->nullable()->after('customer_accepted_at');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('converted_to_invoice_id');
            $table->dropConstrainedForeignId('source_quotation_id');
            $table->dropColumn('converted_at');
        });
    }
};
