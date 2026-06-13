<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('section_1_default_title')->default('Diagnóstico de la Avería');
            $table->string('section_2_default_title')->default('Acciones Realizadas');
            $table->string('section_3_default_title')->default('Análisis de Combustión Resultados Post-Reparación');
            $table->string('section_4_default_title')->default('Conclusión Técnica');
            $table->text('intro_text')->nullable();
            $table->text('final_text')->nullable();
            $table->string('report_prefix', 20)->default('INF-');
            $table->unsignedInteger('next_report_number')->default(1);
            $table->unsignedTinyInteger('number_length')->default(6);
            $table->boolean('allow_manual_number')->default(false);
            $table->timestamps();
        });

        Schema::create('technical_reports', function (Blueprint $table): void {
            $table->id();
            $table->string('report_number')->unique();
            $table->date('report_date');
            $table->foreignId('fiscal_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->string('seller_name');
            $table->string('seller_tax_id')->nullable();
            $table->string('seller_address')->nullable();
            $table->string('seller_city')->nullable();
            $table->string('seller_logo_path')->nullable();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('recipient_name');
            $table->string('recipient_tax_id')->nullable();
            $table->text('recipient_address');
            $table->string('section_1_title');
            $table->longText('section_1_content')->nullable();
            $table->string('section_2_title');
            $table->longText('section_2_content')->nullable();
            $table->string('section_3_title');
            $table->longText('section_3_content')->nullable();
            $table->string('section_4_title');
            $table->longText('section_4_content')->nullable();
            $table->text('intro_text')->nullable();
            $table->text('final_text')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 30)->default('draft');
            $table->string('pdf_path')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'report_date']);
            $table->index('recipient_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technical_reports');
        Schema::dropIfExists('report_settings');
    }
};
