<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $groups = DB::table('invoice_number_settings')
                ->whereNotNull('fiscal_profile_id')
                ->select('fiscal_profile_id', 'document_type')
                ->groupBy('fiscal_profile_id', 'document_type')
                ->get();

            foreach ($groups as $group) {
                $settings = DB::table('invoice_number_settings')
                    ->where('fiscal_profile_id', $group->fiscal_profile_id)
                    ->where('document_type', $group->document_type)
                    ->orderByRaw('CASE WHEN user_id IS NULL AND logo_path IS NULL THEN 0 ELSE 1 END')
                    ->orderBy('id')
                    ->get();

                $primary = $settings->first();
                if ($primary === null) {
                    continue;
                }

                DB::table('invoice_number_settings')
                    ->where('id', $primary->id)
                    ->update([
                        'next_number' => max(1, (int) $settings->max('next_number')),
                        'user_id' => null,
                        'logo_path' => null,
                        'updated_at' => now(),
                    ]);

                DB::table('invoice_number_settings')
                    ->whereIn('id', $settings->pluck('id')->reject(fn (int $id): bool => $id === $primary->id))
                    ->delete();
            }
        });

        Schema::table('invoice_number_settings', function (Blueprint $table): void {
            $table->index('fiscal_profile_id', 'invoice_number_settings_fp_profile_tmp_idx');
        });

        Schema::table('invoice_number_settings', function (Blueprint $table): void {
            $table->dropUnique('invoice_number_settings_profile_user_logo_type_unique');
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn('logo_path');
            $table->unique(['fiscal_profile_id', 'document_type'], 'invoice_number_settings_profile_type_unique');
        });

        Schema::table('invoice_number_settings', function (Blueprint $table): void {
            $table->dropIndex('invoice_number_settings_fp_profile_tmp_idx');
        });
    }

    public function down(): void
    {
        // Restoring per-user or per-logo sequences would invent configuration and counters.
    }
};
