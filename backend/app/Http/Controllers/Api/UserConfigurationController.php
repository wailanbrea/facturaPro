<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\SettingsController as WebSettingsController;
use App\Models\BankAccount;
use App\Models\Currency;
use App\Models\DeviceToken;
use App\Models\LegalText;
use App\Models\PaymentTerm;
use App\Models\Permission;
use App\Models\ReportSetting;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Tax;
use App\Models\User;
use App\Models\Warranty;
use App\Services\InvoiceNumberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserConfigurationController extends Controller
{
    public function __construct(
        private readonly InvoiceNumberService $invoiceNumberService,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->load([
            'roles' => fn ($q) => $q->where('roles.is_active', true)->with('permissions:id,name,slug'),
            'fiscalProfiles' => fn ($q) => $q->where('fiscal_profiles.is_active', true),
        ]);

        $grantedSlugs = $user->grantedPermissions();
        $permissionsByGroup = $this->groupUserPermissions($grantedSlugs);

        $hasInvoicingAccess = $user->hasAnyPermission([
            'ver_factura',
            'crear_factura',
            'editar_factura',
            'emitir_factura',
            'configurar_sistema',
        ]);
        $hasReportsAccess = $user->hasAnyPermission([
            'ver_informes',
            'crear_informes',
            'editar_informes',
            'configurar_informes',
            'configurar_sistema',
        ]);
        $hasCalendarAccess = $user->hasAnyPermission([
            'ver_calendario',
            'gestionar_citas',
        ]);
        $canManageUsers = $user->hasPermission('gestionar_usuarios');
        $canConfigureSystem = $user->hasPermission('configurar_sistema');

        $availableProfiles = ($hasInvoicingAccess || $hasReportsAccess)
            ? $user->availableFiscalProfiles()->load('logos')
            : collect();

        $explicitProfileIds = $user->fiscalProfiles->pluck('id')->all();

        $data = [
            'current_user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->map(static fn (Role $role): array => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug,
                    'description' => $role->description ?? '',
                ])->values()->all(),
                'permission_groups' => $permissionsByGroup,
                'permissions_count' => count($grantedSlugs),
                'has_all_companies_access' => empty($explicitProfileIds) && ($hasInvoicingAccess || $hasReportsAccess),
                'assigned_companies' => $availableProfiles->map(fn ($profile): array => [
                    'id' => $profile->id,
                    'name' => $profile->name,
                    'tax_id' => $profile->tax_id ?? '',
                    'city' => $profile->city ?? '',
                    'is_default' => (bool) $profile->is_default,
                    'logos_count' => $profile->logos->count(),
                    'next_invoice_number' => $hasInvoicingAccess
                        ? $this->invoiceNumberService->preview($profile->id, 'invoice', userId: $user->id)
                        : null,
                    'next_quotation_number' => $hasInvoicingAccess
                        ? $this->invoiceNumberService->preview($profile->id, 'quotation', userId: $user->id)
                        : null,
                ])->values()->all(),
                'push_devices_count' => DeviceToken::query()->where('user_id', $user->id)->count(),
            ],
            'invoicing_config' => $hasInvoicingAccess ? $this->buildInvoicingConfig($user, $canConfigureSystem) : null,
            'technical_reports_config' => $hasReportsAccess ? $this->buildTechnicalReportsConfig() : null,
            'calendar_config' => $hasCalendarAccess ? [
                'can_view_calendar' => $user->hasPermission('ver_calendario'),
                'can_manage_appointments' => $user->hasPermission('gestionar_citas'),
                'registered_push_devices' => DeviceToken::query()->where('user_id', $user->id)->count(),
            ] : null,
            'system_users_config' => $canManageUsers ? $this->buildSystemUsersAndRoles() : null,
        ];

        return response()->json(['data' => $data]);
    }

    /**
     * @param  array<int, string>  $grantedSlugs
     * @return array<int, array{group: string, permissions: array<int, array{slug: string, name: string}>}>
     */
    private function groupUserPermissions(array $grantedSlugs): array
    {
        if ($grantedSlugs === []) {
            return [];
        }

        $permissions = Permission::query()
            ->whereIn('slug', $grantedSlugs)
            ->orderBy('id')
            ->get(['id', 'name', 'slug']);

        $grouped = $permissions->groupBy(function (Permission $p): string {
            return match (true) {
                in_array($p->slug, [
                    'ver_factura',
                    'crear_factura',
                    'editar_factura',
                    'emitir_factura',
                    'anular_factura',
                    'descargar_pdf',
                    'registrar_pagos',
                ], true) => 'Facturas y presupuestos',
                in_array($p->slug, [
                    'ver_informes',
                    'crear_informes',
                    'editar_informes',
                    'eliminar_informes',
                    'descargar_informes',
                    'configurar_informes',
                ], true) => 'Informes técnicos',
                $p->slug === 'gestionar_clientes' => 'Clientes',
                in_array($p->slug, ['ver_calendario', 'gestionar_citas'], true) => 'Calendario y citas',
                $p->slug === 'ver_reportes' => 'Reportes financieros',
                default => 'Administración y sistema',
            };
        });

        return $grouped->map(static fn ($items, string $group): array => [
            'group' => $group,
            'permissions' => $items->map(static fn (Permission $item): array => [
                'slug' => $item->slug,
                'name' => $item->name,
            ])->values()->all(),
        ])->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildInvoicingConfig(User $user, bool $canConfigureSystem): array
    {
        $lockedSetting = Setting::query()->where('key', 'invoice.locked_fields')->value('value');
        $configuredLockedSlugs = array_values((array) ($lockedSetting['fields'] ?? ['conformity_text', 'legal_text']));
        $canEditInvoiceFields = $user->hasAnyPermission(['crear_factura', 'editar_factura']);
        $effectiveLockedSlugs = $canEditInvoiceFields ? [] : $configuredLockedSlugs;

        $lockLabels = WebSettingsController::LOCKABLE_FIELDS;

        return [
            'currencies' => Currency::query()
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->orderBy('code')
                ->get()
                ->map(static fn (Currency $c): array => [
                    'code' => $c->code,
                    'name' => $c->name,
                    'symbol' => $c->symbol,
                    'is_default' => (bool) $c->is_default,
                ])->values()->all(),
            'taxes' => Tax::query()
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get()
                ->map(static fn (Tax $t): array => [
                    'name' => $t->name,
                    'rate' => rtrim(rtrim((string) $t->rate, '0'), '.'),
                    'is_default' => (bool) $t->is_default,
                ])->values()->all(),
            'payment_terms' => PaymentTerm::query()
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->orderBy('days')
                ->get()
                ->map(static fn (PaymentTerm $p): array => [
                    'name' => $p->name,
                    'days' => (int) $p->days,
                    'is_default' => (bool) $p->is_default,
                ])->values()->all(),
            'warranties' => Warranty::query()
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->orderBy('duration_months')
                ->get()
                ->map(static fn (Warranty $w): array => [
                    'title' => $w->title,
                    'duration_months' => (int) $w->duration_months,
                    'is_default' => (bool) $w->is_default,
                ])->values()->all(),
            'bank_accounts' => BankAccount::query()
                ->with('currency')
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->orderBy('label')
                ->get()
                ->map(static fn (BankAccount $b): array => [
                    'label' => $b->label,
                    'bank_name' => $b->bank_name ?? '',
                    'account_holder' => $b->account_holder ?? '',
                    'account_type' => $b->account_type ?? 'official',
                    'currency_code' => $b->currency?->code ?? '',
                    'is_default' => (bool) $b->is_default,
                ])->values()->all(),
            'legal_texts' => LegalText::query()
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get()
                ->map(static fn (LegalText $l): array => [
                    'name' => $l->name,
                    'is_default' => (bool) $l->is_default,
                ])->values()->all(),
            'effective_locked_fields' => array_map(
                static fn (string $slug): string => $lockLabels[$slug] ?? $slug,
                $effectiveLockedSlugs,
            ),
            'system_locked_fields' => $canConfigureSystem
                ? array_map(static fn (string $slug): string => $lockLabels[$slug] ?? $slug, $configuredLockedSlugs)
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTechnicalReportsConfig(): array
    {
        $setting = ReportSetting::current();

        return [
            'report_prefix' => $setting->report_prefix,
            'next_number_preview' => $setting->previewNextNumber(),
            'section_titles' => array_values(array_filter([
                $setting->section_1_default_title,
                $setting->section_2_default_title,
                $setting->section_3_default_title,
                $setting->section_4_default_title,
            ])),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSystemUsersAndRoles(): array
    {
        $allActiveCompaniesCount = \App\Models\FiscalProfile::query()->where('is_active', true)->count();

        $users = User::query()
            ->with([
                'roles' => fn ($q) => $q->where('roles.is_active', true),
                'fiscalProfiles' => fn ($q) => $q->where('fiscal_profiles.is_active', true),
            ])
            ->withCount(['createdInvoices', 'createdTechnicalReports', 'createdAppointments'])
            ->orderBy('name')
            ->get()
            ->map(static function (User $item) use ($allActiveCompaniesCount): array {
                $assignedNames = $item->fiscalProfiles->pluck('name')->values()->all();

                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'email' => $item->email,
                    'roles' => $item->roles->pluck('name')->values()->all(),
                    'assigned_companies' => $assignedNames === []
                        ? ($allActiveCompaniesCount > 0 ? ['Todas las empresas activas'] : [])
                        : $assignedNames,
                    'invoices_count' => (int) $item->created_invoices_count,
                    'reports_count' => (int) $item->created_technical_reports_count,
                    'appointments_count' => (int) $item->created_appointments_count,
                ];
            })->values()->all();

        $roles = Role::query()
            ->where('is_active', true)
            ->with('permissions:id,name,slug')
            ->withCount('users')
            ->orderBy('id')
            ->get()
            ->map(static fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'description' => $role->description ?? '',
                'users_count' => (int) $role->users_count,
                'permissions_count' => $role->permissions->count(),
                'permission_names' => $role->permissions->pluck('name')->values()->all(),
            ])->values()->all();

        return [
            'users' => $users,
            'roles' => $roles,
        ];
    }
}
