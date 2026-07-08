<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\SettingsBootstrapService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    /**
     * Campos del formulario de factura que el panel administrativo puede bloquear.
     */
    public const LOCKABLE_FIELDS = [
        'conformity_text' => 'Texto de conformidad',
        'legal_text' => 'Texto legal',
        'observations' => 'Observaciones',
    ];

    public function index(SettingsBootstrapService $settings): View
    {
        return view('settings.index', ['settings' => $settings->get()]);
    }

    public function editLockedFields(): View
    {
        $value = Setting::query()->where('key', 'invoice.locked_fields')->value('value');

        return view('settings.locked-fields', [
            'lockableFields' => self::LOCKABLE_FIELDS,
            'lockedFields' => (array) ($value['fields'] ?? ['conformity_text', 'legal_text']),
        ]);
    }

    public function updateLockedFields(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'fields' => ['nullable', 'array'],
            'fields.*' => ['string', 'in:'.implode(',', array_keys(self::LOCKABLE_FIELDS))],
        ]);

        Setting::query()->updateOrCreate(
            ['key' => 'invoice.locked_fields'],
            [
                'group' => 'invoices',
                'value' => ['fields' => array_values($data['fields'] ?? [])],
                'description' => 'Campos del formulario de factura bloqueados para usuarios sin permiso de configuracion.',
            ],
        );

        return redirect()
            ->route('web.settings.locked-fields.edit')
            ->with('status', 'Campos bloqueados actualizados.');
    }
}
