<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            // Human-readable code printed on the document for manual verification.
            $table->string('verification_code', 32)->nullable()->after('pdf_path')->index();
            // Full HMAC-SHA256 signature over the invoice's canonical fields.
            $table->string('verification_hash', 64)->nullable()->after('verification_code');
            // Signature of the previous issued invoice (tamper-evident chain).
            $table->string('previous_hash', 64)->nullable()->after('verification_hash');
            // Moment the invoice was sealed (entered the chain).
            $table->timestamp('signed_at')->nullable()->after('previous_hash');
            // SHA-256 of the generated PDF bytes (file-level integrity).
            $table->string('pdf_sha256', 64)->nullable()->after('signed_at');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
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
