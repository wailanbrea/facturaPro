<?php

namespace Database\Seeders;

use App\Models\BankAccount;
use App\Models\Client;
use App\Models\Currency;
use App\Models\FiscalProfile;
use App\Models\Invoice;
use App\Models\InvoiceNumberSetting;
use App\Models\LegalText;
use App\Models\PaymentTerm;
use App\Models\Permission;
use App\Models\ReportSetting;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Tax;
use App\Models\User;
use App\Models\Warranty;
use App\Services\InvoiceCalculationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@facturapro.local'],
            [
                'name' => 'Administrador FacturaPro',
                'password' => Hash::make('FacturaPro123!'),
            ],
        );

        $permissions = collect([
            ['name' => 'Crear factura', 'slug' => 'crear_factura'],
            ['name' => 'Editar factura', 'slug' => 'editar_factura'],
            ['name' => 'Emitir factura', 'slug' => 'emitir_factura'],
            ['name' => 'Anular factura', 'slug' => 'anular_factura'],
            ['name' => 'Ver factura', 'slug' => 'ver_factura'],
            ['name' => 'Descargar PDF', 'slug' => 'descargar_pdf'],
            ['name' => 'Registrar pagos', 'slug' => 'registrar_pagos'],
            ['name' => 'Gestionar clientes', 'slug' => 'gestionar_clientes'],
            ['name' => 'Configurar sistema', 'slug' => 'configurar_sistema'],
            ['name' => 'Gestionar usuarios', 'slug' => 'gestionar_usuarios'],
            ['name' => 'Ver reportes', 'slug' => 'ver_reportes'],
            ['name' => 'Ver informes', 'slug' => 'ver_informes'],
            ['name' => 'Crear informes', 'slug' => 'crear_informes'],
            ['name' => 'Editar informes', 'slug' => 'editar_informes'],
            ['name' => 'Eliminar informes', 'slug' => 'eliminar_informes'],
            ['name' => 'Descargar informes', 'slug' => 'descargar_informes'],
            ['name' => 'Configurar informes', 'slug' => 'configurar_informes'],
            ['name' => 'Ver calendario', 'slug' => 'ver_calendario'],
            ['name' => 'Gestionar citas', 'slug' => 'gestionar_citas'],
            ['name' => 'Ver auditoria', 'slug' => 'ver_auditoria'],
        ])->mapWithKeys(fn (array $permission): array => [
            $permission['slug'] => Permission::query()->updateOrCreate(
                ['slug' => $permission['slug']],
                $permission,
            ),
        ]);

        $adminRole = Role::query()->updateOrCreate(
            ['slug' => 'admin'],
            ['name' => 'ADMIN', 'description' => 'Acceso total al sistema.', 'is_active' => true],
        );

        $facturadorRole = Role::query()->updateOrCreate(
            ['slug' => 'facturador'],
            ['name' => 'FACTURADOR', 'description' => 'Realiza facturaciones, presupuestos, informes y sus derivados.', 'is_active' => true],
        );

        $calendarRole = Role::query()->updateOrCreate(
            ['slug' => 'operador'],
            ['name' => 'CALENDARIO', 'description' => 'Solo acceso al calendario: ver, crear, editar y eliminar citas.', 'is_active' => true],
        );

        // Roles heredados que ya no se usan: quedan desactivados.
        Role::query()->whereIn('slug', ['vendedor', 'tecnico', 'lectura'])->update(['is_active' => false]);

        $adminRole->permissions()->sync($permissions->pluck('id'));
        $facturadorRole->permissions()->sync($permissions->only([
            'crear_factura',
            'editar_factura',
            'emitir_factura',
            'ver_factura',
            'descargar_pdf',
            'registrar_pagos',
            'gestionar_clientes',
            'ver_reportes',
            'ver_informes',
            'crear_informes',
            'editar_informes',
            'descargar_informes',
            'configurar_informes',
            'ver_calendario',
            'gestionar_citas',
        ])->pluck('id'));
        $calendarRole->permissions()->sync($permissions->only([
            'ver_calendario',
            'gestionar_citas',
        ])->pluck('id'));
        $admin->roles()->syncWithoutDetaching([$adminRole->id]);

        $eur = Currency::query()->updateOrCreate(
            ['code' => 'EUR'],
            [
                'name' => 'Euro',
                'symbol' => 'EUR',
                'decimal_separator' => ',',
                'thousand_separator' => '.',
                'decimal_places' => 2,
                'symbol_position' => 'after',
                'is_default' => true,
                'is_active' => true,
            ],
        );

        $usd = Currency::query()->updateOrCreate(
            ['code' => 'USD'],
            [
                'name' => 'Dollar',
                'symbol' => 'US$',
                'decimal_separator' => '.',
                'thousand_separator' => ',',
                'decimal_places' => 2,
                'symbol_position' => 'before',
                'is_default' => false,
                'is_active' => false,
            ],
        );

        $dop = Currency::query()->updateOrCreate(
            ['code' => 'DOP'],
            [
                'name' => 'Peso Dominicano',
                'symbol' => 'RD$',
                'decimal_separator' => '.',
                'thousand_separator' => ',',
                'decimal_places' => 2,
                'symbol_position' => 'before',
                'is_default' => false,
                'is_active' => false,
            ],
        );

        Currency::query()->where('code', '!=', $eur->code)->update(['is_default' => false]);

        // Solo dos opciones de impuesto activas: con IVA y sin IVA.
        // ITBIS y Tax quedan desactivados (se conservan por integridad historica).
        Tax::query()->where('name', 'Exento 0%')->update(['name' => 'Sin IVA 0%']);
        collect([
            ['name' => 'IVA 21%', 'rate' => 21.0000, 'is_default' => true, 'is_active' => true],
            ['name' => 'Sin IVA 0%', 'rate' => 0.0000, 'is_default' => false, 'is_active' => true],
            ['name' => 'ITBIS 18%', 'rate' => 18.0000, 'is_default' => false, 'is_active' => false],
            ['name' => 'Tax 7%', 'rate' => 7.0000, 'is_default' => false, 'is_active' => false],
        ])->each(fn (array $tax): Tax => Tax::query()->updateOrCreate(['name' => $tax['name']], $tax));
        Tax::query()->where('name', '!=', 'IVA 21%')->update(['is_default' => false]);
        $iva = Tax::query()->where('name', 'IVA 21%')->firstOrFail();

        collect([
            ['name' => 'AL CONTADO', 'days' => 0, 'description' => 'Pago inmediato al emitir la factura.', 'is_default' => true, 'is_active' => true],
            ['name' => 'CREDITO', 'days' => 30, 'description' => 'Pago a credito desde la fecha del documento.', 'is_default' => false, 'is_active' => true],
            ['name' => 'CREDITO 15 DIAS', 'days' => 15, 'description' => 'Pago a 15 dias desde la fecha de factura.', 'is_default' => false, 'is_active' => false],
            ['name' => 'CREDITO 30 DIAS', 'days' => 30, 'description' => 'Pago a 30 dias desde la fecha de factura.', 'is_default' => false, 'is_active' => false],
        ])->each(fn (array $term): PaymentTerm => PaymentTerm::query()->updateOrCreate(['name' => $term['name']], $term));

        collect([
            ['title' => 'GARANTIA DE 6 MESES EN PIEZAS Y SERVICIOS DEL FABRICANTE', 'duration_months' => 6, 'is_default' => true],
            ['title' => 'GARANTIA DE 1 AÑO EN PIEZAS Y SERVICIOS DEL FABRICANTE', 'duration_months' => 12, 'is_default' => false],
            ['title' => 'GARANTIA DE 3 AÑOS EN PIEZAS Y SERVICIOS DEL FABRICANTE', 'duration_months' => 36, 'is_default' => false],
        ])->each(function (array $warranty): void {
            Warranty::query()->updateOrCreate(
                ['title' => $warranty['title']],
                [
                    ...$warranty,
                    'description' => $warranty['title'],
                    'full_text' => $warranty['title'],
                    'is_active' => true,
                ],
            );
        });

        $profile = FiscalProfile::query()->updateOrCreate(
            ['name' => 'LUIS AMAURIS RODRIGUEZ GARCIA'],
            [
                'tax_id' => 'CIF.49553314K',
                'address' => 'Carrer Tinet Flomesta 50 Bajos',
                'city' => 'Barcelona',
                'phone' => null,
                'email' => null,
                'logo_path' => null,
                'is_default' => true,
                'is_active' => true,
            ],
        );
        FiscalProfile::query()->updateOrCreate(
            ['name' => 'PAMELA MISHELL AVILA CELI'],
            [
                'tax_id' => 'CIF.23814640A',
                'address' => 'Carrer Tinet Flomesta 50 Bajos',
                'city' => 'Barcelona',
                'phone' => null,
                'email' => null,
                'logo_path' => null,
                'is_default' => false,
                'is_active' => true,
            ],
        );
        FiscalProfile::query()->whereKeyNot($profile->id)->update(['is_default' => false]);

        $account = BankAccount::query()->updateOrCreate(
            ['label' => 'Oficial'],
            [
                'account_type' => 'official',
                'account_holder' => 'PAMELA MISHELL AVILA CELI',
                'bank_name' => 'SANTANDER',
                'account_number' => null,
                'iban' => 'ES03 0049 0498 7321 1075 7225',
                'swift' => null,
                'currency_id' => $eur->id,
                'is_default' => true,
                'is_active' => true,
            ],
        );
        BankAccount::query()->updateOrCreate(
            ['label' => 'No oficial'],
            [
                'account_type' => 'unofficial',
                'account_holder' => 'CARLA AVILA',
                'bank_name' => 'SANTANDER',
                'account_number' => null,
                'iban' => 'ES39 1465 0120 3417 5889 7756',
                'swift' => null,
                'currency_id' => $eur->id,
                'is_default' => false,
                'is_active' => true,
            ],
        );
        BankAccount::query()->whereKeyNot($account->id)->update(['is_default' => false]);

        $legalText = LegalText::query()->updateOrCreate(
            ['name' => 'Texto legal predeterminado'],
            [
                'legal_footer' => 'La firma, aceptacion digital o pago del servicio confirma la conformidad del trabajo realizado. La garantia cubre exclusivamente la reparacion realizada y las piezas sustituidas, excluyendo averias derivadas de manipulacion externa, mal uso o desgaste natural.',
                'warranty_text' => 'La garantia aplica segun las condiciones indicadas en esta factura.',
                'conformity_text' => 'CONFORMIDAD DEL CLIENTE',
                'client_copy_text' => 'ORIGINAL: CLIENTE',
                'seller_copy_text' => 'COPIA: VENDEDOR',
                'is_default' => true,
                'is_active' => true,
            ],
        );
        LegalText::query()->whereKeyNot($legalText->id)->update(['is_default' => false]);

        InvoiceNumberSetting::query()->updateOrCreate(
            ['fiscal_profile_id' => null, 'document_type' => 'invoice'],
            [
                'prefix' => 'FAC-',
                'next_number' => 1,
                'number_length' => 6,
                'serie' => null,
                'reset_yearly' => false,
                'reset_monthly' => false,
                'allow_manual_number' => false,
                'current_year' => null,
                'current_month' => null,
            ],
        );

        InvoiceNumberSetting::query()->updateOrCreate(
            ['fiscal_profile_id' => null, 'document_type' => 'quotation'],
            [
                'prefix' => 'PRES-',
                'next_number' => 1,
                'number_length' => 6,
                'serie' => null,
                'reset_yearly' => false,
                'reset_monthly' => false,
                'allow_manual_number' => false,
                'current_year' => null,
                'current_month' => null,
            ],
        );

        ReportSetting::query()->updateOrCreate(
            ['id' => 1],
            ReportSetting::defaults(),
        );

        Setting::query()->updateOrCreate(
            ['key' => 'tax.display_mode'],
            ['group' => 'taxes', 'value' => ['mode' => 'show'], 'description' => 'Controla si los impuestos se muestran en factura.'],
        );

        Setting::query()->updateOrCreate(
            ['key' => 'tax.prices_include_tax'],
            ['group' => 'taxes', 'value' => ['enabled' => false], 'description' => 'Define si los precios capturados incluyen impuestos.'],
        );

        Setting::query()->updateOrCreate(
            ['key' => 'invoice.default_fiscal_profile_id'],
            ['group' => 'invoices', 'value' => ['id' => $profile->id], 'description' => 'Perfil fiscal predeterminado.'],
        );

        Setting::query()->updateOrCreate(
            ['key' => 'invoice.default_bank_account_id'],
            ['group' => 'invoices', 'value' => ['id' => $account->id], 'description' => 'Cuenta bancaria predeterminada.'],
        );

        Setting::query()->firstOrCreate(
            ['key' => 'invoice.locked_fields'],
            [
                'group' => 'invoices',
                'value' => ['fields' => ['conformity_text', 'legal_text']],
                'description' => 'Campos del formulario de factura bloqueados para usuarios sin permiso de configuracion.',
            ],
        );

        if (! app()->environment('testing')) {
            $this->seedDemoInvoices($admin, $eur, $iva, $profile, $account, $legalText);
        }
    }

    private function seedDemoInvoices(
        User $admin,
        Currency $currency,
        Tax $tax,
        FiscalProfile $profile,
        BankAccount $account,
        LegalText $legalText,
    ): void {
        $calculator = app(InvoiceCalculationService::class);
        $terms = PaymentTerm::query()->whereIn('name', ['AL CONTADO', 'CREDITO'])
            ->get()
            ->keyBy('name');
        $warranty = Warranty::query()->where('is_default', true)->first();

        $definitions = [
            [
                'number' => 'FAC-DEMO-0001',
                'status' => 'issued',
                'invoice_date' => '2026-05-20',
                'due_date' => '2026-06-19',
                'term' => 'CREDITO',
                'amount_received' => '0',
                'prepared_by' => 'Laura Medina',
                'received_by' => 'Carlos Vega',
                'client' => [
                    'name' => 'Taller Europa Norte S.L.',
                    'tax_id' => 'B12345678',
                    'address' => 'Calle Mayor 18',
                    'city' => 'Madrid',
                    'phone' => '+34 910 100 200',
                    'email' => 'administracion@tallereuropanorte.es',
                ],
                'items' => [
                    ['description' => 'Servicio de mantenimiento preventivo', 'quantity' => '2', 'unit_cost' => '145.00'],
                    ['description' => 'Sustitucion de sensor de diagnostico', 'quantity' => '1', 'unit_cost' => '320.00'],
                ],
            ],
            [
                'number' => 'FAC-DEMO-0002',
                'status' => 'partially_paid',
                'invoice_date' => '2026-05-18',
                'due_date' => '2026-06-02',
                'term' => 'CREDITO',
                'amount_received' => '300.00',
                'prepared_by' => 'Laura Medina',
                'received_by' => 'Marta Ruiz',
                'client' => [
                    'name' => 'Clinica Dental Prado S.L.',
                    'tax_id' => 'B87654321',
                    'address' => 'Paseo del Prado 44',
                    'city' => 'Madrid',
                    'phone' => '+34 910 300 400',
                    'email' => 'compras@clinicadentalprado.es',
                ],
                'items' => [
                    ['description' => 'Instalacion de estacion de recepcion', 'quantity' => '1', 'unit_cost' => '680.00'],
                    ['description' => 'Configuracion de software de facturacion', 'quantity' => '3', 'unit_cost' => '95.00'],
                ],
            ],
            [
                'number' => 'FAC-DEMO-0003',
                'status' => 'paid',
                'invoice_date' => '2026-05-12',
                'due_date' => '2026-05-12',
                'term' => 'AL CONTADO',
                'amount_received' => 'full',
                'prepared_by' => 'Pedro Santos',
                'received_by' => 'Elena Mora',
                'client' => [
                    'name' => 'Hotel Costa Azul S.A.',
                    'tax_id' => 'A11223344',
                    'address' => 'Avenida del Mediterraneo 9',
                    'city' => 'Valencia',
                    'phone' => '+34 960 500 600',
                    'email' => 'contabilidad@hotelcostaazul.es',
                ],
                'items' => [
                    ['description' => 'Licencia anual FacturaPro', 'quantity' => '1', 'unit_cost' => '1200.00'],
                    ['description' => 'Soporte remoto premium', 'quantity' => '6', 'unit_cost' => '85.00'],
                ],
            ],
            [
                'number' => 'FAC-DEMO-0004',
                'status' => 'overdue',
                'invoice_date' => '2026-04-10',
                'due_date' => '2026-04-25',
                'term' => 'CREDITO',
                'amount_received' => '0',
                'prepared_by' => 'Pedro Santos',
                'received_by' => 'Sofia Marin',
                'client' => [
                    'name' => 'Consultoria Nova Iberia S.L.',
                    'tax_id' => 'B44556677',
                    'address' => 'Rambla Catalunya 77',
                    'city' => 'Barcelona',
                    'phone' => '+34 930 700 800',
                    'email' => 'finanzas@novaiberia.es',
                ],
                'items' => [
                    ['description' => 'Auditoria de procesos de facturacion', 'quantity' => '1', 'unit_cost' => '950.00'],
                    ['description' => 'Migracion de catalogos y clientes', 'quantity' => '1', 'unit_cost' => '410.00'],
                    ['description' => 'Sesion de capacitacion administrativa', 'quantity' => '2', 'unit_cost' => '120.00'],
                ],
            ],
        ];

        foreach ($definitions as $definition) {
            $client = Client::query()->updateOrCreate(
                ['email' => $definition['client']['email']],
                [
                    ...$definition['client'],
                    'is_active' => true,
                ],
            );

            $items = array_map(fn (array $item): array => [
                ...$item,
                'tax_id' => $tax->id,
                'tax_name' => $tax->name,
                'tax_rate' => (string) $tax->rate,
            ], $definition['items']);

            $initialCalculation = $calculator->calculate($items, '0');
            $amountReceived = $definition['amount_received'] === 'full'
                ? $initialCalculation['total']
                : $definition['amount_received'];
            $calculation = $calculator->calculate($items, $amountReceived);
            $paymentTerm = $terms->get($definition['term']) ?? $terms->first();

            $invoice = Invoice::query()->updateOrCreate(
                ['invoice_number' => $definition['number']],
                [
                    'document_type' => 'invoice',
                    'invoice_date' => $definition['invoice_date'],
                    'due_date' => $definition['due_date'],
                    'payment_term_id' => $paymentTerm?->id,
                    'client_id' => $client->id,
                    'client_name' => $client->name,
                    'client_tax_id' => $client->tax_id,
                    'client_address' => $client->address,
                    'client_city' => $client->city,
                    'currency_id' => $currency->id,
                    'currency_code' => $currency->code,
                    'currency_symbol' => $currency->symbol,
                    'currency_decimal_separator' => $currency->decimal_separator,
                    'currency_thousand_separator' => $currency->thousand_separator,
                    'currency_decimal_places' => $currency->decimal_places,
                    'currency_symbol_position' => $currency->symbol_position,
                    'fiscal_profile_id' => $profile->id,
                    'seller_name' => $profile->name,
                    'seller_tax_id' => $profile->tax_id,
                    'seller_address' => $profile->address,
                    'seller_city' => $profile->city,
                    'bank_account_id' => $account->id,
                    'warranty_id' => $warranty?->id,
                    'warranty_text' => $legalText->warranty_text,
                    'legal_text' => $legalText->legal_footer,
                    'conformity_text' => $legalText->conformity_text,
                    'observations' => 'Factura demo coherente para pruebas funcionales en EUR.',
                    'amount_received' => $calculation['amount_received'],
                    'subtotal' => $calculation['subtotal'],
                    'tax_total' => $calculation['tax_total'],
                    'total' => $calculation['total'],
                    'balance_due' => $calculation['balance_due'],
                    'status' => $definition['status'],
                    'prepared_by' => $definition['prepared_by'],
                    'received_by' => $definition['received_by'],
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ],
            );

            $invoice->items()->delete();
            foreach ($calculation['items'] as $index => $item) {
                $invoice->items()->create([
                    ...$item,
                    'sort_order' => $index,
                ]);
            }

            $invoice->payments()->delete();
            if ((float) $calculation['amount_received'] > 0) {
                $invoice->payments()->create([
                    'payment_date' => $definition['invoice_date'],
                    'amount' => $calculation['amount_received'],
                    'method' => 'Transferencia bancaria',
                    'reference' => $definition['number'].'-PAGO',
                    'notes' => 'Pago demo generado por seeder.',
                    'created_by' => $admin->id,
                ]);
            }
        }
    }
}
