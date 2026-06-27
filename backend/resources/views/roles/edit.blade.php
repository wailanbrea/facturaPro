@extends('layouts.app')

@section('title', 'Permisos: '.$role->name)
@section('subtitle', 'Selecciona los permisos que tendra este rol')
@section('actions')
<a href="{{ route('web.roles.index') }}" class="btn">
    <i data-lucide="arrow-left" class="w-4 h-4"></i> Volver
</a>
@endsection

@section('content')
@if(session('status'))
    <div class="alert success" style="margin-bottom:16px">{{ session('status') }}</div>
@endif

<form method="POST" action="{{ route('web.roles.update', $role) }}">
    @csrf @method('PUT')

    <div class="invoice-grid" style="--aside-w:260px">

        {{-- Grupos de permisos --}}
        <div class="space-y-4">
        @foreach($permissions as $group => $items)
            @php
                $groupIcons = [
                    'Facturas'          => 'file-text',
                    'Informes tecnicos' => 'clipboard-list',
                    'Clientes'          => 'users',
                    'Calendario'        => 'calendar-days',
                    'Reportes'          => 'bar-chart-3',
                    'Sistema'           => 'settings',
                ];
                $groupChecked = $items->filter(fn($p) => isset($assigned[$p->id]))->count();
                $groupTotal   = $items->count();
            @endphp
            <section class="card" style="padding:0;overflow:hidden">
                {{-- Cabecera del grupo --}}
                <div class="flex items-center justify-between px-5 py-3.5 border-b border-outline-variant/50 bg-surface-low">
                    <div class="flex items-center gap-2.5">
                        <i data-lucide="{{ $groupIcons[$group] ?? 'lock' }}" class="w-4 h-4 text-primary"></i>
                        <span class="font-semibold text-[14px] text-on-surface">{{ $group }}</span>
                    </div>
                    <span class="text-[12px] text-on-surface-variant">
                        {{ $groupChecked }} / {{ $groupTotal }} activos
                    </span>
                </div>

                {{-- Permisos del grupo --}}
                <div class="divide-y divide-outline-variant/30">
                @foreach($items as $perm)
                    @php $checked = isset($assigned[$perm->id]); @endphp
                    <label class="flex items-center justify-between gap-4 px-5 py-3.5 cursor-pointer
                                  hover:bg-surface-low/60 transition-colors
                                  {{ $checked ? 'bg-success-soft/20' : '' }}">
                        <div>
                            <div class="text-[13.5px] font-medium text-on-surface">{{ $perm->name }}</div>
                            <div class="text-[11.5px] text-on-surface-variant font-mono mt-0.5">{{ $perm->slug }}</div>
                        </div>
                        <div class="relative shrink-0">
                            <input type="checkbox"
                                   name="permissions[]"
                                   value="{{ $perm->id }}"
                                   {{ $checked ? 'checked' : '' }}
                                   class="sr-only peer">
                            <div class="w-11 h-6 rounded-full border-2 transition-colors
                                        border-outline-variant peer-checked:border-primary peer-checked:bg-primary
                                        bg-surface-mid flex items-center">
                                <div class="w-4 h-4 rounded-full bg-white shadow-sm ml-0.5 transition-transform
                                            peer-checked:translate-x-5"></div>
                            </div>
                        </div>
                    </label>
                @endforeach
                </div>
            </section>
        @endforeach
        </div>

        {{-- Panel lateral --}}
        <aside class="card" style="align-self:start;position:sticky;top:80px">
            <h3 style="margin-top:0">Rol</h3>
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-xl bg-primary-soft-2 flex items-center justify-center shrink-0">
                    <i data-lucide="shield-check" class="w-5 h-5 text-primary"></i>
                </div>
                <div>
                    <div class="font-bold text-[15px]">{{ $role->name }}</div>
                    <div class="text-[12px] text-on-surface-variant font-mono">{{ $role->slug }}</div>
                </div>
            </div>

            <div class="bg-surface-low rounded-lg px-4 py-3 mb-5 text-[13px] text-on-surface-variant">
                <div id="perm-count" class="text-[22px] font-bold text-primary leading-6">
                    {{ $assigned->count() }}
                </div>
                permisos seleccionados de {{ $permissions->flatten()->count() }}
            </div>

            @if($role->slug === 'admin')
                <div class="bg-warning-soft rounded-lg px-4 py-3 mb-4 text-[12.5px] text-warning flex gap-2">
                    <i data-lucide="alert-triangle" class="w-4 h-4 shrink-0 mt-0.5"></i>
                    Estas editando el rol de administrador. Modifica con cuidado.
                </div>
            @endif

            <button type="submit" class="btn primary w-full" style="height:42px">
                <i data-lucide="save" class="w-4 h-4"></i>
                Guardar cambios
            </button>
        </aside>

    </div>
</form>

<script>
// Actualizar contador en tiempo real
document.querySelectorAll('input[name="permissions[]"]').forEach(cb => {
    cb.addEventListener('change', function () {
        const total = document.querySelectorAll('input[name="permissions[]"]:checked').length;
        document.getElementById('perm-count').textContent = total;
        // Colorear fila
        const row = this.closest('label');
        if (this.checked) row.classList.add('bg-success-soft/20');
        else row.classList.remove('bg-success-soft/20');
        // Toggle visual del switch
        const dot = this.nextElementSibling?.querySelector('div');
        if (dot) dot.style.transform = this.checked ? 'translateX(20px)' : '';
    });
});
</script>
@endsection
