<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $profiles = DB::table('fiscal_profiles')->orderBy('id')->get(['id', 'name']);
        $documentTypes = ['invoice', 'quotation'];

        foreach ($profiles as $profile) {
            foreach ($documentTypes as $documentType) {
                $template = DB::table('invoice_number_settings')
                    ->whereNull('fiscal_profile_id')
                    ->whereNull('user_id')
                    ->where('document_type', $documentType)
                    ->first();

                $existing = DB::table('invoice_number_settings')
                    ->where('fiscal_profile_id', $profile->id)
                    ->whereNull('user_id')
                    ->where('document_type', $documentType)
                    ->first();

                $nextNumber = max(1, (int) DB::table('invoice_number_settings')
                    ->where('fiscal_profile_id', $profile->id)
                    ->where('document_type', $documentType)
                    ->max('next_number'));

                $serie = $this->serieForProfile((string) $profile->name, (int) $profile->id, $documentType);

                if ($existing) {
                    DB::table('invoice_number_settings')
                        ->where('id', $existing->id)
                        ->update([
                            'user_id' => null,
                            'serie' => $existing->serie ?: $serie,
                            'next_number' => max((int) $existing->next_number, $nextNumber),
                            'updated_at' => now(),
                        ]);

                    continue;
                }

                DB::table('invoice_number_settings')->insert([
                    'fiscal_profile_id' => $profile->id,
                    'user_id' => null,
                    'document_type' => $documentType,
                    'prefix' => $template?->prefix ?? ($documentType === 'quotation' ? 'PRES-' : 'FAC-'),
                    'next_number' => $nextNumber,
                    'number_length' => $template?->number_length ?? 6,
                    'serie' => $serie,
                    'reset_yearly' => $template?->reset_yearly ?? false,
                    'reset_monthly' => $template?->reset_monthly ?? false,
                    'allow_manual_number' => false,
                    'current_year' => null,
                    'current_month' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Do not delete numbering rows on rollback; they may contain live counters.
    }

    private function serieForProfile(string $name, int $profileId, string $documentType): string
    {
        $parts = preg_split('/\s+/', trim(Str::ascii($name)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $base = '';

        foreach ($parts as $part) {
            $clean = preg_replace('/[^A-Za-z]/', '', $part);
            if ($clean !== '') {
                $base .= strtoupper($clean[0]);
            }
            if (strlen($base) >= 2) {
                break;
            }
        }

        $base = $base !== '' ? $base : 'P'.$profileId;
        $serie = $base;
        $suffix = 1;

        while (
            DB::table('invoice_number_settings')
                ->where('document_type', $documentType)
                ->where('serie', $serie)
                ->where(function ($query) use ($profileId): void {
                    $query->where('fiscal_profile_id', '!=', $profileId)
                        ->orWhereNull('fiscal_profile_id');
                })
                ->exists()
        ) {
            $serie = $base.(++$suffix);
        }

        return $serie;
    }
};
