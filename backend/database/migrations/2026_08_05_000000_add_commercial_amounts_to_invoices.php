<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            // Importes comerciales. Participan en el calculo y, a partir de la
            // firma v2, tambien en la cadena de autenticidad.
            $table->decimal('discount_percent', 5, 2)->default(0)->after('subtotal');
            $table->decimal('discount_total', 15, 4)->default(0)->after('discount_percent');
            $table->decimal('travel_amount', 15, 4)->default(0)->after('discount_total');

            // Base imponible = subtotal - descuento + desplazamiento. Se persiste
            // porque la plantilla PDF tiene prohibido calcular importes.
            $table->decimal('taxable_base', 15, 4)->nullable()->after('travel_amount');

            // Cabecera comercial, comun a factura y presupuesto.
            $table->string('technician_name')->nullable()->after('received_by');
            $table->string('work_reference')->nullable()->after('technician_name');
            $table->string('service_location')->nullable()->after('work_reference');

            // Version del string canonico usado al firmar. NULL = v1 (documentos
            // anteriores a esta migracion), que deben seguir validando intactos.
            $table->string('signature_version', 8)->nullable()->after('previous_hash');
        });

        // Los documentos existentes no tienen descuento ni desplazamiento, asi que
        // su base imponible es exactamente el subtotal ya almacenado. Este backfill
        // NO toca subtotal/tax_total/total: hacerlo invalidaria las firmas v1.
        DB::table('invoices')->whereNull('taxable_base')->update([
            'taxable_base' => DB::raw('subtotal'),
        ]);
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn([
                'discount_percent',
                'discount_total',
                'travel_amount',
                'taxable_base',
                'technician_name',
                'work_reference',
                'service_location',
                'signature_version',
            ]);
        });
    }
};
