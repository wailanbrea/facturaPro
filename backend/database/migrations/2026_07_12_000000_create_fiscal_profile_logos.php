<?php

use App\Models\FiscalProfile;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_profile_logos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fiscal_profile_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('label')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['fiscal_profile_id', 'path']);
        });

        FiscalProfile::query()
            ->whereNotNull('logo_path')
            ->where('logo_path', '!=', '')
            ->get()
            ->each(function (FiscalProfile $profile): void {
                $profile->logos()->firstOrCreate(
                    ['path' => $profile->logo_path],
                    ['label' => basename($profile->logo_path), 'is_default' => true],
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_profile_logos');
    }
};
