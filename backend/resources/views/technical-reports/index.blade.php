@extends('layouts.app')

@section('title', 'Informes')
@section('subtitle', 'Documentos tecnicos independientes para clientes')
@section('actions')
<a class="btn primary" href="{{ route('web.technical-reports.create') }}">Nuevo informe</a>
@endsection

@section('content')
@php
    $canEditReports = auth()->user()?->hasPermission('editar_informes');
    $canDeleteReports = auth()->user()?->hasPermission('eliminar_informes');
    $canDownloadReports = auth()->user()?->hasPermission('descargar_informes');
@endphp

<form method="GET" class="actions" style="margin-bottom:16px">
    <input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Buscar numero o destinatario" style="min-width:260px;border:1px solid var(--line);border-radius:5px;padding:10px">
    <input name="client" value="{{ $filters['client'] ?? '' }}" placeholder="Cliente" style="min-width:220px;border:1px solid var(--line);border-radius:5px;padding:10px">
    <input name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}" style="border:1px solid var(--line);border-radius:5px;padding:10px">
    <input name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}" style="border:1px solid var(--line);border-radius:5px;padding:10px">
    <select name="status" style="border:1px solid var(--line);border-radius:5px;padding:10px">
        <option value="">Todos los estados</option>
        @foreach($statusOptions as $value => $label)
            <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
        @endforeach
    </select>
    <button class="btn" type="submit">Filtrar</button>
    <a class="btn" href="{{ route('web.technical-reports.index') }}">Limpiar</a>
</form>

<table class="table">
    <thead>
        <tr>
            <th>Numero</th>
            <th>Fecha</th>
            <th>Cliente / destinatario</th>
            <th>Direccion</th>
            <th>Empresa</th>
            <th>Estado</th>
            <th>Creado por</th>
            <th class="right">Acciones</th>
        </tr>
    </thead>
    <tbody>
    @forelse($reports as $report)
        <tr>
            <td>{{ $report->report_number }}</td>
            <td>{{ $report->report_date?->toDateString() }}</td>
            <td>{{ $report->recipient_name }}</td>
            <td>{{ \Illuminate\Support\Str::limit($report->recipient_address, 48) }}</td>
            <td>{{ $report->seller_name }}</td>
            <td><span class="status {{ $report->status }}">{{ \App\Support\TechnicalReportStatusLabel::label($report->status) }}</span></td>
            <td>{{ $report->createdBy?->name ?? 'Sistema' }}</td>
            <td class="right">
                <div class="actions" style="justify-content:flex-end">
                    <a class="btn" href="{{ route('web.technical-reports.show', $report) }}">Ver</a>
                    @if($canEditReports && $report->status !== 'cancelled')
                        <a class="btn" href="{{ route('web.technical-reports.edit', $report) }}">Editar</a>
                    @endif
                    <a class="btn" href="{{ route('web.technical-reports.preview', $report) }}" target="_blank">PDF</a>
                    @if($canDownloadReports && $report->pdf_path)
                        <a class="btn" href="{{ route('web.technical-reports.download-pdf', $report) }}">Descargar</a>
                    @endif
                    @if($canDeleteReports)
                        <form method="POST" action="{{ route('web.technical-reports.destroy', $report) }}" onsubmit="return confirm('Deseas continuar?')">
                            @csrf
                            @method('DELETE')
                            <button class="btn danger" type="submit">{{ $report->status === 'draft' ? 'Eliminar' : 'Anular' }}</button>
                        </form>
                    @endif
                </div>
            </td>
        </tr>
    @empty
        <tr><td colspan="8" class="muted">Sin informes tecnicos.</td></tr>
    @endforelse
    </tbody>
</table>
<div class="pagination">{{ $reports->links() }}</div>
@endsection
