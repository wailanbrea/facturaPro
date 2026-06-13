@extends('layouts.app')

@section('title', 'Configuracion')
@section('subtitle', 'Catalogos administrables del sistema')

@section('content')
@php
    $catalogs = [
        'currencies' => ['title' => 'Monedas', 'summary' => $settings['currencies']->count().' registros'],
        'taxes' => ['title' => 'Impuestos', 'summary' => $settings['taxes']->count().' registros'],
        'payment-terms' => ['title' => 'Terminos de pago', 'summary' => $settings['payment_terms']->count().' registros'],
        'warranties' => ['title' => 'Garantias', 'summary' => $settings['warranties']->count().' registros'],
        'bank-accounts' => ['title' => 'Cuentas bancarias', 'summary' => $settings['bank_accounts']->count().' registros'],
        'fiscal-profiles' => ['title' => 'Perfiles fiscales', 'summary' => $settings['fiscal_profiles']->count().' registros'],
        'legal-texts' => ['title' => 'Textos legales', 'summary' => 'Gestion documental'],
        'invoice-number' => ['title' => 'Numeracion', 'summary' => 'Secuencia de facturas'],
        'reports' => ['title' => 'Informes', 'summary' => 'Titulos y numeracion tecnica', 'href' => route('web.settings.reports.edit')],
    ];
@endphp

<div class="grid" style="grid-template-columns:repeat(3,minmax(0,1fr))">
    @foreach($catalogs as $slug => $catalog)
        <section class="card">
            <h3>{{ $catalog['title'] }}</h3>
            <p class="muted">{{ $catalog['summary'] }}</p>
            <a class="btn" href="{{ $catalog['href'] ?? route('web.settings.catalog.index', $slug) }}">Administrar</a>
        </section>
    @endforeach
</div>
@endsection
