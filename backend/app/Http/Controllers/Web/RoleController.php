<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        $roles = Role::withCount(['permissions', 'users'])->orderBy('id')->get();

        return view('roles.index', compact('roles'));
    }

    public function edit(Role $role): View
    {
        $role->load('permissions');

        $permissions = Permission::orderBy('id')->get()->groupBy(function ($p) {
            return match (true) {
                str_starts_with($p->slug, 'ver_factura'),
                str_starts_with($p->slug, 'crear_factura'),
                str_starts_with($p->slug, 'editar_factura'),
                str_starts_with($p->slug, 'emitir_factura'),
                str_starts_with($p->slug, 'anular_factura'),
                str_starts_with($p->slug, 'descargar_pdf'),
                str_starts_with($p->slug, 'registrar_pagos') => 'Facturas',

                str_starts_with($p->slug, 'ver_informes'),
                str_starts_with($p->slug, 'crear_informes'),
                str_starts_with($p->slug, 'editar_informes'),
                str_starts_with($p->slug, 'eliminar_informes'),
                str_starts_with($p->slug, 'descargar_informes'),
                str_starts_with($p->slug, 'configurar_informes') => 'Informes tecnicos',

                str_starts_with($p->slug, 'gestionar_clientes') => 'Clientes',

                str_starts_with($p->slug, 'ver_calendario'),
                str_starts_with($p->slug, 'gestionar_citas') => 'Calendario',

                str_starts_with($p->slug, 'ver_reportes') => 'Reportes',

                default => 'Sistema',
            };
        });

        $assigned = $role->permissions->pluck('id')->flip();

        return view('roles.edit', compact('role', 'permissions', 'assigned'));
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $data = $request->validate([
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['integer', Rule::exists('permissions', 'id')],
        ]);

        $ids = collect($data['permissions'] ?? [])
            ->map(static fn (int|string $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $role->permissions()->sync($ids);

        return back()->with('status', 'Permisos del rol "'.$role->name.'" actualizados correctamente.');
    }
}
