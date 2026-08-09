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
            $table->string('client_email')->nullable()->after('client_city');
            $table->string('client_phone')->nullable()->after('client_email');
        });

        // Hasta ahora el PDF leia el correo y el telefono en vivo de la ficha
        // del cliente, asi que editar un cliente cambiaba documentos ya
        // emitidos. Congelamos el valor actual como snapshot.
        DB::table('invoices')
            ->whereNotNull('client_id')
            ->orderBy('id')
            ->chunkById(200, function ($invoices): void {
                foreach ($invoices as $invoice) {
                    $client = DB::table('clients')
                        ->where('id', $invoice->client_id)
                        ->first(['email', 'phone']);

                    if ($client === null) {
                        continue;
                    }

                    $update = array_filter([
                        'client_email' => $client->email ?: null,
                        'client_phone' => $client->phone ?: null,
                    ]);

                    if ($update !== []) {
                        DB::table('invoices')->where('id', $invoice->id)->update($update);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn(['client_email', 'client_phone']);
        });
    }
};
