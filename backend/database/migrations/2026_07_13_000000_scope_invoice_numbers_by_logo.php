<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_number_settings', function (Blueprint $table): void {
            $table->index('fiscal_profile_id', 'invoice_number_settings_fp_logo_tmp_idx');
        });

        Schema::table('invoice_number_settings', function (Blueprint $table): void {
            $table->dropUnique('invoice_number_settings_profile_user_type_unique');

            $table->string('logo_path')
                ->nullable()
                ->after('user_id');

            // Sequence is unique per company x logo x invoicing user x document type.
            // logo_path = null keeps the existing profile-level/default sequence.
            $table->unique(
                ['fiscal_profile_id', 'user_id', 'logo_path', 'document_type'],
                'invoice_number_settings_profile_user_logo_type_unique',
            );
        });

        Schema::table('invoice_number_settings', function (Blueprint $table): void {
            $table->dropIndex('invoice_number_settings_fp_logo_tmp_idx');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_number_settings', function (Blueprint $table): void {
            $table->index('fiscal_profile_id', 'invoice_number_settings_fp_logo_tmp_idx');
        });

        Schema::table('invoice_number_settings', function (Blueprint $table): void {
            $table->dropUnique('invoice_number_settings_profile_user_logo_type_unique');
            $table->dropColumn('logo_path');
            $table->unique(
                ['fiscal_profile_id', 'user_id', 'document_type'],
                'invoice_number_settings_profile_user_type_unique',
            );
        });

        Schema::table('invoice_number_settings', function (Blueprint $table): void {
            $table->dropIndex('invoice_number_settings_fp_logo_tmp_idx');
        });
    }
};
