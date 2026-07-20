<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technical_report_number_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fiscal_profile_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('prefix', 20)->default('INF-');
            $table->string('serie', 20);
            $table->unsignedInteger('next_number')->default(1);
            $table->unsignedTinyInteger('number_length')->default(6);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technical_report_number_settings');
    }
};
