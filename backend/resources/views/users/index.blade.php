@extends('layouts.app')

@section('title', 'Usuarios')
@section('subtitle', 'Accesos administrativos y roles')
@section('actions')
<a class="inline-flex items-center gap-2 bg-primary hover:bg-primary-hover transition-colors text-white font-semibold text-[13px] rounded-lg px-3.5 py-2 shadow-sm" href="{{ route('web.users.create') }}">
    <i data-lucide="user-plus" class="w-4 h-4"></i>
    Nuevo usuario
</a>
@endsection

@section('content')
<section class="bg-white rounded-xl border border-outline-variant/60 shadow-card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-[13.5px]">
            <thead class="bg-surface-low">
                <tr class="text-left text-[11.5px] uppercase tracking-wider text-on-surface-variant">
                    <th class="px-5 py-3 font-semibold">Usuario</th>
                    <th class="px-5 py-3 font-semibold">Correo</th>
                    <th class="px-5 py-3 font-semibold">Roles</th>
                    <th class="px-5 py-3 font-semibold text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-outline-variant/40">
            @forelse($users as $user)
                @php
                    $initials = collect(explode(' ', trim($user->name)))
                        ->filter()
                        ->take(2)
                        ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))
                        ->implode('');
                    $editUrl = route('web.users.edit', $user);
                @endphp
                <tr class="hover:bg-surface-low/70 cursor-pointer transition-colors"
                    onclick="window.location='{{ $editUrl }}'">
                    <td class="px-5 py-3.5">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-lg bg-primary-soft text-primary font-semibold text-[12px] flex items-center justify-center">
                                {{ $initials ?: '?' }}
                            </div>
                            <div>
                                <p class="font-semibold text-on-surface leading-4">{{ $user->name }}</p>
                                <p class="text-[11.5px] text-on-surface-variant leading-3 mt-0.5">ID #{{ $user->id }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-5 py-3.5 text-on-surface-variant">{{ $user->email }}</td>
                    <td class="px-5 py-3.5">
                        @forelse($user->roles as $role)
                            <span class="inline-flex items-center px-2.5 py-0.5 mr-1 rounded-full text-[11px] font-semibold bg-primary-soft-2 text-primary">
                                {{ $role->name }}
                            </span>
                        @empty
                            <span class="text-on-surface-variant/70 text-[12.5px] italic">Sin rol</span>
                        @endforelse
                    </td>
                    <td class="px-5 py-3.5 text-right" onclick="event.stopPropagation()">
                        <a class="inline-flex items-center gap-1 text-[12.5px] font-semibold text-primary hover:underline"
                           href="{{ $editUrl }}">
                            <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                            Editar
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="px-5 py-10 text-center text-on-surface-variant">
                        <i data-lucide="users" class="w-8 h-8 mx-auto mb-2 opacity-50"></i>
                        <p>Aún no hay usuarios registrados.</p>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>
<div class="pagination mt-4">{{ $users->links() }}</div>
@endsection
