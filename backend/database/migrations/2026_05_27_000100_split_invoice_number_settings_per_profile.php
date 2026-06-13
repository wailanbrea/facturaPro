<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_number_settings', function (Blueprint $table): void {
            $table->foreignId('fiscal_profile_id')
                ->nullable()
                ->after('id')
                ->constrained('fiscal_profiles')
                ->cascadeOnDelete();
            $table->string('document_type', 32)->default('invoice')->after('fiscal_profile_id');
        });

        DB::table('invoice_number_settings')->update(['document_type' => 'invoice']);

        Schema::table('invoice_number_settings', function (Blueprint $table): void {
            $table->unique(['fiscal_profile_id', 'document_type'], 'invoice_number_settings_profile_type_unique');
        });

        $now = now();
        $existingPairs = DB::table('invoice_number_settings')
            ->select('fiscal_profile_id', 'document_type')
            ->get()
            ->map(fn ($row) => ($row->fiscal_profile_id ?? 'null').':'.$row->document_type)
            ->all();

        $insertIfMissing = function (?int $profileId, string $documentType, string $prefix) use (&$existingPairs, $now): void {
            $key = ($profileId ?? 'null').':'.$documentType;
            if (in_array($key, $existingPairs, true)) {
                return;
            }

            DB::table('invoice_number_settings')->insert([
                'fiscal_profile_id' => $profileId,
                'document_type' => $documentType,
                'prefix' => $prefix,
                'next_number' => 1,
                'number_length' => 6,
                'serie' => null,
                'reset_yearly' => false,
                'reset_monthly' => false,
                'allow_manual_number' => false,
                'current_year' => null,
                'current_month' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $existingPairs[] = $key;
        };

        $insertIfMissing(null, 'invoice', 'FAC-');
        $insertIfMissing(null, 'quotation', 'PRES-');

        DB::table('fiscal_profiles')->orderBy('id')->get()->each(function ($profile) use ($insertIfMissing): void {
            $slug = strtoupper(preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $profile->name)));
            $slug = substr($slug, 0, 4);
            $suffix = $slug !== '' ? $slug.'-' : '';
            $insertIfMissing((int) $profile->id, 'invoice', 'FAC-'.$suffix);
            $insertIfMissing((int) $profile->id, 'quotation', 'PRES-'.$suffix);
        });
    }

    public function down(): void
    {
        Schema::table('invoice_number_settings', function (Blueprint $table): void {
            $table->dropUnique('invoice_number_settings_profile_type_unique');
            $table->dropConstrainedForeignId('fiscal_profile_id');
            $table->dropColumn('document_type');
        });
    }
};
