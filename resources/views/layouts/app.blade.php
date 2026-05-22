<!doctype html>
@php
    $applicationName = config('app.name', 'Laravel');
    $isDark = auth()->check() && auth()->user()->theme === 'dark';
    $faviconPath = \App\Models\AppSetting::getValue('company_logo_path');
@endphp
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', $applicationName)</title>
    @if($faviconPath)
        <link rel="icon" href="{{ asset('storage/'.$faviconPath) }}">
        <link rel="shortcut icon" href="{{ asset('storage/'.$faviconPath) }}">
    @endif
    <style>
        :root {
            --bg: #f5f7fb;
            --background: var(--bg);
            --surface: #eef2f7;
            --card: #ffffff;
            --text: #182230;
            --muted: #667085;
            --border: #d9e0ea;
            --accent: #2457c5;
            --brand-50: #eef5ff;
            --brand-100: #dceafe;
            --brand-500: #2f6fed;
            --brand-600: #2457c5;
            --brand-700: #1d439b;
            --success-500: #159947;
            --warning-500: #d98a00;
            --danger-500: #d92d20;
            --shadow-sm: 0 1px 2px rgba(16, 24, 40, 0.06);
            --shadow-md: 0 10px 28px rgba(16, 24, 40, 0.08);
            --shadow-lg: 0 24px 60px rgba(16, 24, 40, 0.14);
            --radius-sm: 10px;
            --radius-md: 14px;
            --radius-lg: 20px;
            --sidebar-bg: #101828;
            --sidebar-text: #f8fafc;
            --sidebar-muted: #98a2b3;
            --sidebar-hover: rgba(255, 255, 255, 0.08);
            --sidebar-group-hover: rgba(255, 255, 255, 0.06);
            --sidebar-group-active: rgba(47, 111, 237, 0.14);
            --sidebar-group-border: rgba(147, 197, 253, 0.25);
            --sidebar-group-title-active-bg: rgba(47, 111, 237, 0.18);
            --sidebar-sub-text: #d0d5dd;
            --sidebar-sub-active: rgba(47, 111, 237, 0.22);
            --sidebar-sub-active-border: #7fb4ff;
            --sidebar-active-text: #ffffff;
            --table-bg: #ffffff;
            --table-header-bg: #f8fafc;
            --table-border: #d9e0ea;
            --table-border-soft: #edf1f6;
            --input-bg: #ffffff;
            --link: #2457c5;
            --link-hover: #1d439b;
            --btn-primary-bg: #2457c5;
            --btn-primary-text: #ffffff;
            --btn-secondary-bg: #ffffff;
            --btn-secondary-text: #344054;
            --btn-edit-bg: #2457c5;
            --btn-edit-border: #1d439b;
            --btn-edit-text: #ffffff;
            --btn-payment-bg: #159947;
            --btn-payment-border: #10743a;
            --btn-payment-text: #ffffff;
            --btn-warning-bg: #f8cc4b;
            --btn-warning-border: #dfa20a;
            --btn-warning-text: #4f3200;
            --btn-danger-bg: #d92d20;
            --btn-danger-border: #b42318;
            --btn-danger-text: #ffffff;
            --btn-process-bg: #5946d2;
            --btn-process-border: #4535a8;
            --btn-process-text: #ffffff;
            --btn-process-soft-bg: #6f59e8;
            --btn-process-soft-border: #5946d2;
            --btn-process-soft-text: #ffffff;
            --btn-info-bg: #eef5ff;
            --btn-info-border: #c8ddff;
            --btn-info-text: #1d439b;
            --btn-orange-bg: #f97316;
            --btn-orange-border: #ea580c;
            --btn-orange-text: #ffffff;
            --btn-create-bg: #2457c5;
            --btn-create-border: #1d439b;
            --btn-create-text: #ffffff;
            --alert-success-bg: #ecfdf3;
            --alert-success-border: #abefc6;
            --alert-success-text: #067647;
            --alert-increase-bg: #ecfdf3;
            --alert-increase-border: #75e0a7;
            --alert-increase-text: #067647;
            --alert-decrease-bg: #fef3f2;
            --alert-decrease-border: #fecdca;
            --alert-decrease-text: #b42318;
            --alert-edit-bg: #fffaeb;
            --alert-edit-border: #fedf89;
            --alert-edit-text: #93370d;
            --alert-error-bg: #fef3f2;
            --alert-error-border: #fecdca;
            --alert-error-text: #b42318;
            --badge-neutral-bg: #f2f4f7;
            --badge-neutral-text: #344054;
            --badge-success-bg: #ecfdf3;
            --badge-success-text: #067647;
            --badge-warning-bg: #fffaeb;
            --badge-warning-text: #b54708;
            --badge-danger-bg: #fef3f2;
            --badge-danger-text: #b42318;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Aptos", "Segoe UI", "Noto Sans", Tahoma, sans-serif;
            background:
                radial-gradient(circle at top left, rgba(47, 111, 237, 0.10), transparent 32rem),
                linear-gradient(
                    180deg,
                    color-mix(in srgb, var(--card) 78%, var(--bg) 22%) 0%,
                    var(--bg) 42%,
                    color-mix(in srgb, var(--surface) 72%, var(--bg) 28%) 100%
                );
            color: var(--text);
            font-size: 14px;
            line-height: 1.5;
        }
        .wrap {
            display: grid;
            grid-template-columns: 252px minmax(0, 1fr);
            min-height: 100vh;
        }
        .mobile-topbar,
        .mobile-nav-toggle,
        .sidebar-backdrop,
        .sidebar-close {
            display: none;
        }
        .sidebar {
            background: var(--sidebar-bg);
            color: var(--sidebar-text);
            padding: 22px 14px;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            overflow-x: hidden;
            box-shadow: inset -1px 0 0 rgba(255, 255, 255, 0.08);
        }
        .sidebar::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at top left, rgba(47, 111, 237, 0.28), transparent 18rem),
                linear-gradient(180deg, rgba(255, 255, 255, 0.05), transparent 28rem);
            pointer-events: none;
        }
        .brand {
            font-size: 19px;
            font-weight: 800;
            letter-spacing: -0.03em;
            margin-bottom: 22px;
            user-select: none;
            -webkit-user-select: none;
            position: relative;
            z-index: 1;
        }
        .nav {
            position: relative;
            z-index: 1;
            user-select: none;
            -webkit-user-select: none;
        }
        .nav > a,
        .nav-sub a {
            display: block;
            color: var(--sidebar-text);
            text-decoration: none;
            padding: 10px 12px;
            border-radius: 12px;
            margin-bottom: 5px;
            position: relative;
            transition: background 0.16s ease, color 0.16s ease, transform 0.16s ease;
        }
        .nav > a.active, .nav > a:hover {
            background: var(--sidebar-hover);
            color: var(--sidebar-active-text);
            transform: translateX(2px);
        }
        .nav-group {
            margin-bottom: 8px;
            border-radius: 14px;
            padding: 2px 0;
            overflow: hidden;
            position: relative;
        }
        .nav-group:hover {
            background: transparent !important;
        }
        .nav-group.active {
            background: transparent !important;
            border: none !important;
            padding: 2px 0;
        }
        .nav-section-title {
            display: block;
            margin: 12px 12px 6px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: var(--sidebar-muted);
            font-weight: 700;
            user-select: none;
            -webkit-user-select: none;
        }
        .nav-group-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            border: none;
            color: var(--sidebar-text);
            padding: 8px 12px 6px;
            border-radius: 12px;
            font-weight: 750;
            position: relative;
            z-index: 1;
            background: transparent !important;
            text-align: left;
            cursor: pointer;
            font: inherit;
            user-select: none;
            -webkit-user-select: none;
        }
        .nav-group-title::after {
            content: '+';
            font-size: 16px;
            line-height: 1;
            color: var(--sidebar-muted);
        }
        .nav-group-title.active {
            color: var(--sidebar-active-text);
        }
        .nav-group.active .nav-group-title::after {
            content: '-';
            color: var(--sidebar-active-text);
        }
        .nav-group.active .nav-group-title {
            background: var(--sidebar-group-title-active-bg) !important;
            color: var(--sidebar-active-text);
            border: 1px solid var(--sidebar-group-border);
            box-shadow: 0 10px 24px rgba(0, 0, 0, 0.16);
        }
        .nav-sub {
            padding: 0 6px 2px;
            overflow: hidden;
            position: relative;
            z-index: 1;
            display: none;
        }
        .nav-group.active .nav-sub {
            display: block;
        }
        .nav-group.active .nav-sub a {
            color: var(--sidebar-text);
        }
        .nav-sub a:hover {
            background: var(--sidebar-group-hover);
            color: var(--sidebar-active-text);
        }
        .nav-group.active .nav-sub a.active {
            background: var(--sidebar-sub-active) !important;
            border-left: 3px solid var(--sidebar-sub-active-border);
            padding-left: 11px;
            color: var(--sidebar-active-text);
        }
        .nav-sub a {
            margin-bottom: 4px;
            margin-left: 0;
            padding: 8px 10px;
            font-size: 13px;
            color: var(--sidebar-sub-text);
            background: transparent;
        }
        .main {
            padding: 28px;
            min-width: 0;
            max-width: 1780px;
            width: 100%;
            margin: 0 auto;
        }
        .main a:not(.btn) {
            color: var(--link);
        }
        .main a:not(.btn):hover,
        .main a:not(.btn):focus {
            color: var(--link-hover);
        }
        .list-doc-cell {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 6px 8px;
            line-height: 1.35;
        }
        .list-doc-link {
            white-space: nowrap;
            font-weight: 500;
        }
        .list-doc-meta {
            display: inline-flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 4px 6px;
            color: var(--muted);
            font-size: 13px;
        }
        .list-doc-meta-label {
            white-space: nowrap;
        }
        .list-doc-badges {
            display: inline-flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 6px;
        }
        .page-title {
            margin: 0 0 18px;
            font-size: 26px;
            line-height: 1.15;
            letter-spacing: -0.04em;
            font-weight: 800;
            user-select: none;
            -webkit-user-select: none;
        }
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 18px;
            margin-bottom: 16px;
            min-width: 0;
            box-shadow: var(--shadow-sm);
            transition: border-color 0.16s ease, box-shadow 0.16s ease, transform 0.16s ease;
        }
        .card:hover {
            border-color: color-mix(in srgb, var(--border) 68%, var(--brand-500) 32%);
            box-shadow: var(--shadow-md);
        }
        .form-section {
            border: 1px solid color-mix(in srgb, var(--border) 72%, var(--brand-100) 28%);
            border-radius: var(--radius-md);
            padding: 14px;
            margin-bottom: 12px;
            background: color-mix(in srgb, var(--card) 86%, var(--brand-50) 14%);
        }
        .form-section-title {
            margin: 0 0 4px;
            font-size: 14px;
            font-weight: 700;
            color: var(--text);
        }
        .form-section-note {
            margin: 0 0 8px;
            font-size: 13px;
            line-height: 1.45;
            color: var(--muted);
        }
        .label-required {
            color: #c0392b;
            margin-left: 2px;
            font-weight: 700;
        }
        .label-with-feedback {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
        }
        .field-inline-error {
            font-size: 12px;
            font-weight: 600;
            color: #dc2626;
            min-height: 16px;
            white-space: nowrap;
            padding-left: 10px;
            line-height: 1.35;
            text-align: right;
        }
        .input-inline-error {
            border-color: #dc2626 !important;
            box-shadow: 0 0 0 1px rgba(220, 38, 38, 0.18);
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 14px;
        }
        .stat {
            padding: 16px;
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            background:
                linear-gradient(135deg, color-mix(in srgb, var(--card) 96%, var(--bg) 4%), color-mix(in srgb, var(--card) 82%, var(--bg) 18%)),
                radial-gradient(circle at top right, rgba(47, 111, 237, 0.13), transparent 10rem);
            box-shadow: var(--shadow-sm);
            position: relative;
            overflow: hidden;
        }
        .stat::after {
            content: '';
            position: absolute;
            inset: auto 14px 0 auto;
            width: 44px;
            height: 4px;
            border-radius: 999px 999px 0 0;
            background: var(--brand-500);
            opacity: 0.22;
        }
        .stat-label {
            color: var(--muted);
            font-size: 12px;
            margin-bottom: 8px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .stat-value {
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -0.04em;
            font-variant-numeric: tabular-nums;
        }
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border: 1px solid var(--table-border);
            background: var(--table-bg);
            border-radius: var(--radius-sm);
            overflow: hidden;
        }
        th, td {
            border-bottom: 1px solid var(--table-border);
            border-right: 1px solid var(--table-border-soft);
            padding: 11px 10px;
            vertical-align: top;
        }
        tbody tr {
            transition: background 0.14s ease;
        }
        tbody tr:hover {
            background: color-mix(in srgb, var(--brand-50) 64%, var(--table-bg) 36%);
        }
        tr > th:last-child,
        tr > td:last-child {
            border-right: none;
        }
        tbody tr:last-child > td {
            border-bottom: none;
        }
        th {
            text-align: center;
            font-size: 11px;
            text-transform: uppercase;
            color: var(--muted);
            background: var(--table-header-bg);
            font-weight: 800;
            letter-spacing: 0.04em;
            vertical-align: middle;
        }
        td {
            text-align: left;
        }
        input, select, textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font: inherit;
            background: var(--input-bg);
            color: var(--text);
            min-height: 42px;
            box-shadow: 0 1px 2px rgba(16, 24, 40, 0.03);
            transition: border-color 0.16s ease, box-shadow 0.16s ease, background 0.16s ease;
        }
        textarea {
            line-height: 1.5;
        }
        input[type="file"] {
            color: var(--text);
        }
        input[type="file"]::file-selector-button {
            margin-right: 10px;
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--btn-secondary-bg);
            color: var(--btn-secondary-text);
            font: inherit;
            font-weight: 600;
            cursor: pointer;
        }
        input::placeholder,
        textarea::placeholder {
            color: var(--muted);
            opacity: 1;
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
            margin-bottom: 7px;
            font-size: 13px;
            font-weight: 750;
            color: var(--muted);
            letter-spacing: 0.01em;
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
        form .row > [class^="col-"] > textarea:focus,
        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: var(--brand-500);
            box-shadow: 0 0 0 4px rgba(47, 111, 237, 0.12);
        }
        /* Optional utility widths for future forms */
        .w-xs { max-width: 180px !important; }
        .w-sm { max-width: 260px !important; }
        .w-md { max-width: 380px !important; }
        .w-lg { max-width: 560px !important; }
        .dual-inline-inputs {
            display: grid;
            gap: 4px;
            min-width: 120px;
        }
        .dual-inline-inputs input {
            width: 100%;
            min-width: 0;
        }
        form > .btn,
        form > .btn.secondary {
            margin-right: 6px;
        }
        .form-submit-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            position: relative;
            z-index: 6;
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
            font-family: inherit;
            font-weight: 750;
            letter-spacing: 0;
            line-height: 1;
            border-radius: 11px;
            white-space: nowrap;
            vertical-align: middle;
            box-shadow: 0 1px 2px rgba(16, 24, 40, 0.10);
            transition: transform 0.14s ease, box-shadow 0.14s ease, filter 0.14s ease;
        }
        button:hover,
        .btn:hover,
        input[type="submit"]:hover,
        input[type="button"]:hover {
            filter: brightness(0.98);
            transform: translateY(-1px);
            box-shadow: 0 8px 18px rgba(16, 24, 40, 0.12);
        }
        button:active,
        .btn:active,
        input[type="submit"]:active,
        input[type="button"]:active {
            transform: translateY(0);
            box-shadow: 0 1px 2px rgba(16, 24, 40, 0.10);
        }
        button.secondary,
        .btn.secondary,
        input[type="submit"].secondary,
        input[type="button"].secondary {
            background: var(--btn-secondary-bg);
            color: var(--btn-secondary-text);
            border: 1px solid var(--border);
        }
        .btn.edit-btn,
        button.edit-btn,
        input[type="submit"].edit-btn,
        input[type="button"].edit-btn {
            background: var(--btn-edit-bg);
            border-color: var(--btn-edit-border);
            color: var(--btn-edit-text);
        }
        .btn.payment-btn,
        button.payment-btn,
        input[type="submit"].payment-btn,
        input[type="button"].payment-btn {
            background: var(--btn-payment-bg);
            border-color: var(--btn-payment-border);
            color: var(--btn-payment-text);
        }
        .btn.warning-btn,
        button.warning-btn,
        input[type="submit"].warning-btn,
        input[type="button"].warning-btn {
            background: var(--btn-warning-bg);
            border-color: var(--btn-warning-border);
            color: var(--btn-warning-text);
        }
        .btn.danger-btn,
        button.danger-btn,
        input[type="submit"].danger-btn,
        input[type="button"].danger-btn {
            background: var(--btn-danger-bg);
            border-color: var(--btn-danger-border);
            color: var(--btn-danger-text);
        }
        .btn.process-btn,
        button.process-btn,
        input[type="submit"].process-btn,
        input[type="button"].process-btn {
            background: var(--btn-process-bg);
            border-color: var(--btn-process-border);
            color: var(--btn-process-text);
        }
        .btn.process-soft-btn,
        button.process-soft-btn,
        input[type="submit"].process-soft-btn,
        input[type="button"].process-soft-btn {
            background: var(--btn-process-soft-bg);
            border-color: var(--btn-process-soft-border);
            color: var(--btn-process-soft-text);
        }
        .btn.info-btn,
        button.info-btn,
        input[type="submit"].info-btn,
        input[type="button"].info-btn {
            background: var(--btn-info-bg);
            border-color: var(--btn-info-border);
            color: var(--btn-info-text);
        }
        .btn.orange-btn,
        button.orange-btn,
        input[type="submit"].orange-btn,
        input[type="button"].orange-btn {
            background: var(--btn-orange-bg);
            border-color: var(--btn-orange-border);
            color: var(--btn-orange-text);
        }
        .btn.create-transaction-btn,
        button.create-transaction-btn,
        input[type="submit"].create-transaction-btn,
        input[type="button"].create-transaction-btn {
            background: var(--btn-create-bg);
            border-color: var(--btn-create-border);
            color: var(--btn-create-text);
        }
        td .flex .btn,
        td .flex button {
            min-width: 72px;
        }
        .row {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 10px;
            min-width: 0;
        }
        .col-3 { grid-column: span 3; }
        .col-4 { grid-column: span 4; }
        .col-6 { grid-column: span 6; }
        .col-8 { grid-column: span 8; }
        .col-12 { grid-column: span 12; }
        .table-mobile-scroll {
            overflow-x: auto;
            overflow-y: hidden;
            -webkit-overflow-scrolling: touch;
            border: 1px solid var(--table-border);
            border-radius: var(--radius-md);
            scrollbar-gutter: stable;
            background: var(--table-bg);
            position: relative;
            z-index: 0;
            box-shadow: var(--shadow-sm);
        }
        .table-mobile-scroll > table {
            min-width: 720px;
            border: none;
        }
        .table-mobile-scroll::-webkit-scrollbar {
            height: 8px;
        }
        .table-mobile-scroll::-webkit-scrollbar-thumb {
            background: color-mix(in srgb, var(--border) 70%, var(--text) 30%);
            border-radius: 999px;
        }
        .transaction-list-scroll {
            overflow-x: auto;
            overflow-y: auto;
            max-height: min(420px, calc(100vh - 280px));
            -webkit-overflow-scrolling: touch;
            border: 1px solid var(--table-border);
            border-radius: var(--radius-md);
            scrollbar-gutter: stable;
            background: var(--table-bg);
            position: relative;
            z-index: 0;
            box-shadow: var(--shadow-sm);
        }
        .transaction-list-scroll > table {
            min-width: 720px;
            border: none;
            margin-bottom: 0;
        }
        .transaction-list-scroll thead th {
            position: sticky;
            top: 0;
            z-index: 4;
            background: var(--table-header-bg);
            box-shadow: inset 0 -1px 0 var(--table-border);
        }
        .transaction-list-scroll::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        .transaction-list-scroll::-webkit-scrollbar-thumb {
            background: color-mix(in srgb, var(--border) 70%, var(--text) 30%);
            border-radius: 999px;
        }
        .transaction-list-scroll::-webkit-scrollbar-track {
            background: color-mix(in srgb, var(--surface) 88%, var(--border) 12%);
        }
        .main {
            position: relative;
            isolation: isolate;
        }
        .main > :not(script):not(style) {
            position: relative;
            z-index: 0;
        }
        .page-header-actions {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 16px;
            position: relative;
            z-index: 24;
            isolation: isolate;
        }
        .page-header-actions .page-title {
            margin: 0;
        }
        .page-header-actions .actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: flex-end;
            position: relative;
            z-index: 25;
        }
        .page-header-actions .actions > *,
        .page-header-actions .actions form,
        .page-header-actions .actions form > * {
            position: relative;
            z-index: 26;
        }
        .page-header-actions .actions .btn:not(.secondary),
        .page-header-actions .actions button:not(.secondary),
        .page-header-actions .actions input[type="submit"]:not(.secondary),
        .page-header-actions .actions input[type="button"]:not(.secondary) {
            background: var(--btn-create-bg) !important;
            border-color: var(--btn-create-border) !important;
            color: var(--btn-create-text) !important;
        }
        .filter-toolbar {
            display: flex;
            align-items: flex-end;
            gap: 12px;
            flex-wrap: wrap;
        }
        .filter-field {
            display: flex;
            flex-direction: column;
            gap: 6px;
            min-width: 0;
        }
        .filter-field label {
            font-size: 12px;
            font-weight: 750;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .stack-mobile {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .mobile-summary {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            align-items: flex-start;
            flex-wrap: wrap;
        }
        .mobile-summary > * {
            min-width: 0;
        }
        .mobile-stack-table {
            width: 100%;
        }
        .alert {
            padding: 12px 14px;
            border-radius: var(--radius-sm);
            margin-bottom: 12px;
            border: 1px solid;
            transition: opacity 0.2s ease, transform 0.2s ease;
            box-shadow: var(--shadow-sm);
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
            padding: 5px 10px;
            font-size: 11px;
            font-weight: 800;
            line-height: 1.2;
            white-space: nowrap;
            background: var(--badge-neutral-bg);
            color: var(--badge-neutral-text);
            border: 1px solid color-mix(in srgb, currentColor 16%, transparent);
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
            padding: 8px 10px;
            font-size: 13px;
            min-height: 38px;
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
            border-radius: 10px;
            border: 1px solid var(--border);
            background: var(--card);
            color: var(--text);
            text-decoration: none;
            font-size: 13px;
            line-height: 1;
            box-shadow: var(--shadow-sm);
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
        .main > .card:first-of-type {
            border-top: 3px solid color-mix(in srgb, var(--brand-500) 72%, var(--border) 28%);
        }
        .card > h3:first-child,
        .card > h4:first-child {
            margin-top: 0;
            letter-spacing: -0.02em;
        }
        td.action,
        td.action-cell,
        th.action,
        th.action-cell,
        td[class*="action"],
        th[class*="action"] {
            white-space: nowrap;
        }
        td .flex,
        .product-actions,
        .receivable-customer-actions,
        .supplier-payable-actions {
            gap: 6px;
        }
        td .btn,
        td button,
        td .action-menu {
            min-height: 32px;
            font-size: 12px;
            padding: 6px 9px;
            border-radius: 9px;
        }
        @media (max-width: 900px) {
            body {
                overflow-x: hidden;
            }
            .wrap {
                grid-template-columns: 1fr;
            }
            .mobile-topbar {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                position: sticky;
                top: 0;
                z-index: 32;
                padding: 12px 16px;
                background: var(--card);
                border-bottom: 1px solid var(--border);
                box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
            }
            .mobile-topbar-title {
                font-size: 16px;
                font-weight: 700;
                color: var(--text);
            }
            .mobile-nav-toggle,
            .sidebar-close {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 42px;
                min-height: 42px;
                padding: 0 12px;
                border-radius: 10px;
                border: 1px solid var(--border);
                background: var(--card);
                color: var(--text);
                font: inherit;
                font-weight: 700;
                cursor: pointer;
                box-shadow: 0 4px 16px rgba(15, 23, 42, 0.08);
            }
            .sidebar-backdrop {
                display: block;
                position: fixed;
                inset: 0;
                background: rgba(15, 23, 42, 0.48);
                opacity: 0;
                visibility: hidden;
                pointer-events: none;
                transition: opacity 0.2s ease;
                z-index: 34;
            }
            .sidebar {
                position: fixed;
                top: 0;
                left: 0;
                bottom: 0;
                width: min(86vw, 296px);
                max-width: 296px;
                z-index: 35;
                overflow-y: auto;
                box-shadow: 18px 0 36px rgba(15, 23, 42, 0.28);
                transform: translateX(-100%);
                transition: transform 0.22s ease;
            }
            body.mobile-sidebar-open .sidebar {
                transform: translateX(0);
            }
            body.mobile-sidebar-open .sidebar-backdrop {
                opacity: 1;
                visibility: visible;
                pointer-events: auto;
            }
            .sidebar-header-mobile {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                margin-bottom: 18px;
            }
            .sidebar-header-mobile .brand {
                margin-bottom: 0;
            }
            .main {
                padding: 14px;
            }
            .col-3, .col-4, .col-6, .col-8, .col-12 { grid-column: span 12; }
            form .row > [class^="col-"] > input,
            form .row > [class^="col-"] > select,
            form .row > [class^="col-"] > textarea {
                max-width: 100%;
            }
            .card {
                padding: 12px;
                border-radius: 12px;
            }
            .form-section {
                padding: 10px;
            }
            .page-title {
                font-size: 21px;
                line-height: 1.2;
            }
            button,
            .btn,
            input[type="submit"],
            input[type="button"] {
                min-height: 42px;
                padding: 10px 14px;
            }
            .label-with-feedback {
                align-items: flex-start;
                flex-direction: column;
                gap: 6px;
            }
            .field-inline-error {
                text-align: left;
                white-space: normal;
                padding-left: 0;
            }
            .page-header-actions,
            .page-header-actions .actions,
            .filter-toolbar,
            .stack-mobile,
            .mobile-summary {
                flex-direction: column;
                align-items: stretch;
            }
            .page-header-actions .actions {
                justify-content: flex-start;
            }
            .filter-field,
            .filter-toolbar > *,
            .stack-mobile > * {
                width: 100%;
                min-width: 0;
            }
            .filter-toolbar .btn,
            .page-header-actions .actions .btn,
            .page-header-actions .actions form,
            .stack-mobile .btn,
            .stack-mobile form {
                width: 100%;
            }
            .page-header-actions .actions form .btn,
            .stack-mobile form .btn {
                width: 100%;
            }
            .action-menu,
            .action-menu-sm,
            .action-menu-md,
            .action-menu-lg {
                width: 100%;
                max-width: 100%;
                min-width: 0;
            }
            .dual-inline-inputs {
                min-width: 108px;
                gap: 6px;
            }
            .dual-inline-inputs input {
                min-height: 40px;
                font-size: 14px;
            }
            .table-mobile-scroll {
                margin-inline: -2px;
            }
            .table-mobile-scroll > table {
                min-width: 640px;
            }
            .table-mobile-scroll > table.mobile-stack-table {
                min-width: 0;
            }
            .mobile-stack-table,
            .mobile-stack-table tbody,
            .mobile-stack-table tr,
            .mobile-stack-table td {
                display: block;
                width: 100%;
            }
            .mobile-stack-table thead {
                display: none;
            }
            .mobile-stack-table tr {
                margin-bottom: 12px;
                border: 1px solid var(--table-border);
                border-radius: 10px;
                overflow: hidden;
                background: var(--table-bg);
            }
            .mobile-stack-table tbody td {
                display: grid;
                grid-template-columns: minmax(96px, 38%) minmax(0, 1fr);
                gap: 8px;
                align-items: start;
                border-right: none;
                padding: 10px 10px;
                white-space: normal;
                overflow: visible;
                text-overflow: initial;
            }
            .mobile-stack-table tbody td::before {
                content: attr(data-label);
                font-size: 11px;
                font-weight: 700;
                text-transform: uppercase;
                color: var(--muted);
                line-height: 1.3;
            }
            .mobile-stack-table tbody td.num {
                text-align: left;
            }
            .mobile-stack-table tbody td.action,
            .mobile-stack-table tbody td.action-cell {
                display: block;
            }
            .mobile-stack-table tbody td.action::before,
            .mobile-stack-table tbody td.action-cell::before {
                display: block;
                margin-bottom: 8px;
            }
            .mobile-stack-table tbody td.action .flex,
            .mobile-stack-table tbody td.action-cell .flex,
            .mobile-stack-table tbody td.action .receivable-customer-actions,
            .mobile-stack-table tbody td.action-cell .receivable-customer-actions,
            .mobile-stack-table tbody td.action .supplier-payable-actions,
            .mobile-stack-table tbody td.action-cell .supplier-payable-actions {
                width: 100%;
            }
            .mobile-stack-table tbody td.action .btn,
            .mobile-stack-table tbody td.action-cell .btn,
            .mobile-stack-table tbody td.action .action-menu,
            .mobile-stack-table tbody td.action-cell .action-menu,
            .mobile-stack-table tbody td.action .supplier-payable-actions .btn,
            .mobile-stack-table tbody td.action-cell .supplier-payable-actions .btn,
            .mobile-stack-table tbody td.action .receivable-customer-actions .btn,
            .mobile-stack-table tbody td.action-cell .receivable-customer-actions .btn {
                width: 100%;
                max-width: 100%;
            }
            .mobile-stack-table tbody td[colspan] {
                display: block;
                text-align: left;
            }
            .mobile-stack-table tbody td[colspan]::before {
                content: none;
            }
            table:not(.calendar-table) {
                font-size: 13px;
            }
            th, td {
                padding: 9px 7px;
            }
            .list-doc-cell {
                align-items: flex-start;
            }
            .pagination {
                justify-content: center;
            }
            .pagination .page-link {
                min-width: 38px;
                min-height: 38px;
            }
        }
    </style>
</head>
<body @if($isDark) style="--bg:#111827;--surface:#111827;--card:#1F2937;--text:#F9FAFB;--muted:#9CA3AF;--border:#374151;--accent:#F9FAFB;--sidebar-bg:#1F2937;--sidebar-text:#D1D5DB;--sidebar-muted:#9CA3AF;--sidebar-hover:#2563EB;--sidebar-group-hover:rgba(37,99,235,0.12);--sidebar-group-active:rgba(37,99,235,0.18);--sidebar-group-border:rgba(37,99,235,0.35);--sidebar-group-title-active-bg:rgba(37,99,235,0.20);--sidebar-sub-text:#D1D5DB;--sidebar-sub-active:#2563EB;--sidebar-sub-active-border:#2563EB;--sidebar-active-text:#FFFFFF;--table-bg:#1F2937;--table-header-bg:#111827;--table-border:#374151;--table-border-soft:#374151;--input-bg:#111827;--link:#93c5fd;--link-hover:#bfdbfe;--btn-secondary-bg:#374151;--btn-secondary-text:#F9FAFB;--alert-success-bg:#0f2a18;--alert-success-border:#2f7f47;--alert-success-text:#d8f6e1;--alert-increase-bg:#11301f;--alert-increase-border:#4fb06e;--alert-increase-text:#d9ffe7;--alert-decrease-bg:#3a1717;--alert-decrease-border:#d86868;--alert-decrease-text:#ffd9d9;--alert-edit-bg:#3f3415;--alert-edit-border:#d3b25a;--alert-edit-text:#ffedb8;--alert-error-bg:#2d1212;--alert-error-border:#8e3333;--alert-error-text:#ffdede;--badge-neutral-bg:#2b2f36;--badge-neutral-text:#d8dee9;--badge-success-bg:#143621;--badge-success-text:#bde8cb;--badge-warning-bg:#3d2f14;--badge-warning-text:#f6d98f;--badge-danger-bg:#4b1f1f;--badge-danger-text:#ffd2d2;" @endif>
<div class="mobile-topbar">
    <button type="button" class="mobile-nav-toggle" data-mobile-nav-toggle aria-expanded="false" aria-controls="app-sidebar">{{ __('ui.mobile_menu') }}</button>
    <div class="mobile-topbar-title">{{ $applicationName }}</div>
</div>
<div class="sidebar-backdrop" data-sidebar-backdrop></div>
<div class="wrap">
    <aside class="sidebar" id="app-sidebar">
        <div class="sidebar-header-mobile">
            <div class="brand">{{ $applicationName }}</div>
            <button type="button" class="sidebar-close" data-mobile-nav-close aria-label="{{ __('ui.close_menu') }}">×</button>
        </div>
        @php
            $authUser = auth()->user();
            $canDashboard = $authUser?->canAccess('dashboard.view') ?? false;
            $canTransactionsView = $authUser !== null;
            $canSalesInvoiceCreate = $authUser?->canAccess('sales_invoices.create') ?? false;
            $canSalesReturnCreate = $authUser?->canAccess('sales_returns.create') ?? false;
            $canDeliveryNoteCreate = $authUser?->canAccess('delivery_notes.create') ?? false;
            $canOrderNoteCreate = $authUser?->canAccess('order_notes.create') ?? false;
            $canDeliveryTripCreate = $authUser?->canAccess('delivery_trips.create') ?? false;
            $canOutgoingTransactionCreate = $authUser?->canAccess('outgoing_transactions.create') ?? false;
            $canSchoolBulkCreate = $authUser?->canAccess('school_bulk_transactions.create') ?? false;
            $canTransactionsCreate = $canSalesInvoiceCreate || $canSalesReturnCreate || $canDeliveryNoteCreate || $canOrderNoteCreate || $canDeliveryTripCreate || $canOutgoingTransactionCreate || $canSchoolBulkCreate;
            $canTransactionsExport = $authUser?->canAccessAny([
                'sales_invoices.export',
                'sales_returns.export',
                'delivery_notes.export',
                'order_notes.export',
                'delivery_trips.export',
                'outgoing_transactions.export',
                'school_bulk_transactions.export',
            ]) ?? false;
            $canCorrectionApprove = $authUser?->canAccess('transactions.correction.approve') ?? false;
            $canReceivablesView = $authUser?->canAccess('receivables.view') ?? false;
            $canReceivablesPay = $authUser?->canAccess('receivables.pay') ?? false;
            $canSupplierPayablesView = $authUser !== null;
            $canReportsView = $authUser?->canAccess('reports.view') ?? false;
            $canProductsView = $authUser !== null;
            $canProductsManage = $authUser?->canAccessAny(['products.create', 'products.edit', 'products.delete', 'products.import']) ?? false;
            $canCustomersView = $authUser !== null;
            $canCustomersManage = $authUser?->canAccessAny(['customers.create', 'customers.edit', 'customers.delete', 'customers.import']) ?? false;
            $canSuppliersView = $authUser !== null;
            $canSuppliersEdit = $authUser?->canAccessAny(['suppliers.create', 'suppliers.edit', 'suppliers.delete', 'suppliers.import']) ?? false;
            $canSettingsProfile = $authUser?->canAccess('settings.profile') ?? false;
            $canSettingsAdmin = $authUser?->canAccess('settings.admin') ?? false;
            $canSemesterBulk = $authUser?->canAccess('semester.bulk') ?? false;
            $canUsersManage = $authUser?->canAccess('users.manage') ?? false;
            $canAuditLogsView = $authUser?->canAccess('audit_logs.view') ?? false;
            $canAboutView = $authUser !== null;
            $showItemsGroup = $authUser !== null;
            $showCustomersGroup = $authUser !== null;
            $showSuppliersGroup = $authUser !== null;
            $showSchoolDistributionGroup = $authUser !== null;
            $showTransactionsGroup = $authUser !== null;
            $showReceivablesGroup = $canReceivablesView || $canReceivablesPay;
            $showReportsGroup = $canReportsView;
            $showSystemGroup = $canUsersManage || $canAuditLogsView || $canCorrectionApprove || $canSettingsAdmin || $canSettingsProfile || $canSemesterBulk || $canAboutView;
        @endphp
        <nav class="nav">
            @if($canDashboard)
                <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">{{ __('menu.dashboard') }}</a>
            @endif
            @auth
                @if($showItemsGroup)
                    <div class="nav-group {{ request()->routeIs('item-categories.*') || request()->routeIs('product-units.*') || request()->routeIs('products.*') ? 'active' : '' }}" data-nav-group>
                        <button type="button" class="nav-group-title {{ request()->routeIs('item-categories.*') || request()->routeIs('product-units.*') || request()->routeIs('products.*') ? 'active' : '' }}" data-nav-toggle>{{ __('ui.nav_items_group') }}</button>
                        <div class="nav-sub">
                            @if($canProductsManage)
                                <a href="{{ route('item-categories.index') }}" class="{{ request()->routeIs('item-categories.*') ? 'active' : '' }}">{{ __('menu.item_categories') }}</a>
                                <a href="{{ route('product-units.index') }}" class="{{ request()->routeIs('product-units.*') ? 'active' : '' }}">{{ __('menu.product_units') }}</a>
                            @endif
                            @if($canProductsView)
                                <a href="{{ route('products.index') }}" class="{{ request()->routeIs('products.*') ? 'active' : '' }}">{{ __('menu.items') }}</a>
                            @endif
                        </div>
                    </div>
                @endif
                @if($showCustomersGroup)
                    <div class="nav-group {{ request()->routeIs('customer-levels-web.*') || request()->routeIs('customers-web.*') ? 'active' : '' }}" data-nav-group>
                        <button type="button" class="nav-group-title {{ request()->routeIs('customer-levels-web.*') || request()->routeIs('customers-web.*') ? 'active' : '' }}" data-nav-toggle>{{ __('ui.nav_customers_group') }}</button>
                        <div class="nav-sub">
                            @if($canCustomersManage)
                                <a href="{{ route('customer-levels-web.index') }}" class="{{ request()->routeIs('customer-levels-web.*') ? 'active' : '' }}">{{ __('menu.customer_levels') }}</a>
                            @endif
                            @if($canCustomersView)
                                <a href="{{ route('customers-web.index') }}" class="{{ request()->routeIs('customers-web.*') ? 'active' : '' }}">{{ __('menu.customers') }}</a>
                            @endif
                        </div>
                    </div>
                @endif
            @endauth
            @auth
                @if($showSuppliersGroup)
                <div class="nav-group {{ request()->routeIs('suppliers.*') || request()->routeIs('outgoing-transactions.*') || request()->routeIs('supplier-payables.*') || request()->routeIs('supplier-stock-cards.*') ? 'active' : '' }}" data-nav-group>
                    <button type="button" class="nav-group-title {{ request()->routeIs('suppliers.*') || request()->routeIs('outgoing-transactions.*') || request()->routeIs('supplier-payables.*') || request()->routeIs('supplier-stock-cards.*') ? 'active' : '' }}" data-nav-toggle>{{ __('menu.suppliers') }}</button>
                    <div class="nav-sub">
                        @if($canSuppliersView)
                            <a href="{{ route('suppliers.index') }}" class="{{ request()->routeIs('suppliers.*') ? 'active' : '' }}">{{ __('menu.suppliers') }}</a>
                        @endif
                        @if($canTransactionsView || $canTransactionsCreate)
                            @php
                                $outgoingTransactionsRoute = $canTransactionsView
                                    ? route('outgoing-transactions.index')
                                    : route('outgoing-transactions.create');
                            @endphp
                            <a href="{{ $outgoingTransactionsRoute }}" class="{{ request()->routeIs('outgoing-transactions.*') ? 'active' : '' }}">{{ __('menu.outgoing_transactions') }}</a>
                        @endif
                        @if($canSupplierPayablesView)
                            <a href="{{ route('supplier-payables.index') }}" class="{{ request()->routeIs('supplier-payables.*') ? 'active' : '' }}">{{ __('menu.supplier_payables') }}</a>
                            <a href="{{ route('supplier-stock-cards.index') }}" class="{{ request()->routeIs('supplier-stock-cards.*') ? 'active' : '' }}">{{ __('menu.supplier_stock_cards') }}</a>
                        @endif
                    </div>
                </div>
                @endif
            @endauth
            @auth
                @if($showSchoolDistributionGroup)
                <div class="nav-group {{ request()->routeIs('customer-ship-locations.*') || request()->routeIs('school-bulk-transactions.*') ? 'active' : '' }}" data-nav-group>
                    <button type="button" class="nav-group-title {{ request()->routeIs('customer-ship-locations.*') || request()->routeIs('school-bulk-transactions.*') ? 'active' : '' }}" data-nav-toggle>{{ __('menu.school_distribution') }}</button>
                    <div class="nav-sub">
                        @if($canTransactionsView || $canTransactionsCreate)
                            <a href="{{ route('customer-ship-locations.index') }}" class="{{ request()->routeIs('customer-ship-locations.*') ? 'active' : '' }}">{{ __('menu.ship_locations') }}</a>
                            <a href="{{ route('school-bulk-transactions.index') }}" class="{{ request()->routeIs('school-bulk-transactions.*') ? 'active' : '' }}">{{ __('menu.school_bulk_transactions') }}</a>
                        @endif
                    </div>
                </div>
                @endif
            @endauth
            @if($showTransactionsGroup)
                @php
                    $isPendingDeliveryInvoiceRoute = request()->routeIs('sales-invoices.pending-delivery-notes')
                        || request()->routeIs('sales-invoices.create-from-delivery-notes')
                        || request()->routeIs('sales-invoices.store-from-delivery-notes');
                    $isSalesInvoiceRoute = request()->routeIs('sales-invoices.*') && ! $isPendingDeliveryInvoiceRoute;
                @endphp
                <div class="nav-group {{ request()->routeIs('sales-invoices.*') || request()->routeIs('sales-returns.*') || request()->routeIs('delivery-notes.*') || request()->routeIs('delivery-trips.*') || request()->routeIs('order-notes.*') ? 'active' : '' }}" data-nav-group>
                    <button type="button" class="nav-group-title {{ request()->routeIs('sales-invoices.*') || request()->routeIs('sales-returns.*') || request()->routeIs('delivery-notes.*') || request()->routeIs('delivery-trips.*') || request()->routeIs('order-notes.*') ? 'active' : '' }}" data-nav-toggle>{{ __('ui.nav_transactions') }}</button>
                    <div class="nav-sub">
                        <a href="{{ route('order-notes.index') }}" class="{{ request()->routeIs('order-notes.*') ? 'active' : '' }}">{{ __('menu.order_notes') }}</a>
                        <a href="{{ route('delivery-notes.index') }}" class="{{ request()->routeIs('delivery-notes.*') ? 'active' : '' }}">{{ __('menu.delivery_notes') }}</a>
                        @if($canSalesInvoiceCreate)
                            <a href="{{ route('sales-invoices.pending-delivery-notes') }}" class="{{ $isPendingDeliveryInvoiceRoute ? 'active' : '' }}">{{ __('menu.pending_delivery_notes_invoice') }}</a>
                        @endif
                        <a href="{{ route('sales-invoices.index') }}" class="{{ $isSalesInvoiceRoute ? 'active' : '' }}">{{ __('menu.sales_invoices') }}</a>
                        <a href="{{ route('sales-returns.index') }}" class="{{ request()->routeIs('sales-returns.*') ? 'active' : '' }}">{{ __('menu.sales_returns') }}</a>
                        <a href="{{ route('delivery-trips.index') }}" class="{{ request()->routeIs('delivery-trips.*') ? 'active' : '' }}">{{ __('menu.delivery_trip_logs') }}</a>
                    </div>
                </div>
            @endif
            @if($showReceivablesGroup)
                <div class="nav-group {{ request()->routeIs('receivables.*') || request()->routeIs('receivable-payments.*') ? 'active' : '' }}" data-nav-group>
                    <button type="button" class="nav-group-title {{ request()->routeIs('receivables.*') || request()->routeIs('receivable-payments.*') ? 'active' : '' }}" data-nav-toggle>{{ __('menu.receivables') }}</button>
                    <div class="nav-sub">
                        @if($canReceivablesView)
                            <a href="{{ route('receivables.index') }}" class="{{ request()->routeIs('receivables.index') || request()->routeIs('receivables.customer-*') ? 'active' : '' }}">{{ __('menu.receivable_ledger') }}</a>
                            <a href="{{ route('receivables.global.index') }}" class="{{ request()->routeIs('receivables.global.*') ? 'active' : '' }}">{{ __('menu.receivable_global') }}</a>
                            <a href="{{ route('receivables.semester.index') }}" class="{{ request()->routeIs('receivables.semester.*') ? 'active' : '' }}">{{ __('menu.receivable_semester') }}</a>
                            <a href="{{ route('receivable-payments.index') }}" class="{{ request()->routeIs('receivable-payments.*') ? 'active' : '' }}">{{ __('menu.receivable_payments') }}</a>
                        @endif
                    </div>
                </div>
            @endif
            @if($showReportsGroup)
                <div class="nav-group {{ request()->routeIs('reports.*') ? 'active' : '' }}" data-nav-group>
                    <button type="button" class="nav-group-title {{ request()->routeIs('reports.*') ? 'active' : '' }}" data-nav-toggle>{{ __('ui.nav_reports') }}</button>
                    <div class="nav-sub">
                        <a href="{{ route('reports.index') }}" class="{{ request()->routeIs('reports.*') ? 'active' : '' }}">{{ __('menu.reports') }}</a>
                    </div>
                </div>
            @endif
            @auth
                @if($showSystemGroup)
                    <div class="nav-group {{ request()->routeIs('users.*') || request()->routeIs('audit-logs.*') || request()->routeIs('approvals.*') || request()->routeIs('semester-transactions.*') || request()->routeIs('ops-health.*') || request()->routeIs('archive-data.*') || request()->routeIs('about.*') || request()->routeIs('settings.*') ? 'active' : '' }}" data-nav-group>
                        <button type="button" class="nav-group-title {{ request()->routeIs('users.*') || request()->routeIs('audit-logs.*') || request()->routeIs('approvals.*') || request()->routeIs('semester-transactions.*') || request()->routeIs('ops-health.*') || request()->routeIs('archive-data.*') || request()->routeIs('about.*') || request()->routeIs('settings.*') ? 'active' : '' }}" data-nav-toggle>{{ __('ui.nav_system') }}</button>
                        <div class="nav-sub">
                        @if($canUsersManage)
                            <a href="{{ route('users.index') }}" class="{{ request()->routeIs('users.*') ? 'active' : '' }}">{{ __('menu.users') }}</a>
                        @endif
                        @if($canAuditLogsView)
                            <a href="{{ route('audit-logs.index') }}" class="{{ request()->routeIs('audit-logs.*') ? 'active' : '' }}">{{ __('ui.audit_logs') }}</a>
                        @endif
                        @if($canCorrectionApprove)
                            <a href="{{ route('approvals.index') }}" class="{{ request()->routeIs('approvals.*') ? 'active' : '' }}">Approval</a>
                        @endif
                        @if($canSettingsAdmin || $canSemesterBulk)
                            <a href="{{ route('semester-transactions.index') }}" class="{{ request()->routeIs('semester-transactions.*') ? 'active' : '' }}">{{ __('menu.semester_transactions') }}</a>
                        @endif
                        @if($canSettingsAdmin)
                            <a href="{{ route('ops-health.index') }}" class="{{ request()->routeIs('ops-health.*') ? 'active' : '' }}">Ops Health</a>
                            <a href="{{ route('archive-data.index') }}" class="{{ request()->routeIs('archive-data.*') ? 'active' : '' }}">{{ __('menu.archive_data') }}</a>
                        @endif
                        @if($canAboutView)
                            <a href="{{ route('about.index') }}" class="{{ request()->routeIs('about.*') ? 'active' : '' }}">{{ __('menu.about') }}</a>
                        @endif
                        @if($canSettingsProfile)
                            <a href="{{ route('settings.edit') }}" class="{{ request()->routeIs('settings.*') ? 'active' : '' }}">{{ __('menu.settings') }}</a>
                        @endif
                        </div>
                    </div>
                @endif
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
        @if (session('error'))
            <div class="alert error js-auto-hide-alert">{{ session('error') }}</div>
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
        <div id="pgpos-dialog-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:3000;"></div>
        <div id="pgpos-dialog" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); width:min(520px, calc(100vw - 24px)); background:var(--card); border:1px solid var(--border); border-radius:10px; padding:14px; z-index:3001;">
            <div class="flex" style="justify-content:space-between; margin-bottom:10px;">
                <strong id="pgpos-dialog-title">{{ __('ui.dialog_notice_title') }}</strong>
                <button type="button" id="pgpos-dialog-close" class="btn info-btn" style="min-height:30px; padding:4px 10px;">&times;</button>
            </div>
            <div id="pgpos-dialog-message" style="white-space:pre-wrap; line-height:1.45;"></div>
            <div class="flex" style="gap:8px; justify-content:flex-end; margin-top:14px;">
                <button type="button" id="pgpos-dialog-cancel" class="btn secondary">{{ __('ui.cancel') }}</button>
                <button type="button" id="pgpos-dialog-ok" class="btn">{{ __('ui.ok') }}</button>
            </div>
        </div>
        <script>
            (function () {
                const overlay = document.getElementById('pgpos-dialog-overlay');
                const modal = document.getElementById('pgpos-dialog');
                const title = document.getElementById('pgpos-dialog-title');
                const message = document.getElementById('pgpos-dialog-message');
                const closeBtn = document.getElementById('pgpos-dialog-close');
                const cancelBtn = document.getElementById('pgpos-dialog-cancel');
                const okBtn = document.getElementById('pgpos-dialog-ok');

                if (!overlay || !modal || !title || !message || !closeBtn || !cancelBtn || !okBtn) {
                    return;
                }

                let confirmCallback = null;

                const defaultNoticeTitle = @json(__('ui.dialog_notice_title'));
                const defaultConfirmTitle = @json(__('ui.dialog_confirm_title'));

                const closeDialog = () => {
                    modal.style.display = 'none';
                    overlay.style.display = 'none';
                    confirmCallback = null;
                };

                const openDialog = (options) => {
                    const type = String(options.type || 'message');
                    title.textContent = String(options.title || (type === 'confirm' ? defaultConfirmTitle : defaultNoticeTitle));
                    message.textContent = String(options.message || '');
                    cancelBtn.style.display = type === 'confirm' ? 'inline-flex' : 'none';
                    okBtn.textContent = String(options.okText || @json(__('ui.ok')));
                    modal.style.display = 'block';
                    overlay.style.display = 'block';
                    setTimeout(() => okBtn.focus(), 50);
                };

                window.PgposDialog = Object.assign({}, window.PgposDialog || {}, {
                    showMessage(dialogMessage, dialogTitle = defaultNoticeTitle) {
                        confirmCallback = null;
                        openDialog({
                            message: dialogMessage,
                            title: dialogTitle,
                            type: 'message',
                        });
                    },
                    showConfirm(dialogMessage, onConfirm, dialogTitle = defaultConfirmTitle) {
                        confirmCallback = typeof onConfirm === 'function' ? onConfirm : null;
                        openDialog({
                            message: dialogMessage,
                            title: dialogTitle,
                            type: 'confirm',
                            okText: @json(__('ui.continue')),
                        });
                    },
                });

                okBtn.addEventListener('click', () => {
                    const callback = confirmCallback;
                    closeDialog();
                    if (callback) {
                        callback();
                    }
                });
                closeBtn.addEventListener('click', closeDialog);
                cancelBtn.addEventListener('click', closeDialog);
                overlay.addEventListener('click', closeDialog);
                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape' && modal.style.display === 'block') {
                        closeDialog();
                    }
                });

                document.addEventListener('submit', (event) => {
                    const form = event.target;
                    if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-confirm-modal')) {
                        return;
                    }
                    if (form.dataset.confirmApproved === '1') {
                        return;
                    }

                    event.preventDefault();
                    window.PgposDialog.showConfirm(
                        form.getAttribute('data-confirm-message') || defaultConfirmTitle,
                        () => {
                            form.dataset.confirmApproved = '1';
                            window.PgposNumberFormat?.cleanForm(form);
                            form.submit();
                        },
                        form.getAttribute('data-confirm-title') || defaultConfirmTitle
                    );
                }, true);
            })();
        </script>
        @if (session('error_popup'))
            <script>
                window.PgposDialog.showMessage(@json((string) session('error_popup')));
            </script>
        @endif
        <script>
            (function () {
                function digitsOnly(value) {
                    return String(value || '').replace(/\D/g, '');
                }

                function formatThousands(value) {
                    const digits = digitsOnly(value);
                    if (digits === '') {
                        return '';
                    }

                    return digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                }

                function parseFormattedInteger(value) {
                    const digits = digitsOnly(value);

                    return digits === '' ? 0 : Number(digits);
                }

                function formatNumberInput(input) {
                    if (!input) {
                        return;
                    }

                    const formatted = formatThousands(input.value);
                    if (input.value !== formatted) {
                        input.value = formatted;
                    }
                }

                function cleanNumberInputs(form) {
                    if (!form) {
                        return;
                    }

                    form.querySelectorAll('.js-thousand-input').forEach((input) => {
                        input.value = digitsOnly(input.value);
                    });
                }

                document.addEventListener('input', (event) => {
                    const target = event.target;
                    if (!(target instanceof HTMLInputElement) || !target.classList.contains('js-thousand-input')) {
                        return;
                    }

                    formatNumberInput(target);
                });

                document.addEventListener('submit', (event) => {
                    cleanNumberInputs(event.target);
                }, true);

                document.addEventListener('DOMContentLoaded', () => {
                    document.querySelectorAll('.js-thousand-input').forEach(formatNumberInput);
                });

                window.PgposNumberFormat = Object.assign({}, window.PgposNumberFormat || {}, {
                    digitsOnly,
                    formatThousands,
                    parseInt: parseFormattedInteger,
                    formatInput: formatNumberInput,
                    cleanForm: cleanNumberInputs,
                });
            })();
        </script>
        @yield('content')
    </main>
</div>
<script>
    (function () {
        function normalizeLegacyPageHeaders() {
            const pageMain = document.querySelector('.main');
            if (!pageMain) {
                return;
            }

            pageMain.querySelectorAll(':scope > .flex').forEach((container) => {
                if (!(container instanceof HTMLDivElement) || container.classList.contains('page-header-actions')) {
                    return;
                }

                const title = container.querySelector(':scope > .page-title');
                if (!(title instanceof HTMLElement)) {
                    return;
                }

                const directChildren = Array.from(container.children);
                const actionNodes = directChildren.filter((child) => child !== title);
                if (actionNodes.length === 0) {
                    return;
                }

                const actionsWrapper = document.createElement('div');
                actionsWrapper.className = 'actions';
                actionNodes.forEach((node) => actionsWrapper.appendChild(node));

                container.classList.add('page-header-actions');
                container.style.justifyContent = '';
                container.style.marginBottom = '';
                container.appendChild(actionsWrapper);
            });
        }

        normalizeLegacyPageHeaders();

        const navGroups = Array.from(document.querySelectorAll('[data-nav-group]'));
        const navStorageKey = 'pgpos-open-nav-group';

        function openNavGroup(targetGroup) {
            navGroups.forEach((group) => {
                const shouldOpen = group === targetGroup;
                group.classList.toggle('active', shouldOpen);
                if (shouldOpen) {
                    const groupName = group.querySelector('[data-nav-toggle]')?.textContent?.trim() || '';
                    if (groupName !== '') {
                        localStorage.setItem(navStorageKey, groupName);
                    }
                }
            });
        }

        navGroups.forEach((group) => {
            const toggle = group.querySelector('[data-nav-toggle]');
            toggle?.addEventListener('click', () => {
                if (group.classList.contains('active')) {
                    return;
                }
                openNavGroup(group);
            });
        });

        const activeGroup = navGroups.find((group) => group.querySelector('.nav-sub a.active'));
        const storedGroupName = localStorage.getItem(navStorageKey);
        const storedGroup = navGroups.find((group) => group.querySelector('[data-nav-toggle]')?.textContent?.trim() === storedGroupName);

        if (activeGroup) {
            openNavGroup(activeGroup);
        } else if (storedGroup) {
            openNavGroup(storedGroup);
        } else if (navGroups.length > 0) {
            openNavGroup(navGroups[0]);
        }

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

        function semesterSortKey(semester) {
            const m = String(semester || '').match(/^S([12])-(\d{2})(\d{2})$/i);
            if (!m) return '9999999' + semester;
            return m[2].padStart(2, '0') + m[3].padStart(2, '0') + m[1];
        }

        window.PgposAutoSearch = Object.assign({}, window.PgposAutoSearch || {}, {
            debounce,
            escapeAttribute,
            canSearchInput,
            deriveSemesterFromDate,
            semesterSortKey,
        });

        const mobileBreakpoint = window.matchMedia('(max-width: 900px)');
        const mobileToggle = document.querySelector('[data-mobile-nav-toggle]');
        const mobileClose = document.querySelector('[data-mobile-nav-close]');
        const sidebarBackdrop = document.querySelector('[data-sidebar-backdrop]');
        const sidebarLinks = Array.from(document.querySelectorAll('.sidebar a'));

        function setMobileSidebarState(isOpen) {
            document.body.classList.toggle('mobile-sidebar-open', Boolean(isOpen) && mobileBreakpoint.matches);
            if (mobileToggle) {
                mobileToggle.setAttribute('aria-expanded', document.body.classList.contains('mobile-sidebar-open') ? 'true' : 'false');
            }
        }

        function closeMobileSidebar() {
            setMobileSidebarState(false);
        }

        mobileToggle?.addEventListener('click', () => {
            setMobileSidebarState(!document.body.classList.contains('mobile-sidebar-open'));
        });

        mobileClose?.addEventListener('click', closeMobileSidebar);
        sidebarBackdrop?.addEventListener('click', closeMobileSidebar);

        sidebarLinks.forEach((link) => {
            link.addEventListener('click', () => {
                if (mobileBreakpoint.matches) {
                    closeMobileSidebar();
                }
            });
        });

        mobileBreakpoint.addEventListener('change', (event) => {
            if (!event.matches) {
                closeMobileSidebar();
            }
        });

        document.addEventListener('focusin', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLInputElement) || target.type !== 'number' || target.dataset.wheelGuard === 'on') {
                return;
            }

            const handleWheel = (wheelEvent) => {
                wheelEvent.preventDefault();
                target.blur();
            };

            target.addEventListener('wheel', handleWheel, { passive: false });
            target.dataset.wheelGuard = 'on';
            target._wheelGuardHandler = handleWheel;
        });

        document.addEventListener('focusout', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLInputElement) || target.type !== 'number') {
                return;
            }

            if (typeof target._wheelGuardHandler === 'function') {
                target.removeEventListener('wheel', target._wheelGuardHandler);
                delete target._wheelGuardHandler;
            }

            delete target.dataset.wheelGuard;
        });

        closeMobileSidebar();
        window.addEventListener('pageshow', closeMobileSidebar);
    })();
</script>
<script>
    (function () {
        const autoHide = (alertNode) => {
            if (!alertNode) return;
            setTimeout(() => {
                alertNode.classList.add('is-hiding');
                setTimeout(() => alertNode.remove(), 220);
            }, 10000);
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

    (function () {
        const savingLabel = @json(__('ui.saving'));
        document.addEventListener('submit', (event) => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement)) return;
            if ((form.method || '').toLowerCase() === 'get') return;
            if (form.hasAttribute('data-no-disable')) return;
            if (!form.checkValidity()) return;
            const btn = form.querySelector('button[type="submit"]:not([data-no-disable])');
            if (!btn || btn.disabled) return;
            setTimeout(() => {
                if (form.checkValidity()) {
                    btn.disabled = true;
                    btn.dataset.originalText = btn.textContent;
                    btn.textContent = savingLabel;
                }
            }, 0);
        }, true);
    })();
</script>
</body>
</html>
