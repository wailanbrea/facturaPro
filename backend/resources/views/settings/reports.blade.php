@extends('layouts.app')

@section('title', 'Configuracion de informes')
@section('subtitle', 'Titulos predeterminados y numeracion tecnica')
@section('actions')
<a class="btn" href="{{ route('web.settings.index') }}">Volver</a>
@endsection

@section('content')
<form method="POST" action="{{ route('web.settings.reports.update') }}" class="form">
    @csrf
    @method('PUT')

    <section class="card form">
        <h3>Titulos predeterminados</h3>
        <div class="fields">
            @foreach([1, 2, 3, 4] as $section)
                <div class="field span-2">
                    <label>Titulo seccion {{ $section }}</label>
                    <input name="section_{{ $section }}_default_title" value="{{ old('section_'.$section.'_default_title', $setting->{'section_'.$section.'_default_title'}) }}" required>
                </div>
            @endforeach
        </div>
    </section>

    <section class="card form">
        <h3>Textos opcionales</h3>
        <div class="fields">
            <div class="field span-2">
                <label>Texto introductorio</label>
                <textarea name="intro_text">{{ old('intro_text', $setting->intro_text) }}</textarea>
            </div>
            <div class="field span-2">
                <label>Texto final</label>
                <textarea name="final_text">{{ old('final_text', $setting->final_text) }}</textarea>
            </div>
        </div>
    </section>

    <section class="card form">
        <h3>Numeracion</h3>
        <div class="fields">
            <div class="field">
                <label>Prefijo</label>
                <input name="report_prefix" value="{{ old('report_prefix', $setting->report_prefix) }}" required maxlength="20">
            </div>
            <div class="field">
                <label>Proximo numero</label>
                <input name="next_report_number" type="number" min="1" value="{{ old('next_report_number', $setting->next_report_number) }}" required>
            </div>
            <div class="field">
                <label>Longitud con ceros</label>
                <input name="number_length" type="number" min="1" max="10" value="{{ old('number_length', $setting->number_length) }}" required>
            </div>
            <div class="field span-2">
                <label>Vista previa del proximo numero</label>
                <input value="{{ $setting->previewNextNumber() }}" readonly>
            </div>
        </div>
    </section>

    <div class="actions">
        <button class="btn primary" type="submit">Guardar configuracion</button>
    </div>
</form>
@endsection
