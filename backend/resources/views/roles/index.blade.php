@extends('layouts.app')

@section('title', 'Roles')
@section('subtitle', 'Gestion de roles y sus permisos')

@section('content')
<div class="grid gap-4">
@foreach($roles as $role)
    @php
        $isAdmin = $role->slug === 'admin';
    @endphp
    <div class="card" style="padding:20px 24px">
        <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0
                    {{ $isAdmin ? 'bg-danger-soft' : 'bg-primary-soft-2' }}">
                    <i data-lucide="{{ $isAdmin ? 'shield' : 'shield-check' }}"
                       class="w-5 h-5 {{ $isAdmin ? 'text-danger' : 'text-primary' }}"></i>
                </div>
                <div>
                    <div class="font-semibold text-[15px] text-on-surface">{{ $role->name }}</div>
                    <div class="text-[12px] text-on-surface-variant mt-0.5 font-mono">{{ $role->slug }}</div>
                </div>
            </div>

            <div class="flex items-center gap-6">
                <div class="text-center">
                    <div class="text-[22px] font-bold text-primary leading-6">{{ $role->permissions_count }}</div>
                    <div class="text-[11px] text-on-surface-variant uppercase tracking-wide mt-0.5">Permisos</div>
                </div>
                <div class="text-center">
                    <div class="text-[22px] font-bold text-on-surface leading-6">{{ $role->users_count }}</div>
                    <div class="text-[11px] text-on-surface-variant uppercase tracking-wide mt-0.5">Usuarios</div>
                </div>
                <a href="{{ route('web.roles.edit', $role) }}" class="btn primary" style="height:38px;padding:0 18px">
                    <i data-lucide="pencil" class="w-4 h-4"></i>
                    Editar permisos
                </a>
            </div>
        </div>
    </div>
@endforeach
</div>
@endsection
