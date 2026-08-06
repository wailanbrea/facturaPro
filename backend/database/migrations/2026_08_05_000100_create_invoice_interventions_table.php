<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Datos tecnicos de la intervencion asociada a un documento.
     *
     * Van en tabla aparte y no en `invoices` porque son campos descriptivos
     * largos (cuatro `text`) que ningun listado necesita, y `invoices` ya
     * arrastra mas de cincuenta columnas.
     */
    public function up(): void
    {
        Schema::create('invoice_interventions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->unique()->constrained()->cascadeOnDelete();

            // Equipo objeto de la intervencion (factura).
            $table->string('equipment_type')->nullable();
            $table->string('equipment_brand')->nullable();
            $table->string('equipment_model')->nullable();
            $table->string('equipment_serial')->nullable();
            $table->string('equipment_location')->nullable();
            $table->unsignedSmallInteger('units_indoor')->nullable();
            $table->unsignedSmallInteger('units_outdoor')->nullable();

            // Bloques narrativos: los dos primeros son de factura y los dos
            // ultimos de presupuesto.
            $table->text('diagnostic_summary')->nullable();
            $table->text('technical_conclusions')->nullable();
            $table->text('service_scope')->nullable();
            // Un concepto por linea; la plantilla lo pinta como lista de checks.
            $table->text('included_items')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_interventions');
    }
};
