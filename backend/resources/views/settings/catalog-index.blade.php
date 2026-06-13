@extends('layouts.app')

@section('title', $config['title'])
@section('subtitle', 'Configuracion administrable')
@section('actions')
<a class="btn" href="{{ route('web.settings.index') }}">Volver</a>
<a class="btn primary" href="{{ route('web.settings.catalog.create', $catalog) }}">Nuevo</a>
@endsection

@section('content')
<section class="card">
    <table class="table">
        <thead>
            <tr>
                @foreach($config['fields'] as $field)
                    <th>{{ $field['label'] }}</th>
                @endforeach
                <th class="right">Acciones</th>
            </tr>
        </thead>
        <tbody>
        @forelse($records as $record)
            <tr>
                @foreach($config['fields'] as $name => $field)
                    @php($value = $field['type'] === 'currency' ? $record->currency?->code : $record->{$name})
                    @php($displayValue = match (true) {
                        $field['type'] === 'select' && isset($field['options']) => (is_callable($field['options']) ? ($field['options'])() : $field['options'])[$value] ?? $value,
                        default => $value,
                    })
                    <td>
                        @if($field['type'] === 'checkbox')
                            {{ $value ? 'Si' : 'No' }}
                        @else
                            {{ \Illuminate\Support\Str::limit((string) $displayValue, 70) }}
                        @endif
                    </td>
                @endforeach
                <td class="right">
                    <div class="actions" style="justify-content:flex-end">
                        <a class="btn" href="{{ route('web.settings.catalog.edit', [$catalog, $record->id]) }}">Editar</a>
                        <form method="POST" action="{{ route('web.settings.catalog.destroy', [$catalog, $record->id]) }}">
                            @csrf
                            @method('DELETE')
                            <button class="btn danger" type="submit">{{ $catalog === 'invoice-number' ? 'Eliminar' : 'Desactivar' }}</button>
                        </form>
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="{{ count($config['fields']) + 1 }}">No hay registros.</td></tr>
        @endforelse
        </tbody>
    </table>
    <div class="pagination">{{ $records->links() }}</div>
</section>
@endsection
