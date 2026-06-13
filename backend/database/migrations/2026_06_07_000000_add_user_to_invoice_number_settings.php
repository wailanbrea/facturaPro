<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The fiscal_profile_id foreign key is backed by the composite unique index,
        // so MySQL refuses to drop that index directly. Add a temporary standalone
        // index to back the FK while we swap the unique constraint.
        Schema::table('invoice_number_settings', function (Blueprint $table): void {
            $table->index('fiscal_profile_id', 'invoice_number_settings_fp_tmp_idx');
        });

        Schema::table('invoice_number_settings', function (Blueprint $table): void {
            $table->dropUnique('invoice_number_settings_profile_type_unique');

            $table->foreignId('user_id')
                ->nullable()
                ->after('fiscal_profile_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Sequence is unique per company (fiscal profile) x invoicing user x document type.
            // Existing rows keep user_id = null and act as the per-company fallback/template.
            $table->unique(
                ['fiscal_profile_id', 'user_id', 'document_type'],
                'invoice_number_settings_profile_user_type_unique',
            );
        });

        // The new composite unique now backs the FK (fiscal_profile_id is its first column).
        Schema::table('invoice_number_settings', function (Blueprint $table): void {
            $table->dropIndex('invoice_number_settings_fp_tmp_idx');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_number_settings', function (Blueprint $table): void {
            $table->index('fiscal_profile_id', 'invoice_number_settings_fp_tmp_idx');
        });

        Schema::table('invoice_number_settings', function (Blueprint $table): void {
            $table->dropUnique('invoice_number_settings_profile_user_type_unique');
            $table->dropConstrainedForeignId('user_id');
            $table->unique(
                ['fiscal_profile_id', 'document_type'],
                'invoice_number_settings_profile_type_unique',
            );
        });

        Schema::table('invoice_number_settings', function (Blueprint $table): void {
            $table->dropIndex('invoice_number_settings_fp_tmp_idx');
        });
    }
};
