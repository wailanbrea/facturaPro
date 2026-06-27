<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $verAuditoria = Permission::firstOrCreate(
            ['slug' => 'ver_auditoria'],
            ['name' => 'Ver auditoria']
        );

        $admin = Role::firstOrCreate(
            ['slug' => 'admin'],
            [
                'name' => 'ADMIN',
                'description' => 'Acceso total al sistema.',
                'is_active' => true,
            ]
        );
        $admin->permissions()->syncWithoutDetaching([$verAuditoria->id]);

        $facturador = Role::firstOrCreate(
            ['slug' => 'facturador'],
            [
                'name'        => 'FACTURADOR',
                'description' => 'Todo como el admin excepto eliminar, anular y gestionar usuarios.',
                'is_active'   => true,
            ]
        );

        $facturadorPerms = Permission::whereIn('slug', [
            'crear_factura', 'editar_factura', 'emitir_factura', 'ver_factura',
            'descargar_pdf', 'ver_reportes', 'registrar_pagos', 'gestionar_clientes',
            'ver_informes', 'crear_informes', 'editar_informes', 'descargar_informes',
            'configurar_informes', 'ver_calendario', 'gestionar_citas',
        ])->pluck('id');

        $facturador->permissions()->sync($facturadorPerms);

        $operador = Role::firstOrCreate(
            ['slug' => 'operador'],
            [
                'name'        => 'OPERADOR',
                'description' => 'Solo acceso al calendario: ver, crear, editar y eliminar citas.',
                'is_active'   => true,
            ]
        );

        $operadorPerms = Permission::whereIn('slug', [
            'ver_calendario', 'gestionar_citas',
        ])->pluck('id');

        $operador->permissions()->sync($operadorPerms);
    }

    public function down(): void
    {
        foreach (['facturador', 'operador'] as $slug) {
            $role = Role::where('slug', $slug)->first();
            if ($role) {
                $role->permissions()->detach();
                $role->delete();
            }
        }

        Permission::where('slug', 'ver_auditoria')->delete();
    }
};
