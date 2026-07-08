@extends('layouts.app')

@section('title', 'Nueva cita')
@section('subtitle', 'Agendar cita en el calendario compartido')

@section('content')
<form method="POST" action="{{ route('web.appointments.store') }}" class="form">
    @csrf
    <div class="invoice-grid">
        <section class="card form">
            <h3>Datos de la cita</h3>
            <div class="fields">
                <div class="field span-2">
                    <label>Título *</label>
                    <input name="title" type="text" value="{{ old('title') }}" required placeholder="Ej: Revisión equipo cliente X">
                </div>
                <div class="field">
                    <label>Inicio *</label>
                    <input name="start_at" type="datetime-local"
                           value="{{ old('start_at', $defaultDate ? $defaultDate.'T09:00' : '') }}" required>
                </div>
                <div class="field">
                    <label>Fin *</label>
                    <input name="end_at" type="datetime-local"
                           value="{{ old('end_at', $defaultDate ? $defaultDate.'T10:00' : '') }}" required>
                </div>
                <div class="field">
                    <label>Cliente registrado</label>
                    <select name="client_id">
                        <option value="">Sin cliente registrado</option>
                        @foreach($clients as $client)
                            <option value="{{ $client->id }}" @selected((int) old('client_id') === $client->id)>{{ $client->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Nombre del cliente <span class="text-on-surface-variant font-normal">(sin registrarlo)</span></label>
                    <input name="client_name" type="text" value="{{ old('client_name') }}"
                           placeholder="Escribe el nombre directamente…">
                    <p class="muted" style="font-size:12px;margin:6px 0 0">No hace falta crear el cliente antes: escribe aquí su nombre y añade su teléfono o correo en Contactos.</p>
                </div>
                <div class="field">
                    <label>Estado inicial</label>
                    <select name="status">
                        @foreach(\App\Models\Appointment::STATUS_LABELS as $key => $label)
                            <option value="{{ $key }}" @selected(old('status', 'pending') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                @include('appointments._location-picker', ['appointment' => null])
                <div class="field span-2">
                    <label>Servicio a realizar</label>
                    <textarea name="service_description" placeholder="Describe el trabajo a realizar...">{{ old('service_description') }}</textarea>
                </div>
                <div class="field span-2">
                    <label>Observaciones</label>
                    <textarea name="observations">{{ old('observations') }}</textarea>
                </div>
            </div>

            {{-- Contacts --}}
            <div class="mt-4">
                <div class="flex items-center justify-between mb-2">
                    <h3 style="margin:0">Contactos</h3>
                    <button type="button" onclick="addContact()" class="btn" style="padding:6px 12px;font-size:12px">
                        <i data-lucide="plus" class="w-3.5 h-3.5"></i> Agregar
                    </button>
                </div>
                <div id="contacts-list">
                    @foreach(old('contacts', []) as $i => $contact)
                    <div class="contact-row fields" style="margin-bottom:10px">
                        <div class="field"><label>Nombre</label><input name="contacts[{{ $i }}][name]" value="{{ $contact['name'] ?? '' }}"></div>
                        <div class="field"><label>Teléfono</label><input name="contacts[{{ $i }}][phone]" value="{{ $contact['phone'] ?? '' }}"></div>
                        <div class="field"><label>Correo</label><input name="contacts[{{ $i }}][email]" type="email" value="{{ $contact['email'] ?? '' }}"></div>
                        <div class="field" style="display:flex;align-items:flex-end"><button type="button" class="btn danger" onclick="this.closest('.contact-row').remove()">Quitar</button></div>
                    </div>
                    @endforeach
                </div>
            </div>
        </section>

        <aside class="card">
            <h3>Resumen</h3>
            <p class="muted text-[13px]">
                Cualquier usuario con gestión de citas puede actualizar esta cita después de crearla.
                Las notificaciones push se envían automáticamente a los usuarios con acceso al calendario.
            </p>
            <div class="mt-4 space-y-2">
                <button class="btn primary w-full" type="submit">Agendar cita</button>
                <a class="btn w-full justify-center" href="{{ route('web.appointments.index') }}">Cancelar</a>
            </div>
        </aside>
    </div>
</form>

<script>
let contactIdx = {{ count(old('contacts', [])) }};
function addContact() {
    document.getElementById('contacts-list').insertAdjacentHTML('beforeend', `
    <div class="contact-row fields" style="margin-bottom:10px">
        <div class="field"><label>Nombre</label><input name="contacts[${contactIdx}][name]"></div>
        <div class="field"><label>Teléfono</label><input name="contacts[${contactIdx}][phone]"></div>
        <div class="field"><label>Correo</label><input name="contacts[${contactIdx}][email]" type="email"></div>
        <div class="field" style="display:flex;align-items:flex-end"><button type="button" class="btn danger" onclick="this.closest('.contact-row').remove()">Quitar</button></div>
    </div>`);
    contactIdx++;
    if (window.lucide) window.lucide.createIcons();
}
</script>
@endsection
