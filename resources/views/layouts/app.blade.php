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
            --alert-increase-bg: #e7f8ee;
            --alert-increase-border: #58a36e;
            --alert-increase-text: #10351e;
            --alert-decrease-bg: #fdecec;
            --alert-decrease-border: #d16363;
            --alert-decrease-text: #4a1212;
            --alert-edit-bg: #fff8da;
            --alert-edit-border: #d6b24a;
            --alert-edit-text: #4d3a00;
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
            border-radius: 10px;
            padding: 2px;
        }
        .nav-group:hover {
            background: rgba(255, 255, 255, 0.04);
        }
        .nav-group.active {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.18);
            padding: 4px;
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
        .nav-group.active .nav-group-title {
            background: #3a3a3a;
        }
        .nav-group.active .nav-sub a {
            color: #f5f5f5;
        }
        .nav-group.active .nav-sub a.active {
            background: #4a4a4a;
            border-left: 3px solid #d9d9d9;
            padding-left: 9px;
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
            transition: opacity 0.2s ease, transform 0.2s ease;
        }
        .alert.success {
            background: var(--alert-success-bg);
            border-color: var(--alert-success-border);
            color: var(--alert-success-text);
        }
        .alert.increase {
            background: var(--alert-increase-bg);
            border-color: var(--alert-increase-border);
            color: var(--alert-increase-text);
        }
        .alert.decrease {
            background: var(--alert-decrease-bg);
            border-color: var(--alert-decrease-border);
            color: var(--alert-decrease-text);
        }
        .alert.edit {
            background: var(--alert-edit-bg);
            border-color: var(--alert-edit-border);
            color: var(--alert-edit-text);
        }
        .alert.error {
            background: var(--alert-error-bg);
            border-color: var(--alert-error-border);
            color: var(--alert-error-text);
        }
        .alert.is-hiding {
            opacity: 0;
            transform: translateY(-4px);
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
        .pagination {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }
        .pagination .page-item {
            list-style: none;
        }
        .pagination .page-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 34px;
            min-height: 34px;
            padding: 6px 10px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--card);
            color: var(--text);
            text-decoration: none;
            font-size: 13px;
            line-height: 1;
        }
        .pagination .page-item.active .page-link {
            background: var(--btn-primary-bg);
            color: var(--btn-primary-text);
            border-color: var(--btn-primary-bg);
            font-weight: 700;
        }
        .pagination .page-item.disabled .page-link {
            opacity: 0.55;
            pointer-events: none;
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
<body @if($isDark) style="--bg:#111;--card:#1b1b1b;--text:#f2f2f2;--muted:#b3b3b3;--border:#333;--accent:#fff;--btn-primary-bg:#f2f2f2;--btn-primary-text:#111111;--btn-secondary-bg:#1b1b1b;--btn-secondary-text:#f2f2f2;--alert-success-bg:#0f2a18;--alert-success-border:#2f7f47;--alert-success-text:#d8f6e1;--alert-increase-bg:#11301f;--alert-increase-border:#4fb06e;--alert-increase-text:#d9ffe7;--alert-decrease-bg:#3a1717;--alert-decrease-border:#d86868;--alert-decrease-text:#ffd9d9;--alert-edit-bg:#3f3415;--alert-edit-border:#d3b25a;--alert-edit-text:#ffedb8;--alert-error-bg:#2d1212;--alert-error-border:#8e3333;--alert-error-text:#ffdede;--badge-neutral-bg:#2b2f36;--badge-neutral-text:#d8dee9;--badge-success-bg:#143621;--badge-success-text:#bde8cb;--badge-warning-bg:#3d2f14;--badge-warning-text:#f6d98f;--badge-danger-bg:#4b1f1f;--badge-danger-text:#ffd2d2;" @endif>
<div class="wrap">
    <aside class="sidebar">
        <div class="brand">PgPOS ERP</div>
        <nav class="nav">
            <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">{{ __('menu.dashboard') }}</a>
            @auth
                @if(auth()->user()->role === 'admin')
                    <span class="nav-section-title">{{ __('ui.nav_master_data') }}</span>
                    <div class="nav-group {{ request()->routeIs('item-categories.*') || request()->routeIs('products.*') ? 'active' : '' }}">
                        <span class="nav-group-title {{ request()->routeIs('item-categories.*') || request()->routeIs('products.*') ? 'active' : '' }}">{{ __('ui.nav_items_group') }}</span>
                        <div class="nav-sub">
                            <a href="{{ route('item-categories.index') }}" class="{{ request()->routeIs('item-categories.*') ? 'active' : '' }}">{{ __('menu.item_categories') }}</a>
                            <a href="{{ route('products.index') }}" class="{{ request()->routeIs('products.*') ? 'active' : '' }}">{{ __('menu.items') }}</a>
                        </div>
                    </div>
                    <div class="nav-group {{ request()->routeIs('customer-levels-web.*') || request()->routeIs('customers-web.*') ? 'active' : '' }}">
                        <span class="nav-group-title {{ request()->routeIs('customer-levels-web.*') || request()->routeIs('customers-web.*') ? 'active' : '' }}">{{ __('ui.nav_customers_group') }}</span>
                        <div class="nav-sub">
                            <a href="{{ route('customer-levels-web.index') }}" class="{{ request()->routeIs('customer-levels-web.*') ? 'active' : '' }}">{{ __('menu.customer_levels') }}</a>
                            <a href="{{ route('customers-web.index') }}" class="{{ request()->routeIs('customers-web.*') ? 'active' : '' }}">{{ __('menu.customers') }}</a>
                        </div>
                    </div>
                @endif
            @endauth
            @auth
                <div class="nav-group {{ request()->routeIs('suppliers.*') || request()->routeIs('outgoing-transactions.*') || request()->routeIs('supplier-payables.*') || request()->routeIs('supplier-stock-cards.*') ? 'active' : '' }}">
                    <span class="nav-group-title {{ request()->routeIs('suppliers.*') || request()->routeIs('outgoing-transactions.*') || request()->routeIs('supplier-payables.*') || request()->routeIs('supplier-stock-cards.*') ? 'active' : '' }}">{{ __('menu.suppliers') }}</span>
                    <div class="nav-sub">
                        @if(auth()->user()->role === 'admin')
                            <a href="{{ route('suppliers.index') }}" class="{{ request()->routeIs('suppliers.*') ? 'active' : '' }}">{{ __('menu.suppliers') }}</a>
                        @endif
                        <a href="{{ route('outgoing-transactions.index') }}" class="{{ request()->routeIs('outgoing-transactions.*') ? 'active' : '' }}">{{ __('menu.outgoing_transactions') }}</a>
                        <a href="{{ route('supplier-payables.index') }}" class="{{ request()->routeIs('supplier-payables.*') ? 'active' : '' }}">{{ __('menu.supplier_payables') }}</a>
                        <a href="{{ route('supplier-stock-cards.index') }}" class="{{ request()->routeIs('supplier-stock-cards.*') ? 'active' : '' }}">{{ __('menu.supplier_stock_cards') }}</a>
                    </div>
                </div>
            @endauth
            @auth
                <div class="nav-group {{ request()->routeIs('customer-ship-locations.*') || request()->routeIs('school-bulk-transactions.*') ? 'active' : '' }}">
                    <span class="nav-group-title {{ request()->routeIs('customer-ship-locations.*') || request()->routeIs('school-bulk-transactions.*') ? 'active' : '' }}">{{ __('menu.school_distribution') }}</span>
                    <div class="nav-sub">
                        <a href="{{ route('customer-ship-locations.index') }}" class="{{ request()->routeIs('customer-ship-locations.*') ? 'active' : '' }}">{{ __('menu.ship_locations') }}</a>
                        <a href="{{ route('school-bulk-transactions.index') }}" class="{{ request()->routeIs('school-bulk-transactions.*') ? 'active' : '' }}">{{ __('menu.school_bulk_transactions') }}</a>
                    </div>
                </div>
            @endauth
            <div class="nav-group {{ request()->routeIs('sales-invoices.*') || request()->routeIs('sales-returns.*') || request()->routeIs('delivery-notes.*') || request()->routeIs('delivery-trips.*') || request()->routeIs('order-notes.*') ? 'active' : '' }}">
                <span class="nav-group-title {{ request()->routeIs('sales-invoices.*') || request()->routeIs('sales-returns.*') || request()->routeIs('delivery-notes.*') || request()->routeIs('delivery-trips.*') || request()->routeIs('order-notes.*') ? 'active' : '' }}">{{ __('ui.nav_transactions') }}</span>
                <div class="nav-sub">
                    <a href="{{ route('sales-invoices.index') }}" class="{{ request()->routeIs('sales-invoices.*') ? 'active' : '' }}">{{ __('menu.sales_invoices') }}</a>
                    <a href="{{ route('sales-returns.index') }}" class="{{ request()->routeIs('sales-returns.*') ? 'active' : '' }}">{{ __('menu.sales_returns') }}</a>
                    <a href="{{ route('delivery-notes.index') }}" class="{{ request()->routeIs('delivery-notes.*') ? 'active' : '' }}">{{ __('menu.delivery_notes') }}</a>
                    <a href="{{ route('delivery-trips.index') }}" class="{{ request()->routeIs('delivery-trips.*') ? 'active' : '' }}">{{ __('menu.delivery_trip_logs') }}</a>
                    <a href="{{ route('order-notes.index') }}" class="{{ request()->routeIs('order-notes.*') ? 'active' : '' }}">{{ __('menu.order_notes') }}</a>
                </div>
            </div>
            <div class="nav-group {{ request()->routeIs('receivables.*') || request()->routeIs('receivable-payments.*') ? 'active' : '' }}">
                <span class="nav-group-title {{ request()->routeIs('receivables.*') || request()->routeIs('receivable-payments.*') ? 'active' : '' }}">{{ __('menu.receivables') }}</span>
                <div class="nav-sub">
                    <a href="{{ route('receivables.index') }}" class="{{ request()->routeIs('receivables.*') ? 'active' : '' }}">{{ __('menu.receivable_ledger') }}</a>
                    <a href="{{ route('receivable-payments.index') }}" class="{{ request()->routeIs('receivable-payments.*') ? 'active' : '' }}">{{ __('menu.receivable_payments') }}</a>
                </div>
            </div>
            <div class="nav-group {{ request()->routeIs('reports.*') ? 'active' : '' }}">
                <span class="nav-group-title {{ request()->routeIs('reports.*') ? 'active' : '' }}">{{ __('ui.nav_reports') }}</span>
                <div class="nav-sub">
                    <a href="{{ route('reports.index') }}" class="{{ request()->routeIs('reports.*') ? 'active' : '' }}">{{ __('menu.reports') }}</a>
                </div>
            </div>
            @auth
                <div class="nav-group {{ request()->routeIs('users.*') || request()->routeIs('audit-logs.*') || request()->routeIs('approvals.*') || request()->routeIs('semester-transactions.*') || request()->routeIs('ops-health.*') || request()->routeIs('settings.*') ? 'active' : '' }}">
                    <span class="nav-group-title {{ request()->routeIs('users.*') || request()->routeIs('audit-logs.*') || request()->routeIs('approvals.*') || request()->routeIs('semester-transactions.*') || request()->routeIs('ops-health.*') || request()->routeIs('settings.*') ? 'active' : '' }}">{{ __('ui.nav_system') }}</span>
                    <div class="nav-sub">
                    @if(auth()->user()->role === 'admin')
                        <a href="{{ route('users.index') }}" class="{{ request()->routeIs('users.*') ? 'active' : '' }}">{{ __('menu.users') }}</a>
                        <a href="{{ route('audit-logs.index') }}" class="{{ request()->routeIs('audit-logs.*') ? 'active' : '' }}">{{ __('ui.audit_logs') }}</a>
                        <a href="{{ route('approvals.index') }}" class="{{ request()->routeIs('approvals.*') ? 'active' : '' }}">Approval</a>
                        <a href="{{ route('semester-transactions.index') }}" class="{{ request()->routeIs('semester-transactions.*') ? 'active' : '' }}">{{ __('menu.semester_transactions') }}</a>
                        <a href="{{ route('ops-health.index') }}" class="{{ request()->routeIs('ops-health.*') ? 'active' : '' }}">Ops Health</a>
                    @endif
                    <a href="{{ route('settings.edit') }}" class="{{ request()->routeIs('settings.*') ? 'active' : '' }}">{{ __('menu.settings') }}</a>
                    </div>
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
        @php
            $successMessage = (string) session('success', '');
            $successTypeRaw = (string) session('success_type', '');
            $successType = in_array($successTypeRaw, ['increase', 'decrease', 'edit'], true) ? $successTypeRaw : '';
            if ($successType === '' && $successMessage !== '') {
                $successMessageLower = mb_strtolower($successMessage);
                if (str_contains($successMessageLower, 'dikurang') || str_contains($successMessageLower, 'berkurang') || str_contains($successMessageLower, 'pengurangan') || str_contains($successMessageLower, 'batal') || str_contains($successMessageLower, 'hapus') || str_contains($successMessageLower, 'decrease') || str_contains($successMessageLower, 'reduced') || str_contains($successMessageLower, 'cancel')) {
                    $successType = 'decrease';
                } elseif (str_contains($successMessageLower, 'ditambah') || str_contains($successMessageLower, 'bertambah') || str_contains($successMessageLower, 'penambahan') || str_contains($successMessageLower, 'tambah') || str_contains($successMessageLower, 'created') || str_contains($successMessageLower, 'added') || str_contains($successMessageLower, 'increase')) {
                    $successType = 'increase';
                } elseif (str_contains($successMessageLower, 'diperbarui') || str_contains($successMessageLower, 'diubah') || str_contains($successMessageLower, 'edit') || str_contains($successMessageLower, 'updated')) {
                    $successType = 'edit';
                } else {
                    $successType = 'success';
                }
            }
        @endphp
        @if ($successMessage !== '')
            <div class="alert {{ $successType }} js-auto-hide-alert">{{ $successMessage }}</div>
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
<script>
    (function () {
        function debounce(fn, wait) {
            let timeoutId = null;
            return (...args) => {
                clearTimeout(timeoutId);
                timeoutId = setTimeout(() => fn(...args), Number(wait) || 100);
            };
        }

        function escapeAttribute(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/"/g, '&quot;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
        }

        function canSearchInput(input) {
            if (!input) {
                return false;
            }
            const raw = String(input.value || '').trim();
            if (raw === '') {
                return true;
            }

            const words = raw.split(/\s+/).filter(Boolean);
            if (words.length === 0) {
                return true;
            }

            return words.every((word) => word.length >= 3);
        }

        function deriveSemesterFromDate(dateValue) {
            if (!dateValue) {
                return '';
            }

            const [yearText, monthText] = String(dateValue).split('-');
            const year = parseInt(yearText, 10);
            const month = parseInt(monthText, 10);
            if (!Number.isInteger(year) || !Number.isInteger(month)) {
                return '';
            }

            if (month >= 5 && month <= 10) {
                const nextYear = year + 1;
                return `S1-${String(year).slice(-2)}${String(nextYear).slice(-2)}`;
            }

            if (month >= 11) {
                const nextYear = year + 1;
                return `S2-${String(year).slice(-2)}${String(nextYear).slice(-2)}`;
            }

            const startYear = year - 1;
            return `S2-${String(startYear).slice(-2)}${String(year).slice(-2)}`;
        }

        window.PgposAutoSearch = Object.assign({}, window.PgposAutoSearch || {}, {
            debounce,
            escapeAttribute,
            canSearchInput,
            deriveSemesterFromDate,
        });
    })();
</script>
<script>
    (function () {
        const autoHide = (alertNode) => {
            if (!alertNode) return;
            setTimeout(() => {
                alertNode.classList.add('is-hiding');
                setTimeout(() => alertNode.remove(), 220);
            }, 3000);
        };

        const normalizeType = (type) => {
            const value = String(type || '').toLowerCase();
            if (value === 'increase' || value === 'decrease' || value === 'edit' || value === 'success' || value === 'error') {
                return value;
            }
            return 'success';
        };

        window.PgposFlash = Object.assign({}, window.PgposFlash || {}, {
            show(message, type = 'success') {
                const target = document.querySelector('.main');
                if (!target || !message) return;
                const alertNode = document.createElement('div');
                alertNode.className = 'alert ' + normalizeType(type) + ' js-auto-hide-alert';
                alertNode.textContent = String(message);
                target.insertBefore(alertNode, target.firstChild);
                autoHide(alertNode);
            }
        });

        document.querySelectorAll('.js-auto-hide-alert').forEach(autoHide);
    })();
</script>
</body>
</html>
