<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table): void {
            $table->foreignId('fiscal_profile_id')
                ->nullable()
                ->after('account_type')
                ->constrained('fiscal_profiles')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('fiscal_profile_id');
        });
    }
};
