<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'FacturaPro')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        surface: '#faf8ff',
                        'surface-low': '#f3f2fe',
                        'surface-mid': '#ededf9',
                        'surface-high': '#e8e7f3',
                        'surface-var': '#e2e1ed',
                        'on-surface': '#1a1b23',
                        'on-surface-variant': '#434655',
                        outline: '#747686',
                        'outline-variant': '#c4c5d7',
                        primary: '#0037b0',
                        'primary-hover': '#1d4ed8',
                        'primary-soft': '#dce1ff',
                        'primary-soft-2': '#eef2ff',
                        secondary: '#505f76',
                        'secondary-soft': '#d0e1fb',
                        'success-soft': '#d1fae5',
                        success: '#047857',
                        'warning-soft': '#fef3c7',
                        warning: '#b45309',
                        'danger-soft': '#fee2e2',
                        danger: '#b42318',
                    },
                    fontFamily: {
                        sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                    },
                    boxShadow: {
                        card: '0 4px 6px -1px rgb(0 0 0 / 0.05), 0 2px 4px -2px rgb(0 0 0 / 0.04)',
                    },
                },
            },
        };
    </script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js" defer></script>
    <style>
        /* Compatibilidad con vistas previas (botones, tablas, badges, formularios legacy) */
        :root{--bg:#faf8ff;--surface:#ffffff;--line:#e2e1ed;--text:#1a1b23;--muted:#434655;--primary:#0037b0;--primary-2:#1d4ed8;--danger:#b42318;--ok:#047857;--warn:#b45309}
        body { font-family: Inter, ui-sans-serif, system-ui, sans-serif; }
        .muted { color: var(--muted); }
        .btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; border-radius:8px; border:1px solid var(--line); padding:9px 14px; background:#fff; font-weight:600; font-size:13px; cursor:pointer; color:var(--text); transition:background-color .15s, border-color .15s; }
        .btn:hover { background:#f3f2fe; }
        .btn.primary { background:var(--primary); border-color:var(--primary); color:#fff; box-shadow:0 1px 2px rgb(0 0 0 / .08); }
        .btn.primary:hover { background:var(--primary-2); border-color:var(--primary-2); }
        .btn.danger { background:#fff1f1; border-color:#fecaca; color:var(--danger); }
        .btn[disabled] { opacity:.55; cursor:not-allowed; }
        .card { background:#fff; border:1px solid var(--line); border-radius:12px; padding:20px; box-shadow:0 4px 6px -1px rgb(0 0 0 / .04), 0 2px 4px -2px rgb(0 0 0 / .03); }
        .card h3 { margin:0 0 14px; font-size:16px; font-weight:600; }
        .kpi-value { font-size:28px; font-weight:700; color:var(--primary); margin-top:8px; letter-spacing:-0.02em; }
        .table { width:100%; border-collapse:collapse; background:#fff; border:1px solid var(--line); border-radius:12px; overflow:hidden; box-shadow:0 4px 6px -1px rgb(0 0 0 / .04); }
        .table th, .table td { padding:14px 16px; border-bottom:1px solid var(--line); text-align:left; vertical-align:middle; font-size:13.5px; }
        .table th { font-size:11.5px; font-weight:600; text-transform:uppercase; letter-spacing:.06em; color:var(--muted); background:#f3f2fe; }
        .table tr:last-child td { border-bottom:0; }
        .table tr:hover td { background:#faf8ff; }
        .right { text-align:right!important; }
        .status { display:inline-flex; border-radius:9999px; padding:3px 10px; font-size:11px; font-weight:700; letter-spacing:.04em; text-transform:uppercase; background:#e2e1ed; color:#1a1b23; }
        .status.draft { background:#e2e1ed; color:#434655; }
        .status.issued { background:#dbeafe; color:#1d4ed8; }
        .status.paid { background:#d1fae5; color:#047857; }
        .status.partially_paid { background:#fef3c7; color:#b45309; }
        .status.accepted { background:#dbeafe; color:#1d4ed8; }
        .status.converted { background:#ede9fe; color:#6d28d9; }
        .status.overdue, .status.cancelled { background:#fee2e2; color:#b42318; }
        .actions { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
        .form { display:grid; gap:16px; }
        .fields { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; }
        .field label { display:block; font-size:13px; font-weight:600; color:#34435c; margin-bottom:6px; }
        .field input, .field select, .field textarea { width:100%; border:1px solid var(--line); border-radius:8px; padding:9px 11px; font:inherit; background:#fff; transition:border-color .15s, box-shadow .15s; }
        .field input:focus, .field select:focus, .field textarea:focus { outline:none; border-color:var(--primary); box-shadow:0 0 0 3px rgb(0 55 176 / .15); }
        .field textarea { min-height:86px; }
        .span-2 { grid-column:span 2; }
        .alert { border:1px solid #c4c5d7; background:#eef2ff; color:#1e3a8a; border-radius:10px; padding:12px 14px; margin-bottom:16px; font-size:13px; }
        .alert.error { border-color:#fecaca; background:#fef2f2; color:#991b1b; }
        .invoice-grid { display:grid; grid-template-columns:1.5fr .9fr; gap:20px; }
        .line-row { display:grid; grid-template-columns:1fr 110px 140px 150px 48px; gap:10px; align-items:end; margin-bottom:10px; }
        .pagination { margin-top:18px; }
        @media (max-width:900px) {
            .fields, .invoice-grid { grid-template-columns:1fr; }
            .span-2 { grid-column:auto; }
            .line-row { grid-template-columns:1fr; }
        }
        /* Login (vista no-auth) */
        .login { min-height:100vh; display:grid; grid-template-columns:1fr 1fr; background:#faf8ff; }
        .login-hero { background:linear-gradient(135deg,#dce1ff,#faf8ff 70%); padding:64px; display:flex; flex-direction:column; justify-content:center; }
        .login-card { display:flex; align-items:center; justify-content:center; padding:32px; background:#fff; }
        .login-card form { width:min(420px,100%); }
        @media (max-width:900px) { .login { grid-template-columns:1fr; } .login-hero { display:none; } }
        .brand { font-size:24px; font-weight:800; color:var(--primary); }
    </style>
    @yield('head')
</head>
<body class="bg-surface text-on-surface font-sans antialiased">
@auth
<div class="flex min-h-screen">
    {{-- Sidebar --}}
    <aside class="hidden lg:flex flex-col w-[280px] bg-white border-r border-outline-variant/60 sticky top-0 h-screen">
        <div class="flex items-center gap-3 px-6 pt-6 pb-8">
            <div class="w-10 h-10 rounded-xl bg-primary text-white flex items-center justify-center shadow-sm">
                <i data-lucide="receipt" class="w-5 h-5"></i>
            </div>
            <div>
                <p class="font-bold text-[17px] leading-5 text-primary">FacturaPro</p>
                <p class="text-[12px] text-on-surface-variant">Facturación</p>
            </div>
        </div>

        <nav class="flex-1 px-3 space-y-1 overflow-y-auto">
            @php
                $nav = [
                    ['route' => 'web.dashboard', 'label' => 'Dashboard', 'icon' => 'layout-dashboard', 'permission' => 'ver_factura'],
                    ['route' => 'web.invoices.index', 'label' => 'Facturas', 'icon' => 'file-text', 'match' => 'web.invoices.*', 'permission' => 'ver_factura'],
                    ['route' => 'web.invoices.verify', 'label' => 'Verificar documento', 'icon' => 'shield-check', 'match' => 'web.invoices.verify', 'permission' => 'ver_factura'],
                    ['route' => 'web.technical-reports.index', 'label' => 'Informes', 'icon' => 'clipboard-list', 'match' => 'web.technical-reports.*', 'permission' => 'ver_informes'],
                    ['route' => 'web.clients.index', 'label' => 'Clientes', 'icon' => 'users', 'match' => 'web.clients.*', 'permission' => 'gestionar_clientes'],
                    ['route' => 'web.appointments.index', 'label' => 'Calendario', 'icon' => 'calendar-days', 'match' => 'web.appointments.*', 'permission' => 'ver_calendario'],
                    ['route' => 'web.reports.index', 'label' => 'Reportes', 'icon' => 'bar-chart-3', 'match' => 'web.reports.*', 'permission' => 'ver_reportes'],
                    ['route' => 'web.users.index', 'label' => 'Usuarios', 'icon' => 'user-cog', 'match' => 'web.users.*', 'permission' => 'gestionar_usuarios'],
                    ['route' => 'web.roles.index', 'label' => 'Roles', 'icon' => 'shield-half', 'match' => 'web.roles.*', 'permission' => 'gestionar_usuarios'],
                    ['route' => 'web.settings.index', 'label' => 'Configuración', 'icon' => 'settings', 'match' => 'web.settings.*', 'permission' => 'configurar_sistema'],
                    ['route' => 'web.audit.index', 'label' => 'Auditoría', 'icon' => 'shield-check', 'match' => 'web.audit.*', 'permission' => 'ver_auditoria'],
                ];
            @endphp
            @foreach($nav as $item)
                @php
                    $match = $item['match'] ?? $item['route'];
                    $active = request()->routeIs($match);
                    $requiredPerm = $item['permission'] ?? null;
                    if ($requiredPerm && !auth()->user()?->hasPermission($requiredPerm)) continue;
                @endphp
                <a href="{{ route($item['route']) }}"
                   class="group flex items-center gap-3 px-3 py-2.5 rounded-lg text-[14px] font-medium transition-colors
                          {{ $active
                              ? 'bg-primary-soft-2 text-primary border-l-[3px] border-primary -ml-[3px] pl-[15px]'
                              : 'text-on-surface-variant hover:bg-surface-low hover:text-on-surface' }}">
                    <i data-lucide="{{ $item['icon'] }}" class="w-[18px] h-[18px] {{ $active ? 'text-primary' : 'text-on-surface-variant group-hover:text-on-surface' }}"></i>
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>

        <div class="px-4 py-4 border-t border-outline-variant/60">
            <form method="POST" action="{{ route('web.logout') }}">
                @csrf
                <button type="submit"
                        class="w-full inline-flex items-center gap-3 px-3 py-2.5 rounded-lg text-[14px] font-medium text-on-surface-variant hover:bg-surface-low hover:text-danger transition-colors">
                    <i data-lucide="log-out" class="w-[18px] h-[18px]"></i>
                    Cerrar sesión
                </button>
            </form>
        </div>
    </aside>

    {{-- Main --}}
    <div class="flex-1 min-w-0 flex flex-col">
        {{-- Topbar --}}
        <header class="sticky top-0 z-20 bg-surface/90 backdrop-blur border-b border-outline-variant/60">
            <div class="flex items-center gap-4 px-4 sm:px-8 h-[68px]">
                <button class="lg:hidden p-2 rounded-lg hover:bg-surface-low" onclick="document.getElementById('mobile-nav').classList.toggle('hidden')">
                    <i data-lucide="menu" class="w-5 h-5"></i>
                </button>

                <div class="hidden md:flex items-center gap-2 bg-white border border-outline-variant/60 rounded-lg px-3 py-2 w-full max-w-md">
                    <i data-lucide="search" class="w-4 h-4 text-on-surface-variant"></i>
                    <input type="text" placeholder="Buscar facturas, clientes…"
                           class="bg-transparent border-0 outline-none focus:ring-0 text-[14px] flex-1 placeholder:text-on-surface-variant/60">
                </div>

                <div class="flex-1 md:hidden">
                    <p class="font-bold text-primary text-[16px]">FacturaPro</p>
                </div>

                <div class="flex items-center gap-2 ml-auto">
                    <button class="p-2 rounded-lg hover:bg-surface-low text-on-surface-variant relative">
                        <i data-lucide="bell" class="w-5 h-5"></i>
                        @if(($overdueCount ?? 0) > 0)
                            <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-danger rounded-full"></span>
                        @endif
                    </button>
                    <button class="p-2 rounded-lg hover:bg-surface-low text-on-surface-variant">
                        <i data-lucide="help-circle" class="w-5 h-5"></i>
                    </button>
                    <div class="flex items-center gap-2 pl-3 ml-1 border-l border-outline-variant/60">
                        <div class="w-9 h-9 rounded-full bg-primary-soft text-primary font-semibold text-[13px] flex items-center justify-center">
                            {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 2)) }}
                        </div>
                        <div class="hidden sm:block">
                            <p class="text-[13px] font-semibold leading-4">{{ auth()->user()->name }}</p>
                            <p class="text-[11px] text-on-surface-variant leading-3">{{ auth()->user()->email }}</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Mobile drawer --}}
            <div id="mobile-nav" class="hidden lg:hidden border-t border-outline-variant/60 bg-white px-4 py-3 space-y-1">
                @foreach($nav as $item)
                    @php
                        $match = $item['match'] ?? $item['route'];
                        $active = request()->routeIs($match);
                        $requiredPerm = $item['permission'] ?? null;
                        if ($requiredPerm && !auth()->user()?->hasPermission($requiredPerm)) continue;
                    @endphp
                    <a href="{{ route($item['route']) }}"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-[14px] font-medium
                              {{ $active ? 'bg-primary-soft-2 text-primary' : 'text-on-surface-variant hover:bg-surface-low' }}">
                        <i data-lucide="{{ $item['icon'] }}" class="w-[18px] h-[18px]"></i>
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </div>
        </header>

        {{-- Page header --}}
        <div class="px-4 sm:px-8 pt-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-2">
                <div>
                    <h1 class="text-[26px] sm:text-[28px] font-bold tracking-tight text-on-surface leading-8">@yield('title')</h1>
                    @hasSection('subtitle')
                        <p class="text-[14px] text-on-surface-variant mt-1">@yield('subtitle')</p>
                    @endif
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    @yield('actions')
                </div>
            </div>
        </div>

        <main class="flex-1 px-4 sm:px-8 pb-12 pt-6">
            @if(session('status'))
                <div class="mb-4 flex items-start gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-[13px] text-emerald-800">
                    <i data-lucide="check-circle-2" class="w-4 h-4 mt-0.5"></i>
                    <p>{{ session('status') }}</p>
                </div>
            @endif
            @if($errors->any())
                <div class="mb-4 flex items-start gap-3 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-[13px] text-rose-800">
                    <i data-lucide="alert-circle" class="w-4 h-4 mt-0.5"></i>
                    <p>{{ $errors->first() }}</p>
                </div>
            @endif
            @yield('content')
        </main>
    </div>
</div>
@else
    @yield('content')
@endauth

<script>
    document.addEventListener('DOMContentLoaded', () => {
        if (window.lucide) window.lucide.createIcons();
    });
</script>
@yield('scripts')
</body>
</html>
