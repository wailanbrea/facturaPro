@extends('layouts.app')

@section('title', $client->exists ? 'Editar cliente' : 'Nuevo cliente')
@section('subtitle', 'Datos de facturacion del cliente')

@section('content')
<form method="POST" action="{{ $client->exists ? route('web.clients.update', $client) : route('web.clients.store') }}" class="card form">
    @csrf
    @if($client->exists) @method('PUT') @endif
    <div class="fields">
        <div class="field"><label>Nombre</label><input name="name" value="{{ old('name', $client->name) }}" required></div>
        <div class="field"><label>Tax ID / RNC</label><input name="tax_id" value="{{ old('tax_id', $client->tax_id) }}"></div>
        <div class="field"><label>Email</label><input name="email" type="email" value="{{ old('email', $client->email) }}"></div>
        <div class="field"><label>Telefono</label><input name="phone" value="{{ old('phone', $client->phone) }}"></div>
        <div class="field"><label>Ciudad</label><input name="city" value="{{ old('city', $client->city) }}"></div>
        <div class="field"><label>Activo</label><select name="is_active"><option value="1" @selected(old('is_active', $client->is_active ?? true))>Activo</option><option value="0" @selected(! old('is_active', $client->is_active ?? true))>Inactivo</option></select></div>
        <div class="field span-2"><label>Direccion</label><input name="address" value="{{ old('address', $client->address) }}"></div>
        <div class="field span-2"><label>Notas</label><textarea name="notes">{{ old('notes', $client->notes) }}</textarea></div>
    </div>
    <div class="actions">
        <button class="btn primary" type="submit">Guardar</button>
        <a class="btn" href="{{ route('web.clients.index') }}">Cancelar</a>
    </div>
</form>
@endsection
