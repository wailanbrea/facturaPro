@extends('layouts.app')

@section('title', 'Editar informe')
@section('subtitle', $report->report_number.' · '.\App\Support\TechnicalReportStatusLabel::label($report->status))

@section('content')
@include('technical-reports.form')
@endsection
