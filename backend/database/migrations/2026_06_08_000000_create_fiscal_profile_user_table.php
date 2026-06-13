<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_profile_user', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fiscal_profile_id')->constrained()->cascadeOnDelete();
            $table->primary(['user_id', 'fiscal_profile_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_profile_user');
    }
};
