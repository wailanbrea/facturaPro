@extends('layouts.app')

@section('title', 'Editar cita')
@section('subtitle', $appointment->title)

@section('content')
<form method="POST" action="{{ route('web.appointments.update', $appointment) }}" class="form">
    @csrf @method('PUT')
    <div class="invoice-grid">
        <section class="card form">
            <h3>Datos de la cita</h3>
            <div class="fields">
                <div class="field span-2">
                    <label>Título *</label>
                    <input name="title" type="text" value="{{ old('title', $appointment->title) }}" required>
                </div>
                <div class="field">
                    <label>Inicio *</label>
                    <input name="start_at" type="datetime-local"
                           value="{{ old('start_at', $appointment->start_at->format('Y-m-d\TH:i')) }}" required>
                </div>
                <div class="field">
                    <label>Fin *</label>
                    <input name="end_at" type="datetime-local"
                           value="{{ old('end_at', $appointment->end_at->format('Y-m-d\TH:i')) }}" required>
                </div>
                <div class="field">
                    <label>Cliente registrado</label>
                    <select name="client_id">
                        <option value="">Sin cliente registrado</option>
                        @foreach($clients as $client)
                            <option value="{{ $client->id }}" @selected((int) old('client_id', $appointment->client_id) === $client->id)>{{ $client->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Nombre del cliente <span class="text-on-surface-variant font-normal">(sin registrarlo)</span></label>
                    <input name="client_name" type="text"
                           value="{{ old('client_name', $appointment->client_id ? '' : $appointment->client_name) }}"
                           placeholder="Escribe el nombre directamente…">
                </div>
                <div class="field">
                    <label>Estado</label>
                    <select name="status">
                        @foreach(\App\Models\Appointment::STATUS_LABELS as $key => $label)
                            <option value="{{ $key }}" @selected(old('status', $appointment->status) === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                @include('appointments._location-picker', ['appointment' => $appointment])
                <div class="field span-2">
                    <label>Servicio a realizar</label>
                    <textarea name="service_description">{{ old('service_description', $appointment->service_description) }}</textarea>
                </div>
                <div class="field span-2">
                    <label>Observaciones</label>
                    <textarea name="observations">{{ old('observations', $appointment->observations) }}</textarea>
                </div>
            </div>

            <div class="mt-4">
                <div class="flex items-center justify-between mb-2">
                    <h3 style="margin:0">Contactos</h3>
                    <button type="button" onclick="addContact()" class="btn" style="padding:6px 12px;font-size:12px">
                        <i data-lucide="plus" class="w-3.5 h-3.5"></i> Agregar
                    </button>
                </div>
                <div id="contacts-list">
                    @php $existingContacts = old('contacts', $appointment->contacts ?? []); @endphp
                    @foreach($existingContacts as $i => $contact)
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
            <h3>Guardar cambios</h3>
            <p class="muted text-[13px]">Cualquier usuario con gestión de citas puede editar esta cita.</p>
            <div class="mt-4 space-y-2">
                <button class="btn primary w-full" type="submit">Guardar cambios</button>
                <a class="btn w-full justify-center" href="{{ route('web.appointments.show', $appointment) }}">Cancelar</a>
            </div>
        </aside>
    </div>
</form>

<script>
let contactIdx = {{ count($existingContacts ?? []) }};
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
