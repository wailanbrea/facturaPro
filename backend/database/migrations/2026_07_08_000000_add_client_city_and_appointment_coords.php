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
            $table->string('client_city')->nullable()->after('client_address');
        });

        Schema::table('appointments', function (Blueprint $table): void {
            $table->decimal('location_lat', 10, 7)->nullable()->after('location');
            $table->decimal('location_lng', 10, 7)->nullable()->after('location_lat');
        });

        // Backfill the client city snapshot from the linked client record.
        DB::table('invoices')
            ->whereNull('client_city')
            ->whereNotNull('client_id')
            ->orderBy('id')
            ->chunkById(200, function ($invoices): void {
                foreach ($invoices as $invoice) {
                    $city = DB::table('clients')->where('id', $invoice->client_id)->value('city');

                    if ($city !== null && $city !== '') {
                        DB::table('invoices')->where('id', $invoice->id)->update(['client_city' => $city]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn('client_city');
        });

        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropColumn(['location_lat', 'location_lng']);
        });
    }
};
