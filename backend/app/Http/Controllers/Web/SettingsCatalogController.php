<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Currency;
use App\Models\FiscalProfile;
use App\Models\InvoiceNumberSetting;
use App\Models\LegalText;
use App\Models\PaymentTerm;
use App\Models\Tax;
use App\Models\User;
use App\Models\Warranty;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsCatalogController extends Controller
{
    public function index(string $catalog): View
    {
        $config = $this->config($catalog);
        $query = $config['model']::query();

        if ($config['model'] === BankAccount::class) {
            $query->with('currency');
        }

        $records = $query->latest('id')->paginate(15);

        return view('settings.catalog-index', compact('catalog', 'config', 'records'));
    }

    public function create(string $catalog): View
    {
        $config = $this->config($catalog);

        return view('settings.catalog-form', [
            'catalog' => $catalog,
            'config' => $config,
            'record' => new $config['model'](),
            'action' => route('web.settings.catalog.store', $catalog),
            'method' => 'POST',
            'currencies' => Currency::query()->where('is_active', true)->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request, string $catalog): RedirectResponse
    {
        $config = $this->config($catalog);

        $data = $this->validated($request, $catalog);
        $this->clearOtherDefaults($config['model'], $data);
        $record = $config['model']::query()->create($data);

        if ($catalog === 'fiscal-profiles' && $record instanceof FiscalProfile) {
            $this->storeFiscalProfileLogos($request, $record);
        }

        return redirect()->route('web.settings.catalog.index', $catalog)->with('status', 'Configuracion creada.');
    }

    public function edit(string $catalog, int $id): View
    {
        $config = $this->config($catalog);
        $record = $this->findRecord($config['model'], $id);

        return view('settings.catalog-form', [
            'catalog' => $catalog,
            'config' => $config,
            'record' => $record,
            'action' => route('web.settings.catalog.update', [$catalog, $record->id]),
            'method' => 'PUT',
            'currencies' => Currency::query()->where('is_active', true)->orderBy('code')->get(),
        ]);
    }

    public function update(Request $request, string $catalog, int $id): RedirectResponse
    {
        $config = $this->config($catalog);
        $record = $this->findRecord($config['model'], $id);
        $data = $this->validated($request, $catalog, $record);

        $this->clearOtherDefaults($config['model'], $data, $record);
        $record->update($data);

        if ($catalog === 'fiscal-profiles' && $record instanceof FiscalProfile) {
            $record->refresh();
            $this->deleteFiscalProfileLogos($request, $record);
            $record->refresh();
            $this->storeFiscalProfileLogos($request, $record);
            $record->refresh();
            $this->syncFiscalProfileDefaultLogo($record);
        }

        return redirect()->route('web.settings.catalog.index', $catalog)->with('status', 'Configuracion actualizada.');
    }

    public function destroy(string $catalog, int $id): RedirectResponse
    {
        $config = $this->config($catalog);

        $record = $this->findRecord($config['model'], $id);

        if ($catalog === 'invoice-number') {
            $record->delete();
        } else {
            $record->update(['is_active' => false, 'is_default' => false]);
        }

        return back()->with('status', 'Configuracion desactivada.');
    }

    /**
     * @return array<string, mixed>
     */
    private function config(string $catalog): array
    {
        $configs = [
            'currencies' => [
                'title' => 'Monedas',
                'model' => Currency::class,
                'fields' => [
                    'name' => ['label' => 'Nombre', 'type' => 'text'],
                    'code' => ['label' => 'Codigo ISO', 'type' => 'text'],
                    'symbol' => ['label' => 'Simbolo', 'type' => 'text'],
                    'decimal_separator' => ['label' => 'Separador decimal', 'type' => 'text'],
                    'thousand_separator' => ['label' => 'Separador miles', 'type' => 'text'],
                    'decimal_places' => ['label' => 'Decimales', 'type' => 'number'],
                    'symbol_position' => ['label' => 'Posicion simbolo', 'type' => 'select', 'options' => ['before' => 'Antes', 'after' => 'Despues']],
                    'is_default' => ['label' => 'Predeterminada', 'type' => 'checkbox'],
                    'is_active' => ['label' => 'Activa', 'type' => 'checkbox'],
                ],
            ],
            'taxes' => [
                'title' => 'Impuestos',
                'model' => Tax::class,
                'fields' => [
                    'name' => ['label' => 'Nombre', 'type' => 'text'],
                    'rate' => ['label' => 'Tasa %', 'type' => 'number', 'step' => '0.0001'],
                    'is_default' => ['label' => 'Predeterminado', 'type' => 'checkbox'],
                    'is_active' => ['label' => 'Activo', 'type' => 'checkbox'],
                ],
            ],
            'payment-terms' => [
                'title' => 'Terminos de pago',
                'model' => PaymentTerm::class,
                'fields' => [
                    'name' => ['label' => 'Nombre', 'type' => 'text'],
                    'days' => ['label' => 'Dias', 'type' => 'number'],
                    'description' => ['label' => 'Descripcion', 'type' => 'textarea'],
                    'is_default' => ['label' => 'Predeterminado', 'type' => 'checkbox'],
                    'is_active' => ['label' => 'Activo', 'type' => 'checkbox'],
                ],
            ],
            'warranties' => [
                'title' => 'Garantias',
                'model' => Warranty::class,
                'fields' => [
                    'title' => ['label' => 'Titulo', 'type' => 'text'],
                    'description' => ['label' => 'Descripcion', 'type' => 'textarea'],
                    'duration_months' => ['label' => 'Meses', 'type' => 'number'],
                    'full_text' => ['label' => 'Texto completo', 'type' => 'textarea'],
                    'is_default' => ['label' => 'Predeterminada', 'type' => 'checkbox'],
                    'is_active' => ['label' => 'Activa', 'type' => 'checkbox'],
                ],
            ],
            'bank-accounts' => [
                'title' => 'Cuentas bancarias',
                'model' => BankAccount::class,
                'fields' => [
                    'label' => ['label' => 'Etiqueta', 'type' => 'text'],
                    'account_type' => ['label' => 'Tipo', 'type' => 'select', 'options' => ['official' => 'Oficial', 'unofficial' => 'No oficial']],
                    'fiscal_profile_id' => [
                        'label' => 'Perfil fiscal',
                        'type' => 'select',
                        'options' => fn () => FiscalProfile::query()->orderBy('name')->pluck('name', 'id')->prepend('Sin perfil', '')->all(),
                    ],
                    'account_holder' => ['label' => 'Titular', 'type' => 'text'],
                    'bank_name' => ['label' => 'Banco', 'type' => 'text'],
                    'account_number' => ['label' => 'Numero de cuenta', 'type' => 'text'],
                    'iban' => ['label' => 'IBAN', 'type' => 'text'],
                    'swift' => ['label' => 'SWIFT', 'type' => 'text'],
                    'currency_id' => ['label' => 'Moneda', 'type' => 'currency'],
                    'is_default' => ['label' => 'Predeterminada', 'type' => 'checkbox'],
                    'is_active' => ['label' => 'Activa', 'type' => 'checkbox'],
                ],
            ],
            'fiscal-profiles' => [
                'title' => 'Perfiles fiscales',
                'model' => FiscalProfile::class,
                'fields' => [
                    'name' => ['label' => 'Nombre', 'type' => 'text'],
                    'tax_id' => ['label' => 'Identificacion fiscal', 'type' => 'text'],
                    'address' => ['label' => 'Direccion', 'type' => 'text'],
                    'city' => ['label' => 'Ciudad', 'type' => 'text'],
                    'phone' => ['label' => 'Telefono', 'type' => 'text'],
                    'email' => ['label' => 'Email', 'type' => 'email'],
                    'logo_uploads' => [
                        'label' => 'Logos del perfil',
                        'type' => 'file',
                        'path_field' => 'logo_path',
                        'gallery' => 'logos',
                        'multiple' => true,
                        'accept' => 'image/png,image/jpeg,image/webp,image/svg+xml',
                        'help' => 'PNG, JPG, WEBP o SVG. Puedes cargar varios logos y elegir uno distinto al crear cada factura.',
                    ],
                    'is_default' => ['label' => 'Predeterminado', 'type' => 'checkbox'],
                    'is_active' => ['label' => 'Activo', 'type' => 'checkbox'],
                ],
            ],
            'legal-texts' => [
                'title' => 'Textos legales',
                'model' => LegalText::class,
                'fields' => [
                    'name' => ['label' => 'Nombre', 'type' => 'text'],
                    'legal_footer' => ['label' => 'Pie legal', 'type' => 'textarea'],
                    'warranty_text' => ['label' => 'Texto garantia', 'type' => 'textarea'],
                    'conformity_text' => ['label' => 'Texto conformidad', 'type' => 'textarea'],
                    'client_copy_text' => ['label' => 'Copia cliente', 'type' => 'textarea'],
                    'seller_copy_text' => ['label' => 'Copia vendedor', 'type' => 'textarea'],
                    'is_default' => ['label' => 'Predeterminado', 'type' => 'checkbox'],
                    'is_active' => ['label' => 'Activo', 'type' => 'checkbox'],
                ],
            ],
            'invoice-number' => [
                'title' => 'Numeracion',
                'model' => InvoiceNumberSetting::class,
                'fields' => [
                    'fiscal_profile_id' => [
                        'label' => 'Perfil fiscal (empresa)',
                        'type' => 'select',
                        'options' => fn () => \App\Models\FiscalProfile::query()->orderBy('name')->pluck('name', 'id')->prepend('Global (sin perfil)', '')->all(),
                    ],
                    'user_id' => [
                        'label' => 'Usuario que factura',
                        'type' => 'select',
                        'options' => fn () => User::query()->orderBy('name')->pluck('name', 'id')->prepend('Plantilla de empresa (sin usuario)', '')->all(),
                    ],
                    'logo_path' => [
                        'label' => 'Logo',
                        'type' => 'select',
                        'options' => fn () => \App\Models\FiscalProfileLogo::query()
                            ->with('fiscalProfile')
                            ->orderBy('fiscal_profile_id')
                            ->orderByDesc('is_default')
                            ->orderBy('label')
                            ->get()
                            ->mapWithKeys(fn (\App\Models\FiscalProfileLogo $logo): array => [
                                $logo->path => ($logo->fiscalProfile?->name ? $logo->fiscalProfile->name.' - ' : '').($logo->label ?: basename($logo->path)),
                            ])
                            ->prepend('Selecciona un logo', '')
                            ->all(),
                    ],
                    'document_type' => [
                        'label' => 'Tipo de documento',
                        'type' => 'select',
                        'options' => ['invoice' => 'Factura', 'quotation' => 'Presupuesto'],
                    ],
                    'prefix' => ['label' => 'Prefijo', 'type' => 'text'],
                    'next_number' => ['label' => 'Proximo numero', 'type' => 'number'],
                    'number_length' => ['label' => 'Longitud', 'type' => 'number'],
                    'serie' => ['label' => 'Serie', 'type' => 'text'],
                    'reset_yearly' => ['label' => 'Reiniciar anual', 'type' => 'checkbox'],
                    'reset_monthly' => ['label' => 'Reiniciar mensual', 'type' => 'checkbox'],
                    'current_year' => ['label' => 'Ano actual', 'type' => 'number'],
                    'current_month' => ['label' => 'Mes actual', 'type' => 'number'],
                ],
            ],
        ];

        abort_unless(array_key_exists($catalog, $configs), 404);

        return $configs[$catalog];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, string $catalog, ?Model $record = null): array
    {
        $rules = match ($catalog) {
            'currencies' => [
                'name' => ['required', 'string', 'max:255'],
                'code' => ['required', 'string', 'size:3', Rule::unique('currencies', 'code')->ignore($record?->id)],
                'symbol' => ['required', 'string', 'max:8'],
                'decimal_separator' => ['required', 'string', 'max:4'],
                'thousand_separator' => ['required', 'string', 'max:4'],
                'decimal_places' => ['required', 'integer', 'min:0', 'max:4'],
                'symbol_position' => ['required', Rule::in(['before', 'after'])],
                'is_default' => ['sometimes', 'boolean'],
                'is_active' => ['sometimes', 'boolean'],
            ],
            'taxes' => [
                'name' => ['required', 'string', 'max:255'],
                'rate' => ['required', 'numeric', 'min:0', 'max:100'],
                'is_default' => ['sometimes', 'boolean'],
                'is_active' => ['sometimes', 'boolean'],
            ],
            'payment-terms' => [
                'name' => ['required', 'string', 'max:255'],
                'days' => ['required', 'integer', 'min:0', 'max:3650'],
                'description' => ['nullable', 'string'],
                'is_default' => ['sometimes', 'boolean'],
                'is_active' => ['sometimes', 'boolean'],
            ],
            'warranties' => [
                'title' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
                'duration_months' => ['nullable', 'integer', 'min:0', 'max:600'],
                'full_text' => ['required', 'string'],
                'is_default' => ['sometimes', 'boolean'],
                'is_active' => ['sometimes', 'boolean'],
            ],
            'bank-accounts' => [
                'label' => ['required', 'string', 'max:255'],
                'account_type' => ['required', Rule::in(['official', 'unofficial'])],
                'fiscal_profile_id' => ['nullable', 'exists:fiscal_profiles,id'],
                'account_holder' => ['required', 'string', 'max:255'],
                'bank_name' => ['required', 'string', 'max:255'],
                'account_number' => ['nullable', 'string', 'max:255'],
                'iban' => ['nullable', 'string', 'max:255'],
                'swift' => ['nullable', 'string', 'max:255'],
                'currency_id' => ['nullable', 'exists:currencies,id'],
                'is_default' => ['sometimes', 'boolean'],
                'is_active' => ['sometimes', 'boolean'],
            ],
            'fiscal-profiles' => [
                'name' => ['required', 'string', 'max:255'],
                'tax_id' => ['nullable', 'string', 'max:255'],
                'address' => ['nullable', 'string', 'max:255'],
                'city' => ['nullable', 'string', 'max:255'],
                'phone' => ['nullable', 'string', 'max:255'],
                'email' => ['nullable', 'email', 'max:255'],
                'logo_uploads' => ['nullable', 'array'],
                'logo_uploads.*' => ['file', 'mimes:png,jpg,jpeg,webp,svg', 'max:4096'],
                'delete_logos' => ['nullable', 'array'],
                'delete_logos.*' => ['integer'],
                'is_default' => ['sometimes', 'boolean'],
                'is_active' => ['sometimes', 'boolean'],
            ],
            'legal-texts' => [
                'name' => ['required', 'string', 'max:255'],
                'legal_footer' => ['nullable', 'string'],
                'warranty_text' => ['nullable', 'string'],
                'conformity_text' => ['nullable', 'string'],
                'client_copy_text' => ['nullable', 'string'],
                'seller_copy_text' => ['nullable', 'string'],
                'is_default' => ['sometimes', 'boolean'],
                'is_active' => ['sometimes', 'boolean'],
            ],
            'invoice-number' => [
                'fiscal_profile_id' => ['nullable', 'exists:fiscal_profiles,id'],
                'user_id' => ['nullable', 'exists:users,id'],
                'logo_path' => ['nullable', 'string', 'max:255'],
                'document_type' => [
                    'required',
                    Rule::in(['invoice', 'quotation']),
                    Rule::unique('invoice_number_settings', 'document_type')
                        ->where(fn ($q) => $q
                            ->where('fiscal_profile_id', $request->input('fiscal_profile_id') ?: null)
                            ->where('user_id', $request->input('user_id') ?: null)
                            ->where('logo_path', $request->input('logo_path') ?: null))
                        ->ignore($record?->id),
                ],
                'prefix' => ['required', 'string', 'max:255'],
                'next_number' => ['required', 'integer', 'min:1'],
                'number_length' => ['required', 'integer', 'min:1', 'max:12'],
                'serie' => ['nullable', 'string', 'max:255'],
                'reset_yearly' => ['sometimes', 'boolean'],
                'reset_monthly' => ['sometimes', 'boolean'],
                'current_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
                'current_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            ],
            default => abort(404),
        };

        $data = $request->validate($rules);

        if ($catalog === 'invoice-number' && filled($data['logo_path'] ?? null)) {
            $logoBelongsToProfile = \App\Models\FiscalProfileLogo::query()
                ->where('fiscal_profile_id', $data['fiscal_profile_id'] ?? null)
                ->where('path', $data['logo_path'])
                ->exists();

            if (! $logoBelongsToProfile) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'logo_path' => 'El logo seleccionado no pertenece al perfil fiscal.',
                ]);
            }
        }

        $fields = $this->config($catalog)['fields'];

        foreach ($fields as $name => $field) {
            if ($field['type'] === 'checkbox') {
                $data[$name] = $request->boolean($name);
            }
        }

        if (array_key_exists('code', $data)) {
            $data['code'] = strtoupper((string) $data['code']);
        }

        if ($catalog === 'invoice-number') {
            $data['allow_manual_number'] = false;
        }

        if ($catalog === 'fiscal-profiles') {
            unset($data['logo_uploads'], $data['delete_logos']);
        }

        return collect($data)
            ->map(fn ($value) => $value === '' ? null : $value)
            ->all();
    }

    private function storeFiscalProfileLogos(Request $request, FiscalProfile $profile): void
    {
        if (! $request->hasFile('logo_uploads')) {
            return;
        }

        $files = $request->file('logo_uploads');
        $files = is_array($files) ? $files : [$files];
        $baseName = Str::slug($profile->name) ?: 'perfil';

        foreach ($files as $index => $file) {
            $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'png');
            $filename = $baseName.'-'.now()->format('YmdHis').'-'.($index + 1).'.'.$extension;
            $path = $file->storeAs('logos', $filename, 'public');
            $isDefault = ! $profile->logos()->exists() && blank($profile->logo_path);

            $profile->logos()->create([
                'path' => $path,
                'label' => $file->getClientOriginalName(),
                'is_default' => $isDefault,
            ]);

            if ($isDefault || blank($profile->logo_path)) {
                $profile->forceFill(['logo_path' => $path])->save();
            }
        }
    }

    private function deleteFiscalProfileLogos(Request $request, FiscalProfile $profile): void
    {
        $ids = collect($request->input('delete_logos', []))
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->values()
            ->all();

        if ($ids === []) {
            return;
        }

        $paths = $profile->logos()->whereKey($ids)->pluck('path')->all();
        $profile->logos()->whereKey($ids)->delete();
        $this->syncFiscalProfileDefaultLogo($profile->refresh());

        foreach ($paths as $path) {
            $stillReferenced = FiscalProfile::query()->where('logo_path', $path)->exists()
                || \App\Models\FiscalProfileLogo::query()->where('path', $path)->exists();

            if (! $stillReferenced) {
                Storage::disk('public')->delete($path);
            }
        }
    }

    private function syncFiscalProfileDefaultLogo(FiscalProfile $profile): void
    {
        $logos = $profile->logos()->get();

        if ($logos->isEmpty()) {
            $profile->forceFill(['logo_path' => null])->save();

            return;
        }

        $default = $logos->firstWhere('is_default', true)
            ?? $logos->firstWhere('path', $profile->logo_path)
            ?? $logos->first();

        $profile->logos()->whereKeyNot($default->id)->update(['is_default' => false]);
        $default->forceFill(['is_default' => true])->save();
        $profile->forceFill(['logo_path' => $default->path])->save();
    }

    private function findRecord(string $model, int $id): Model
    {
        return $model::query()->findOrFail($id);
    }

    private function clearOtherDefaults(string $model, array $data, ?Model $record = null): void
    {
        if (! array_key_exists('is_default', $data) || ! $data['is_default']) {
            return;
        }

        $query = $model::query();

        if ($record?->exists) {
            $query->whereKeyNot($record->id);
        }

        $query->update(['is_default' => false]);
    }
}
