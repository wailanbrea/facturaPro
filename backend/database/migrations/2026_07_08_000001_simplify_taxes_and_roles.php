<?php

use App\Models\Role;
use App\Models\Setting;
use App\Models\Tax;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Impuestos: solo quedan activas las opciones "con IVA" y "sin IVA".
        Tax::query()->where('name', 'Exento 0%')->update(['name' => 'Sin IVA 0%']);
        Tax::query()->whereIn('name', ['ITBIS 18%', 'Tax 7%'])->update(['is_default' => false, 'is_active' => false]);

        // Roles vigentes: ADMIN, FACTURADOR y CALENDARIO (operador).
        Role::query()->where('slug', 'operador')->update(['name' => 'CALENDARIO', 'description' => 'Solo acceso al calendario: ver, crear, editar y eliminar citas.']);
        Role::query()->whereIn('slug', ['vendedor', 'tecnico', 'lectura'])->update(['is_active' => false]);

        // Campos del formulario de factura bloqueados por defecto para usuarios sin
        // permiso de configuracion (panel administrativo de bloqueo).
        Setting::query()->firstOrCreate(
            ['key' => 'invoice.locked_fields'],
            [
                'group' => 'invoices',
                'value' => ['fields' => ['conformity_text', 'legal_text']],
                'description' => 'Campos del formulario de factura bloqueados para usuarios sin permiso de configuracion.',
            ],
        );
    }

    public function down(): void
    {
        Tax::query()->where('name', 'Sin IVA 0%')->update(['name' => 'Exento 0%']);
        Tax::query()->whereIn('name', ['ITBIS 18%', 'Tax 7%'])->update(['is_active' => true]);

        Role::query()->where('slug', 'operador')->update(['name' => 'OPERADOR']);
        Role::query()->whereIn('slug', ['vendedor', 'tecnico', 'lectura'])->update(['is_active' => true]);

        Setting::query()->where('key', 'invoice.locked_fields')->delete();
    }
};
