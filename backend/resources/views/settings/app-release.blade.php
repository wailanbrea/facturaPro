@extends('layouts.app')

@section('title', 'App Android (APK)')
@section('subtitle', 'Publicación de versiones para actualización directa desde la aplicación móvil')

@section('actions')
<a class="btn" href="{{ route('web.settings.index') }}">Volver a configuración</a>
@endsection

@section('content')
<div class="space-y-6" style="max-width:680px">
    <section class="card">
        <h3>Versión activa actual</h3>
        <div class="space-y-2 mt-3 text-[14px]">
            <div><strong>Versión:</strong> {{ $release['version_name'] }} (código {{ $release['version_code'] }})</div>
            @if(!empty($release['published_at']))
                <div><strong>Fecha de publicación:</strong> {{ $release['published_at'] }}</div>
            @endif
            @if(!empty($release['release_notes']))
                <div><strong>Notas:</strong> {{ $release['release_notes'] }}</div>
            @endif
            @if(!empty($release['download_url']))
                <div class="pt-2">
                    <a class="btn" href="{{ $release['download_url'] }}" target="_blank" rel="noopener">Descargar APK actual</a>
                </div>
            @endif
        </div>
    </section>

    <form method="POST" action="{{ route('web.settings.app-release.update') }}" enctype="multipart/form-data" class="form">
        @csrf
        <section class="card">
            <h3>Publicar nueva APK manualmente</h3>
            <p class="muted" style="margin-bottom:16px">
                También se actualiza automáticamente por Git mediante <code>app_release.json</code>. Si subes una APK con un código de versión mayor aquí, los dispositivos descargarán esta versión directamente.
            </p>
            <div class="fields">
                <div class="field">
                    <label>Código de versión (entero mayor al actual)</label>
                    <input type="number" name="version_code" min="1" value="{{ ((int) ($release['version_code'] ?? 1)) + 1 }}" required>
                </div>
                <div class="field">
                    <label>Nombre de versión (ej. 1.2.0)</label>
                    <input type="text" name="version_name" maxlength="50" placeholder="1.2.0" required>
                </div>
                <div class="field span-2">
                    <label>Notas de la versión</label>
                    <textarea name="release_notes" rows="3" maxlength="1000" placeholder="Mejoras incluidas en esta actualización..."></textarea>
                </div>
                <div class="field span-2">
                    <label>Archivo APK (.apk)</label>
                    <input type="file" name="apk_file" accept=".apk,application/vnd.android.package-archive" required>
                </div>
            </div>
            <div class="actions" style="margin-top:18px">
                <button class="btn primary" type="submit">Subir y publicar APK</button>
            </div>
        </section>
    </form>
</div>
@endsection
