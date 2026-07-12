@extends('layouts.app')

@section('title', 'Iniciar sesión')

@section('head')
<style>
    .pw-wrap { position: relative; }
    .pw-wrap input { padding-right: 78px; }
    .pw-toggle {
        position: absolute; top: 50%; right: 6px; transform: translateY(-50%);
        background: none; border: 0; cursor: pointer; padding: 4px 8px;
        font: inherit; font-size: 12px; font-weight: 600; color: var(--primary);
    }
    .pw-toggle:hover { text-decoration: underline; }
    .pw-toggle:focus-visible { outline: 2px solid var(--primary); border-radius: 6px; }
</style>
@endsection

@section('content')
<div class="login">
    <section class="login-hero">
        <div class="brand">FacturaPro</div>
        <h1 style="font-size:42px;line-height:1.05;margin:28px 0 16px">Facturacion clara, controlada y lista para crecer.</h1>
        <p class="muted" style="font-size:17px;max-width:520px">Panel administrativo para crear facturas, gestionar clientes y preparar la operacion web y movil desde una misma fuente de verdad.</p>
    </section>
    <section class="login-card">
        <form method="POST" action="{{ route('login.store', [], false) }}" class="card">
            @csrf
            <h2 style="margin-top:0">Acceso</h2>
            @if($errors->any())<div class="alert error">{{ $errors->first() }}</div>@endif
            <div class="form">
                <div class="field"><label>Correo electrónico</label><input name="email" type="email" value="{{ old('email') }}" required autofocus></div>
                <div class="field">
                    <label for="password">Contraseña</label>
                    <div class="pw-wrap">
                        <input id="password" name="password" type="password" required>
                        <button type="button" class="pw-toggle" data-target="password"
                                aria-label="Mostrar contraseña" aria-pressed="false">Mostrar</button>
                    </div>
                </div>
                <label><input name="remember" type="checkbox" value="1"> Recordarme</label>
                <button class="btn primary" type="submit">Entrar</button>
            </div>
        </form>
    </section>
</div>
@endsection

@section('scripts')
<script>
    document.querySelectorAll('.pw-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.getElementById(btn.dataset.target);
            if (!input) return;
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.textContent = show ? 'Ocultar' : 'Mostrar';
            btn.setAttribute('aria-pressed', show ? 'true' : 'false');
            btn.setAttribute('aria-label', show ? 'Ocultar contraseña' : 'Mostrar contraseña');
            input.focus();
        });
    });
</script>
@endsection
