@extends('layouts.app')

@section('title', $record->exists ? 'Editar '.$config['title'] : 'Nuevo '.$config['title'])
@section('subtitle', 'Los cambios aplican a nuevas facturas; las emitidas conservan snapshots')
@section('actions')
<a class="btn" href="{{ route('web.settings.catalog.index', $catalog) }}">Volver</a>
@endsection

@section('content')
<form method="POST" action="{{ $action }}" class="card form">
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
