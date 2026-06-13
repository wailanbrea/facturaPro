<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table): void {
            $table->string('account_type', 32)->default('official')->after('label')->index();
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->string('document_type', 32)->default('invoice')->after('invoice_number')->index();
            $table->text('conformity_text')->nullable()->after('legal_text');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn(['document_type', 'conformity_text']);
        });

        Schema::table('bank_accounts', function (Blueprint $table): void {
            $table->dropColumn('account_type');
        });
    }
};
