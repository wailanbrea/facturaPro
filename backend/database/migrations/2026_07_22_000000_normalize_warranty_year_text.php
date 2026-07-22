<?php

use App\Models\Warranty;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Warranty::query()->orderBy('id')->eachById(function (Warranty $warranty): void {
            $changes = [];

            foreach (['title', 'description', 'full_text'] as $field) {
                $value = $warranty->{$field};

                if (! is_string($value)) {
                    continue;
                }

                $normalized = preg_replace_callback(
                    '/\bANO(S)?\b/ui',
                    static function (array $matches): string {
                        $word = $matches[0];
                        $replacement = isset($matches[1]) ? 'años' : 'año';

                        if ($word === strtoupper($word)) {
                            return isset($matches[1]) ? 'AÑOS' : 'AÑO';
                        }

                        return $word === ucfirst(strtolower($word))
                            ? ucfirst($replacement)
                            : $replacement;
                    },
                    $value,
                );

                if ($normalized !== null && $normalized !== $value) {
                    $changes[$field] = $normalized;
                }
            }

            if ($changes !== []) {
                $warranty->update($changes);
            }
        });
    }

    public function down(): void
    {
        // The corrected spelling must not be reverted.
    }
};
