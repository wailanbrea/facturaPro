<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('technical_reports', function (Blueprint $table): void {
            $table->string('verification_code', 32)->nullable()->after('pdf_path')->index();
            $table->string('verification_hash', 64)->nullable()->after('verification_code');
            $table->string('previous_hash', 64)->nullable()->after('verification_hash');
            $table->timestamp('signed_at')->nullable()->after('previous_hash');
            $table->string('pdf_sha256', 64)->nullable()->after('signed_at');
        });
    }

    public function down(): void
    {
        Schema::table('technical_reports', function (Blueprint $table): void {
            $table->dropColumn([
                'verification_code',
                'verification_hash',
                'previous_hash',
                'signed_at',
                'pdf_sha256',
            ]);
        });
    }
};
