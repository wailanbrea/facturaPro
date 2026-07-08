@extends('layouts.app')

@section('title', 'Campos bloqueados')
@section('subtitle', 'Panel administrativo: bloquea casillas del formulario de factura para evitar cambios por error')

@section('actions')
<a class="btn" href="{{ route('web.settings.index') }}">Volver a configuración</a>
@endsection

@section('content')
<form method="POST" action="{{ route('web.settings.locked-fields.update') }}" class="form" style="max-width:640px">
    @csrf
    @method('PUT')
    <section class="card">
        <h3>Casillas editables del documento</h3>
        <p class="muted" style="margin-bottom:16px">
            Las casillas marcadas quedan bloqueadas (solo lectura) al crear o editar facturas y presupuestos
            para los usuarios sin permiso de configuración. Los administradores siempre pueden editarlas.
        </p>
        <div class="space-y-3">
            @foreach($lockableFields as $field => $label)
                <label class="flex items-center gap-3 border border-outline-variant rounded-lg px-4 py-3 cursor-pointer hover:bg-surface-low">
                    <input type="checkbox" name="fields[]" value="{{ $field }}"
                           class="rounded border-outline-variant text-primary focus:ring-primary"
                           @checked(in_array($field, $lockedFields, true))>
                    <span class="flex-1">
                        <span class="font-semibold text-[14px] block">{{ $label }}</span>
                        <span class="text-[12px] text-on-surface-variant">
                            {{ in_array($field, $lockedFields, true) ? 'Bloqueado actualmente' : 'Editable actualmente' }}
                        </span>
                    </span>
                    <i data-lucide="{{ in_array($field, $lockedFields, true) ? 'lock' : 'lock-open' }}" class="w-4 h-4 text-on-surface-variant"></i>
                </label>
            @endforeach
        </div>
        <div class="actions" style="margin-top:18px">
            <button class="btn primary" type="submit">Guardar cambios</button>
        </div>
    </section>
</form>
@endsection
