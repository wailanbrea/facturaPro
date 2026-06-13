@extends('layouts.app')

@php
    $canEditReports = auth()->user()?->hasPermission('editar_informes');
    $canDownloadReports = auth()->user()?->hasPermission('descargar_informes');
@endphp

@section('title', $report->report_number)
@section('subtitle', $report->recipient_name.' · '.$report->report_date?->toDateString())
@section('actions')
<a class="btn" href="{{ route('web.technical-reports.index') }}">Volver</a>
<a class="btn" href="{{ route('web.technical-reports.preview', $report) }}" target="_blank">Vista previa</a>
@if($canEditReports && $report->status !== 'cancelled')
    <a class="btn" href="{{ route('web.technical-reports.edit', $report) }}">Editar</a>
@endif
@if($canDownloadReports && $report->status !== 'cancelled')
    <form method="POST" action="{{ route('web.technical-reports.generate-pdf', $report) }}">
        @csrf
        <button class="btn primary" type="submit">{{ $report->pdf_path ? 'Regenerar PDF' : 'Generar PDF' }}</button>
    </form>
@endif
@if($canDownloadReports && $report->pdf_path)
    <a class="btn" href="{{ route('web.technical-reports.download-pdf', $report) }}">Descargar PDF</a>
@endif
@endsection

@section('content')
<div class="invoice-grid">
    <section class="card">
        <div class="actions" style="justify-content:space-between;margin-bottom:18px">
            <div>
                <h2 style="margin:0;color:var(--primary)">INFORME</h2>
                <div class="muted">Estado: <span class="status {{ $report->status }}">{{ \App\Support\TechnicalReportStatusLabel::label($report->status) }}</span></div>
            </div>
            <div class="right">
                <strong>{{ $report->report_number }}</strong><br>
                <span class="muted">{{ $report->report_date?->toDateString() }}</span>
            </div>
        </div>

        <div class="grid" style="grid-template-columns:1fr 1fr;gap:18px;margin-bottom:22px">
            <div>
                <strong>Destinatario</strong><br>
                {{ $report->recipient_name }}<br>
                <span class="muted">{{ $report->recipient_tax_id }}<br>{{ $report->recipient_address }}</span>
            </div>
            <div>
                <strong>Emisor</strong><br>
                {{ $report->seller_name }}<br>
                <span class="muted">{{ $report->seller_tax_id }}<br>{{ trim($report->seller_address.' '.$report->seller_city) }}</span>
            </div>
        </div>

        @if($report->intro_text)
            <p style="white-space:pre-line">{{ $report->intro_text }}</p>
        @endif

        @foreach([1, 2, 3, 4] as $section)
            <section style="margin-top:22px">
                <h3 style="margin-bottom:8px">{{ $report->{'section_'.$section.'_title'} }}</h3>
                <p class="muted" style="white-space:pre-line">{{ $report->{'section_'.$section.'_content'} ?: 'Sin contenido.' }}</p>
            </section>
        @endforeach

        @if($report->final_text)
            <p style="margin-top:22px;white-space:pre-line">{{ $report->final_text }}</p>
        @endif
    </section>

    <aside class="card">
        <h3>Detalle</h3>
        <table class="table">
            <tr><td>Numero</td><td class="right">{{ $report->report_number }}</td></tr>
            <tr><td>Fecha</td><td class="right">{{ $report->report_date?->toDateString() }}</td></tr>
            <tr><td>Estado</td><td class="right">{{ \App\Support\TechnicalReportStatusLabel::label($report->status) }}</td></tr>
            <tr><td>Creado por</td><td class="right">{{ $report->createdBy?->name ?? 'Sistema' }}</td></tr>
            <tr><td>Creado</td><td class="right">{{ $report->created_at?->format('Y-m-d H:i') }}</td></tr>
        </table>

        <h3 style="margin-top:22px">PDF</h3>
        @if($report->pdf_path)
            <p class="muted">PDF final disponible.</p>
            <a class="btn" href="{{ route('web.technical-reports.download-pdf', $report) }}">Descargar PDF</a>
        @else
            <p class="muted">Todavia no se ha generado el PDF final.</p>
        @endif

        @if($report->notes)
            <h3 style="margin-top:22px">Notas internas</h3>
            <p class="muted" style="white-space:pre-line">{{ $report->notes }}</p>
        @endif
    </aside>
</div>
@endsection
