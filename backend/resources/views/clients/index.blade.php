@extends('layouts.app')

@section('title', 'Clientes')
@section('subtitle', 'Gestion de clientes y datos fiscales')
@section('actions')
<a class="btn primary" href="{{ route('web.clients.create') }}">Nuevo cliente</a>
@endsection

@section('content')
<form method="GET" class="actions" style="margin-bottom:16px">
    <input name="search" value="{{ request('search') }}" placeholder="Buscar por nombre, correo o RNC" style="min-width:320px;border:1px solid var(--line);border-radius:5px;padding:10px">
    <button class="btn" type="submit">Buscar</button>
</form>
<table class="table">
    <thead><tr><th>Nombre</th><th>RNC / ID Fiscal</th><th>Correo</th><th>Teléfono</th><th>Estado</th><th></th></tr></thead>
    <tbody>
    @forelse($clients as $client)
        <tr>
            <td>{{ $client->name }}</td>
            <td>{{ $client->tax_id }}</td>
            <td>{{ $client->email }}</td>
            <td>{{ $client->phone }}</td>
            <td>{{ $client->is_active ? 'Activo' : 'Inactivo' }}</td>
            <td class="right actions">
                <a class="btn" href="{{ route('web.clients.edit', $client) }}">Editar</a>
                <form method="POST" action="{{ route('web.clients.destroy', $client) }}">@csrf @method('DELETE')<button class="btn danger" type="submit">Eliminar</button></form>
            </td>
        </tr>
    @empty
        <tr><td colspan="6" class="muted">Sin clientes.</td></tr>
    @endforelse
    </tbody>
</table>
<div class="pagination">{{ $clients->links() }}</div>
@endsection
