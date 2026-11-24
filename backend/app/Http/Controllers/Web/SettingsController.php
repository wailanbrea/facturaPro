<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Api\AppReleaseController;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\SettingsBootstrapService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
                'description' => 'Campos del formulario de factura bloqueados para usuarios sin permisos de creacion o edicion.',
            ],
        );

        return redirect()
            ->route('web.settings.locked-fields.edit')
            ->with('status', 'Campos bloqueados actualizados.');
    }

    public function editAppRelease(AppReleaseController $releaseController): View
    {
        return view('settings.app-release', [
            'release' => $releaseController->resolveReleaseMetadata(),
        ]);
    }

    public function updateAppRelease(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'version_code' => ['required', 'integer', 'min:1'],
            'version_name' => ['required', 'string', 'max:50'],
            'release_notes' => ['nullable', 'string', 'max:1000'],
            'apk_file' => ['required', 'file', 'max:102400'],
        ]);

        $previous = Setting::query()->where('key', 'android.app_release')->value('value');
        if (is_array($previous) && ! empty($previous['apk_path'])) {
            Storage::disk('public')->delete((string) $previous['apk_path']);
        }

        $fileName = 'FacturaPro-v'.preg_replace('/[^A-Za-z0-9._-]/', '_', $data['version_name']).'-'.(int) $data['version_code'].'.apk';
        $apkPath = $request->file('apk_file')->storeAs('apk', $fileName, 'public');

        Setting::query()->updateOrCreate(
            ['key' => 'android.app_release'],
            [
                'group' => 'mobile',
                'value' => [
                    'version_code' => (int) $data['version_code'],
                    'version_name' => trim($data['version_name']),
                    'apk_path' => $apkPath,
                    'release_notes' => trim((string) ($data['release_notes'] ?? '')),
                    'published_at' => now()->toDateString(),
                ],
                'description' => 'Paquete APK de FacturaPro publicado desde el panel web.',
            ],
        );

        return redirect()
            ->route('web.settings.app-release.edit')
            ->with('status', 'Nueva versión de la APK publicada correctamente.');
    }
}
