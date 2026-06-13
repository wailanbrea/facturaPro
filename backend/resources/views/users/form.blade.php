@extends('layouts.app')

@section('title', $user->exists ? 'Editar usuario' : 'Nuevo usuario')
@section('subtitle', $user->exists ? 'Actualiza datos y roles del usuario' : 'Crea un nuevo acceso administrativo')
@section('actions')
<a class="inline-flex items-center gap-2 bg-white border border-outline-variant text-on-surface font-semibold text-[13px] rounded-lg px-3.5 py-2 hover:bg-surface-low" href="{{ route('web.users.index') }}">
    <i data-lucide="arrow-left" class="w-4 h-4"></i>
    Volver
</a>
@endsection

@section('content')
<form method="POST" action="{{ $action }}" class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    @csrf
    @if($method !== 'POST')
        @method($method)
    @endif

    {{-- Datos básicos --}}
    <section class="bg-white rounded-xl border border-outline-variant/60 shadow-card p-5 lg:col-span-2">
        <div class="flex items-center gap-3 mb-5">
            <div class="w-9 h-9 rounded-lg bg-primary-soft-2 text-primary flex items-center justify-center">
                <i data-lucide="user" class="w-4 h-4"></i>
            </div>
            <div>
                <h2 class="text-[16px] font-semibold text-on-surface leading-5">Información de la cuenta</h2>
                <p class="text-[12px] text-on-surface-variant">Datos personales y credenciales</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-[13px] font-semibold text-on-surface mb-1.5">Nombre completo</label>
                <input name="name" value="{{ old('name', $user->name) }}" required
                       class="w-full border border-outline-variant/70 rounded-lg px-3 py-2 text-[14px] focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/15">
            </div>
            <div>
                <label class="block text-[13px] font-semibold text-on-surface mb-1.5">Correo electrónico</label>
                <input name="email" type="email" value="{{ old('email', $user->email) }}" required
                       class="w-full border border-outline-variant/70 rounded-lg px-3 py-2 text-[14px] focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/15">
            </div>
            <div class="md:col-span-2">
                <label class="block text-[13px] font-semibold text-on-surface mb-1.5">
                    Contraseña
                    @if($user->exists)
                        <span class="text-on-surface-variant font-normal">(déjalo vacío para conservar la actual)</span>
                    @else
                        <span class="text-on-surface-variant font-normal">(mínimo 10 caracteres)</span>
                    @endif
                </label>
                <input name="password" type="password" minlength="10" {{ $user->exists ? '' : 'required' }}
                       autocomplete="new-password"
                       class="w-full border border-outline-variant/70 rounded-lg px-3 py-2 text-[14px] focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/15">
            </div>
        </div>
    </section>

    {{-- Roles --}}
    <section class="bg-white rounded-xl border border-outline-variant/60 shadow-card p-5">
        <div class="flex items-center gap-3 mb-5">
            <div class="w-9 h-9 rounded-lg bg-primary-soft-2 text-primary flex items-center justify-center">
                <i data-lucide="shield-check" class="w-4 h-4"></i>
            </div>
            <div>
                <h2 class="text-[16px] font-semibold text-on-surface leading-5">Roles asignados</h2>
                <p class="text-[12px] text-on-surface-variant">Define permisos del usuario</p>
            </div>
        </div>

        @php
            $selectedRoles = collect(old('roles', $user->roles->pluck('id')->all()))->map(fn($v) => (int) $v)->all();
        @endphp

        <div class="space-y-2 max-h-72 overflow-y-auto pr-1">
            @forelse($roles as $role)
                @php $checked = in_array($role->id, $selectedRoles, true); @endphp
                <label class="flex items-start gap-3 px-3 py-2.5 rounded-lg border {{ $checked ? 'border-primary bg-primary-soft-2/60' : 'border-outline-variant/60 hover:bg-surface-low' }} cursor-pointer transition-colors">
                    <input type="checkbox" name="roles[]" value="{{ $role->id }}" {{ $checked ? 'checked' : '' }}
                           class="mt-1 h-4 w-4 rounded border-outline-variant text-primary focus:ring-primary">
                    <span class="flex-1">
                        <span class="block text-[13.5px] font-semibold text-on-surface">{{ $role->name }}</span>
                        @if($role->description ?? false)
                            <span class="block text-[11.5px] text-on-surface-variant">{{ $role->description }}</span>
                        @endif
                    </span>
                </label>
            @empty
                <p class="text-[12.5px] text-on-surface-variant italic">No hay roles activos disponibles.</p>
            @endforelse
        </div>
    </section>

    {{-- Empresas asignadas --}}
    <section class="bg-white rounded-xl border border-outline-variant/60 shadow-card p-5 lg:col-span-3">
        <div class="flex items-center gap-3 mb-5">
            <div class="w-9 h-9 rounded-lg bg-primary-soft-2 text-primary flex items-center justify-center">
                <i data-lucide="building-2" class="w-4 h-4"></i>
            </div>
            <div>
                <h2 class="text-[16px] font-semibold text-on-surface leading-5">Empresas asignadas</h2>
                <p class="text-[12px] text-on-surface-variant">Empresas con las que este usuario puede facturar. Si no marcas ninguna, podrá usar todas.</p>
            </div>
        </div>

        @php
            $selectedProfiles = collect(old('fiscal_profiles', $user->fiscalProfiles->pluck('id')->all()))->map(fn($v) => (int) $v)->all();
        @endphp

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2">
            @forelse($fiscalProfiles as $profile)
                @php $checked = in_array($profile->id, $selectedProfiles, true); @endphp
                <label class="flex items-start gap-3 px-3 py-2.5 rounded-lg border {{ $checked ? 'border-primary bg-primary-soft-2/60' : 'border-outline-variant/60 hover:bg-surface-low' }} cursor-pointer transition-colors">
                    <input type="checkbox" name="fiscal_profiles[]" value="{{ $profile->id }}" {{ $checked ? 'checked' : '' }}
                           class="mt-1 h-4 w-4 rounded border-outline-variant text-primary focus:ring-primary">
                    <span class="flex-1">
                        <span class="block text-[13.5px] font-semibold text-on-surface">{{ $profile->name }}</span>
                        @if($profile->tax_id)
                            <span class="block text-[11.5px] text-on-surface-variant">{{ $profile->tax_id }}</span>
                        @endif
                    </span>
                </label>
            @empty
                <p class="text-[12.5px] text-on-surface-variant italic">No hay empresas activas disponibles.</p>
            @endforelse
        </div>
    </section>

    {{-- Acciones --}}
    <div class="lg:col-span-3 flex items-center justify-end gap-3 pt-2">
        <a class="inline-flex items-center gap-2 bg-white border border-outline-variant text-on-surface font-semibold text-[13px] rounded-lg px-4 py-2.5 hover:bg-surface-low"
           href="{{ route('web.users.index') }}">
            Cancelar
        </a>
        <button type="submit"
                class="inline-flex items-center gap-2 bg-primary hover:bg-primary-hover transition-colors text-white font-semibold text-[13px] rounded-lg px-4 py-2.5 shadow-sm">
            <i data-lucide="check" class="w-4 h-4"></i>
            {{ $user->exists ? 'Guardar cambios' : 'Crear usuario' }}
        </button>
    </div>
</form>
@endsection
