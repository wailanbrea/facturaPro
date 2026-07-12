@extends('layouts.app')

@section('title', $record->exists ? 'Editar '.$config['title'] : 'Nuevo '.$config['title'])
@section('subtitle', 'Los cambios aplican a nuevas facturas; las emitidas conservan snapshots')
@section('actions')
<a class="btn" href="{{ route('web.settings.catalog.index', $catalog) }}">Volver</a>
@endsection

@section('content')
@php($hasFileFields = collect($config['fields'])->contains(fn ($field) => ($field['type'] ?? null) === 'file'))
<form method="POST" action="{{ $action }}" class="card form" @if($hasFileFields) enctype="multipart/form-data" @endif>
    @csrf
    @if($method !== 'POST')
        @method($method)
    @endif

    <div class="fields">
        @foreach($config['fields'] as $name => $field)
            <div class="field {{ in_array($field['type'], ['textarea'], true) ? 'span-2' : '' }}">
                @if($field['type'] === 'checkbox')
                    <input type="hidden" name="{{ $name }}" value="0">
                    <label><input style="width:auto" type="checkbox" name="{{ $name }}" value="1" @checked((bool) old($name, $record->{$name} ?? $name === 'is_active'))> {{ $field['label'] }}</label>
                @else
                    <label>{{ $field['label'] }}</label>
                    @if($field['type'] === 'textarea')
                        <textarea name="{{ $name }}">{{ old($name, $record->{$name}) }}</textarea>
                    @elseif($field['type'] === 'select')
                        @php($options = is_callable($field['options']) ? ($field['options'])() : $field['options'])
                        <select name="{{ $name }}">
                            @foreach($options as $value => $label)
                                <option value="{{ $value }}" @selected((string) old($name, $record->{$name} ?? '') === (string) $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    @elseif($field['type'] === 'currency')
                        <select name="{{ $name }}">
                            <option value="">Sin moneda</option>
                            @foreach($currencies as $currency)
                                <option value="{{ $currency->id }}" @selected((int) old($name, $record->{$name}) === $currency->id)>{{ $currency->code }} - {{ $currency->name }}</option>
                            @endforeach
                        </select>
                    @elseif($field['type'] === 'file')
                        @php($pathField = $field['path_field'] ?? null)
                        @php($currentPath = $pathField ? ($record->{$pathField} ?? null) : null)
                        @if(($field['gallery'] ?? null) && $record->exists)
                            @php($logos = $record->{$field['gallery']} ?? collect())
                            @if($logos->isNotEmpty())
                                <div class="flex flex-wrap gap-3" style="margin-bottom:12px">
                                    @foreach($logos as $logo)
                                        <div style="width:126px;border:1px solid var(--line);border-radius:6px;background:#fff;padding:8px">
                                            <img src="{{ asset('storage/'.$logo->path) }}" alt="{{ $logo->label ?? basename($logo->path) }}" style="width:108px;height:58px;object-fit:contain;display:block;margin:0 auto 6px">
                                            <div class="muted" style="font-size:11px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $logo->label ?? basename($logo->path) }}</div>
                                            @if($logo->is_default)
                                                <div class="text-[11px] text-primary font-semibold">Predeterminado</div>
                                            @endif
                                            <label style="display:flex;align-items:center;gap:6px;margin-top:8px;font-size:12px;color:#b42318">
                                                <input type="checkbox" name="delete_logos[]" value="{{ $logo->id }}" style="width:auto">
                                                Eliminar
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        @endif
                        @if($currentPath)
                            <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px">
                                <img src="{{ asset('storage/'.$currentPath) }}" alt="Logo actual" style="width:96px;height:58px;object-fit:contain;border:1px solid var(--line);border-radius:6px;background:#fff;padding:6px">
                                <span class="muted" style="font-size:12px">{{ $currentPath }}</span>
                            </div>
                        @endif
                        <input name="{{ ($field['multiple'] ?? false) ? $name.'[]' : $name }}" type="file" accept="{{ $field['accept'] ?? 'image/*' }}" @if($field['multiple'] ?? false) multiple @endif>
                        @if(!empty($field['help']))
                            <p class="muted" style="font-size:12px;margin-top:6px">{{ $field['help'] }}</p>
                        @endif
                    @else
                        <input name="{{ $name }}" type="{{ $field['type'] }}" step="{{ $field['step'] ?? '1' }}" value="{{ old($name, $record->{$name}) }}">
                    @endif
                @endif
            </div>
        @endforeach
    </div>

    <div class="actions">
        <button class="btn primary" type="submit">Guardar</button>
        <a class="btn" href="{{ route('web.settings.catalog.index', $catalog) }}">Cancelar</a>
    </div>
</form>
@endsection
