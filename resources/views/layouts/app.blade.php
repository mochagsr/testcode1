<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'PgPOS ERP')</title>
    <style>
        :root {
            --bg: #f4f4f4;
            --card: #ffffff;
            --text: #111111;
            --muted: #666666;
            --border: #dddddd;
            --accent: #111111;
            --btn-primary-bg: #111111;
            --btn-primary-text: #ffffff;
            --btn-secondary-bg: #ffffff;
            --btn-secondary-text: #111111;
            --alert-success-bg: #effaf0;
            --alert-success-border: #9bd3a1;
            --alert-success-text: #12301a;
            --alert-error-bg: #fff0f0;
            --alert-error-border: #f1a5a5;
            --alert-error-text: #3a1515;
            --badge-neutral-bg: #eceff3;
            --badge-neutral-text: #243447;
            --badge-success-bg: #e8f7ed;
            --badge-success-text: #1f6b3d;
            --badge-warning-bg: #fff4dc;
            --badge-warning-text: #7a4b00;
            --badge-danger-bg: #ffe7e7;
            --badge-danger-text: #8d1f1f;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, sans-serif;
            background: var(--bg);
            color: var(--text);
        }
        .wrap {
            display: grid;
            grid-template-columns: 220px 1fr;
            min-height: 100vh;
        }
        .sidebar {
            background: #111111;
            color: #ffffff;
            padding: 24px 16px;
        }
        .brand {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 24px;
        }
        .nav a {
            display: block;
            color: #ffffff;
            text-decoration: none;
            padding: 10px 12px;
            border-radius: 8px;
            margin-bottom: 6px;
        }
        .nav a.active, .nav a:hover {
            background: #2a2a2a;
        }
        .nav-group {
            margin-bottom: 8px;
        }
        .nav-section-title {
            display: block;
            margin: 12px 12px 6px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #bdbdbd;
            font-weight: 700;
        }
        .nav-group-title {
            display: block;
            color: #ffffff;
            padding: 10px 12px 6px;
            border-radius: 8px;
            font-weight: 700;
        }
        .nav-group-title.active {
            background: #2a2a2a;
        }
        .nav-sub a {
            margin-bottom: 4px;
            margin-left: 10px;
            padding: 8px 10px;
            font-size: 13px;
            color: #e6e6e6;
        }
        .main {
            padding: 24px;
        }
        .page-title {
            margin: 0 0 16px;
            font-size: 24px;
        }
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 16px;
        }
        .form-section {
            border: 1px dashed var(--border);
            border-radius: 10px;
            padding: 12px;
            margin-bottom: 12px;
            background: color-mix(in srgb, var(--card) 94%, var(--bg));
        }
        .form-section-title {
            margin: 0 0 4px;
            font-size: 14px;
            font-weight: 700;
            color: var(--text);
        }
        .form-section-note {
            margin: 0 0 8px;
            font-size: 12px;
            color: var(--muted);
        }
        .label-required {
            color: #c0392b;
            margin-left: 2px;
            font-weight: 700;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
        }
        .stat {
            padding: 14px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: #fff;
        }
        .stat-label {
            color: var(--muted);
            font-size: 12px;
            margin-bottom: 6px;
        }
        .stat-value {
            font-size: 22px;
            font-weight: 700;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            text-align: left;
            border-bottom: 1px solid var(--border);
            padding: 10px 8px;
            vertical-align: top;
        }
        th {
            font-size: 12px;
            text-transform: uppercase;
            color: var(--muted);
        }
        input, select, textarea {
            width: 100%;
            padding: 9px 10px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font: inherit;
            background: #fff;
        }
        input[type="number"]::-webkit-outer-spin-button,
        input[type="number"]::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        input[type="number"] {
            -moz-appearance: textfield;
            appearance: textfield;
        }
        /* Form fields: vertical stack, 10px spacing */
        form .row {
            display: block;
        }
        form .row.inline {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 10px;
        }
        form .row > [class^="col-"] {
            margin-bottom: 10px;
        }
        form .row.inline > [class^="col-"] {
            margin-bottom: 0;
        }
        form .row > [class^="col-"] > label {
            display: block;
            margin-bottom: 6px;
            font-size: 13px;
            font-weight: 600;
            color: var(--muted);
            letter-spacing: 0.2px;
        }
        form .row > [class^="col-"] > input[type="text"],
        form .row > [class^="col-"] > input[type="email"],
        form .row > [class^="col-"] > input[type="password"],
        form .row > [class^="col-"] > input[type="number"],
        form .row > [class^="col-"] > input[type="date"],
        form .row > [class^="col-"] > input[type="file"],
        form .row > [class^="col-"] > select,
        form .row > [class^="col-"] > textarea {
            max-width: 320px;
        }
        /* Precision sizing by data type for better form readability */
        form .row input[name$="code"],
        form .row input[name="code"] {
            max-width: 220px !important;
        }
        form .row input[name$="name"],
        form .row input[name="name"] {
            max-width: 380px !important;
        }
        form .row input[name$="email"] {
            max-width: 380px !important;
        }
        form .row input[name$="phone"],
        form .row input[name="city"] {
            max-width: 260px !important;
        }
        form .row input[name$="stock"],
        form .row input[name$="price"],
        form .row input[name$="quantity"],
        form .row input[name$="receivable"] {
            max-width: 180px !important;
        }
        form .row input[name$="date"] {
            max-width: 220px !important;
        }
        form .row > [class^="col-"] > textarea {
            max-width: 560px;
            min-height: 88px;
        }
        form .row > [class^="col-"] > input:focus,
        form .row > [class^="col-"] > select:focus,
        form .row > [class^="col-"] > textarea:focus {
            outline: none;
            border-color: #777;
            box-shadow: 0 0 0 2px rgba(120, 120, 120, 0.15);
        }
        /* Optional utility widths for future forms */
        .w-xs { max-width: 180px !important; }
        .w-sm { max-width: 260px !important; }
        .w-md { max-width: 380px !important; }
        .w-lg { max-width: 560px !important; }
        form > .btn,
        form > .btn.secondary {
            margin-right: 6px;
        }
        button,
        .btn,
        input[type="submit"],
        input[type="button"] {
            background: var(--btn-primary-bg);
            color: var(--btn-primary-text);
            text-decoration: none;
            text-align: center;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid transparent;
            cursor: pointer;
            width: auto;
            min-height: 38px;
            padding: 9px 14px;
            font-family: "Trebuchet MS", "Segoe UI", Tahoma, sans-serif;
            font-weight: 600;
            letter-spacing: 0.2px;
            line-height: 1;
            border-radius: 8px;
            white-space: nowrap;
            vertical-align: middle;
        }
        button.secondary,
        .btn.secondary,
        input[type="submit"].secondary,
        input[type="button"].secondary {
            background: var(--btn-secondary-bg);
            color: var(--btn-secondary-text);
            border: 1px solid var(--border);
        }
        td .flex .btn,
        td .flex button {
            min-width: 72px;
        }
        .row {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 10px;
        }
        .col-3 { grid-column: span 3; }
        .col-4 { grid-column: span 4; }
        .col-6 { grid-column: span 6; }
        .col-8 { grid-column: span 8; }
        .col-12 { grid-column: span 12; }
        .alert {
            padding: 10px 12px;
            border-radius: 8px;
            margin-bottom: 12px;
            border: 1px solid;
        }
        .alert.success {
            background: var(--alert-success-bg);
            border-color: var(--alert-success-border);
            color: var(--alert-success-text);
        }
        .alert.error {
            background: var(--alert-error-bg);
            border-color: var(--alert-error-border);
            color: var(--alert-error-text);
        }
        .muted { color: var(--muted); }
        .flex {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }
        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            padding: 4px 10px;
            font-size: 11px;
            font-weight: 700;
            line-height: 1.2;
            white-space: nowrap;
            background: var(--badge-neutral-bg);
            color: var(--badge-neutral-text);
            border: 1px solid transparent;
        }
        .badge.success {
            background: var(--badge-success-bg);
            color: var(--badge-success-text);
        }
        .badge.warning {
            background: var(--badge-warning-bg);
            color: var(--badge-warning-text);
        }
        .badge.danger {
            background: var(--badge-danger-bg);
            color: var(--badge-danger-text);
        }
        .action-menu {
            width: auto;
            min-width: 120px;
            max-width: 140px;
            padding: 7px 8px;
            font-size: 13px;
        }
        .action-menu-sm {
            max-width: 130px;
        }
        .action-menu-md {
            max-width: 180px;
        }
        .action-menu-lg {
            max-width: 190px;
        }
        @media (max-width: 900px) {
            .wrap { grid-template-columns: 1fr; }
            .sidebar { position: sticky; top: 0; z-index: 10; }
            .col-3, .col-4, .col-6, .col-8, .col-12 { grid-column: span 12; }
            form .row > [class^="col-"] > input,
            form .row > [class^="col-"] > select,
            form .row > [class^="col-"] > textarea {
                max-width: 100%;
            }
            .form-section {
                padding: 10px;
            }
        }
    </style>
</head>
@php
    $isDark = auth()->check() && auth()->user()->theme === 'dark';
@endphp
<body @if($isDark) style="--bg:#111;--card:#1b1b1b;--text:#f2f2f2;--muted:#b3b3b3;--border:#333;--accent:#fff;--btn-primary-bg:#f2f2f2;--btn-primary-text:#111111;--btn-secondary-bg:#1b1b1b;--btn-secondary-text:#f2f2f2;--alert-success-bg:#0f2a18;--alert-success-border:#2f7f47;--alert-success-text:#d8f6e1;--alert-error-bg:#2d1212;--alert-error-border:#8e3333;--alert-error-text:#ffdede;--badge-neutral-bg:#2b2f36;--badge-neutral-text:#d8dee9;--badge-success-bg:#143621;--badge-success-text:#bde8cb;--badge-warning-bg:#3d2f14;--badge-warning-text:#f6d98f;--badge-danger-bg:#4b1f1f;--badge-danger-text:#ffd2d2;" @endif>
<div class="wrap">
    <aside class="sidebar">
        <div class="brand">PgPOS ERP</div>
        <nav class="nav">
            <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">{{ __('menu.dashboard') }}</a>
            @auth
                @if(auth()->user()->role === 'admin')
                    <span class="nav-section-title">{{ __('ui.nav_master_data') }}</span>
                    <div class="nav-group">
                        <span class="nav-group-title {{ request()->routeIs('item-categories.*') || request()->routeIs('products.*') ? 'active' : '' }}">{{ __('ui.nav_items_group') }}</span>
                        <div class="nav-sub">
                            <a href="{{ route('item-categories.index') }}" class="{{ request()->routeIs('item-categories.*') ? 'active' : '' }}">{{ __('menu.item_categories') }}</a>
                            <a href="{{ route('products.index') }}" class="{{ request()->routeIs('products.*') ? 'active' : '' }}">{{ __('menu.items') }}</a>
                        </div>
                    </div>
                    <div class="nav-group">
                        <span class="nav-group-title {{ request()->routeIs('customer-levels-web.*') || request()->routeIs('customers-web.*') ? 'active' : '' }}">{{ __('ui.nav_customers_group') }}</span>
                        <div class="nav-sub">
                            <a href="{{ route('customer-levels-web.index') }}" class="{{ request()->routeIs('customer-levels-web.*') ? 'active' : '' }}">{{ __('menu.customer_levels') }}</a>
                            <a href="{{ route('customers-web.index') }}" class="{{ request()->routeIs('customers-web.*') ? 'active' : '' }}">{{ __('menu.customers') }}</a>
                        </div>
                    </div>
                @endif
            @endauth
            <span class="nav-section-title">{{ __('ui.nav_transactions') }}</span>
            <div class="nav-sub">
                <a href="{{ route('sales-invoices.index') }}" class="{{ request()->routeIs('sales-invoices.*') ? 'active' : '' }}">{{ __('menu.sales_invoices') }}</a>
                <a href="{{ route('sales-returns.index') }}" class="{{ request()->routeIs('sales-returns.*') ? 'active' : '' }}">{{ __('menu.sales_returns') }}</a>
                <a href="{{ route('delivery-notes.index') }}" class="{{ request()->routeIs('delivery-notes.*') ? 'active' : '' }}">{{ __('menu.delivery_notes') }}</a>
                <a href="{{ route('order-notes.index') }}" class="{{ request()->routeIs('order-notes.*') ? 'active' : '' }}">{{ __('menu.order_notes') }}</a>
            </div>
            <div class="nav-group">
                <span class="nav-group-title {{ request()->routeIs('receivables.*') || request()->routeIs('receivable-payments.*') ? 'active' : '' }}">{{ __('menu.receivables') }}</span>
                <div class="nav-sub">
                    <a href="{{ route('receivables.index') }}" class="{{ request()->routeIs('receivables.*') ? 'active' : '' }}">{{ __('menu.receivable_ledger') }}</a>
                    <a href="{{ route('receivable-payments.index') }}" class="{{ request()->routeIs('receivable-payments.*') ? 'active' : '' }}">{{ __('menu.receivable_payments') }}</a>
                </div>
            </div>
            <span class="nav-section-title">{{ __('ui.nav_reports') }}</span>
            <div class="nav-sub">
                <a href="{{ route('reports.index') }}" class="{{ request()->routeIs('reports.*') ? 'active' : '' }}">{{ __('menu.reports') }}</a>
            </div>
            @auth
                <span class="nav-section-title">{{ __('ui.nav_system') }}</span>
                <div class="nav-sub">
                @if(auth()->user()->role === 'admin')
                    <a href="{{ route('users.index') }}" class="{{ request()->routeIs('users.*') ? 'active' : '' }}">{{ __('menu.users') }}</a>
                    <a href="{{ route('audit-logs.index') }}" class="{{ request()->routeIs('audit-logs.*') ? 'active' : '' }}">{{ __('ui.audit_logs') }}</a>
                @endif
                <a href="{{ route('settings.edit') }}" class="{{ request()->routeIs('settings.*') ? 'active' : '' }}">{{ __('menu.settings') }}</a>
                </div>
                <div style="margin-top: 14px; font-size: 12px; color: #d1d1d1;">
                    {{ __('ui.login') }}: {{ auth()->user()->name }} ({{ auth()->user()->role }})
                </div>
                <form method="post" action="{{ route('logout') }}" style="margin-top: 8px;">
                    @csrf
                    <button type="submit" class="btn secondary" style="width:100%;">{{ __('menu.logout') }}</button>
                </form>
            @else
                <a href="{{ route('login') }}">{{ __('menu.login') }}</a>
            @endauth
        </nav>
    </aside>
    <main class="main">
        @if (session('success'))
            <div class="alert success">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert error">
                <div><strong>{{ __('ui.errors_fix') }}</strong></div>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @if (session('error_popup'))
            <script>
                alert(@json((string) session('error_popup')));
            </script>
        @endif
        @yield('content')
    </main>
</div>
</body>
</html>
