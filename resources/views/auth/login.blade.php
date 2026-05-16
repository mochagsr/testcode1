<!doctype html>
@php
    $applicationName = config('app.name', 'Laravel');
    $logoPath = \App\Models\AppSetting::getValue('company_logo_path');
    $logoUrl = $logoPath ? asset('storage/'.$logoPath) : null;
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('ui.login_title') }} - {{ $applicationName }}</title>
    <style>
        :root {
            --bg: #f5f7fb;
            --card: #ffffff;
            --text: #182230;
            --muted: #667085;
            --border: #d9e0ea;
            --brand: #2457c5;
            --brand-soft: #eef5ff;
            --danger: #b42318;
        }
        body {
            margin:0;
            font-family: "Aptos", "Segoe UI", "Noto Sans", Tahoma, sans-serif;
            background:
                radial-gradient(circle at top left, rgba(47,111,237,.13), transparent 32rem),
                linear-gradient(180deg, #fbfcff 0%, var(--bg) 52%, #eef2f7 100%);
            color:var(--text);
        }
        .wrap { min-height:100vh; display:grid; place-items:center; padding:20px; }
        .card {
            width:100%;
            max-width:430px;
            background:var(--card);
            border:1px solid var(--border);
            border-radius:18px;
            padding:24px;
            box-shadow:0 24px 70px rgba(16,24,40,.14);
        }
        .login-brand {
            display:flex;
            align-items:center;
            gap:12px;
            margin-bottom:22px;
            padding-bottom:18px;
            border-bottom:1px solid var(--border);
        }
        .login-logo {
            width:56px;
            height:56px;
            border-radius:16px;
            object-fit:contain;
            background:var(--brand-soft);
            border:1px solid color-mix(in srgb, var(--brand) 18%, var(--border) 82%);
            padding:7px;
            flex:0 0 auto;
        }
        .login-logo-fallback {
            display:inline-flex;
            align-items:center;
            justify-content:center;
            color:var(--brand);
            font-weight:800;
            font-size:20px;
            letter-spacing:-.04em;
        }
        .login-app-name {
            font-size:22px;
            line-height:1.1;
            font-weight:850;
            letter-spacing:-.04em;
        }
        .login-app-subtitle {
            margin-top:4px;
            color:var(--muted);
            font-size:13px;
        }
        h1 { margin:0 0 16px; font-size:24px; letter-spacing:-.04em; }
        label { display:block; margin-bottom:7px; font-size:13px; font-weight:700; color:var(--muted); }
        .label-required { color:#c0392b; font-weight:700; }
        input {
            width:100%;
            box-sizing:border-box;
            border:1px solid var(--border);
            border-radius:11px;
            padding:11px 12px;
            margin-bottom:13px;
            min-height:42px;
            font:inherit;
        }
        input:focus {
            outline:none;
            border-color:var(--brand);
            box-shadow:0 0 0 4px rgba(47,111,237,.12);
        }
        .password-wrap { display:flex; gap:8px; align-items:center; margin-bottom:12px; }
        .password-wrap input { margin-bottom:0; }
        .password-wrap button { width:auto; padding:10px 12px; border-radius:11px; background:#344054; color:#fff; border:none; white-space:nowrap; cursor:pointer; font-weight:750; }
        button { width:100%; border:none; border-radius:11px; padding:11px; background:var(--brand); color:#fff; cursor:pointer; font-weight:800; box-shadow:0 8px 18px rgba(36,87,197,.18); }
        .error { background:#fef3f2; border:1px solid #fecdca; color:var(--danger); border-radius:11px; padding:10px; margin-bottom:12px; }
        .hint { margin-top:12px; font-size:12px; color:var(--muted); }
        .remember-label { display:flex; align-items:center; gap:8px; margin-bottom:14px; color:var(--text); font-weight:500; }
        @media (max-width: 520px) {
            .wrap { padding:14px; }
            .card { padding:20px; }
            .login-logo { width:50px; height:50px; border-radius:14px; }
            .login-app-name { font-size:20px; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <form method="post" action="{{ route('login.post') }}" class="card">
        @csrf
        <div class="login-brand">
            @if($logoUrl)
                <img class="login-logo" src="{{ $logoUrl }}" alt="{{ $applicationName }} logo">
            @else
                <div class="login-logo login-logo-fallback" aria-hidden="true">{{ mb_substr($applicationName, 0, 2) }}</div>
            @endif
            <div>
                <div class="login-app-name">{{ $applicationName }}</div>
                <div class="login-app-subtitle">{{ __('ui.login_title') }}</div>
            </div>
        </div>
        <h1>{{ __('ui.login_title') }}</h1>

        @if ($errors->any())
            <div class="error">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <label>{{ __('ui.login_identifier') }} <span class="label-required">*</span></label>
        <input type="text" name="login" value="{{ old('login') }}" required>

        <label>{{ __('ui.password') }} <span class="label-required">*</span></label>
        <div class="password-wrap">
            <input id="login-password-input" type="password" name="password" required>
            <button
                id="toggle-login-password"
                type="button"
                aria-controls="login-password-input"
                aria-label="{{ __('ui.show_password') }}"
                data-label-show="{{ __('ui.show_password') }}"
                data-label-hide="{{ __('ui.hide_password') }}"
            >{{ __('ui.show_password') }}</button>
        </div>

        <label class="remember-label">
            <input type="checkbox" name="remember" value="1" style="width:auto; margin:0;">
            {{ __('ui.remember_me') }}
        </label>

        <button type="submit">{{ __('ui.sign_in') }}</button>
        <div class="hint">{{ __('ui.login_hint') }}</div>
    </form>
</div>
<script>
    (function () {
        const passwordInput = document.getElementById('login-password-input');
        const toggle = document.getElementById('toggle-login-password');
        if (!passwordInput || !toggle) {
            return;
        }
        toggle.addEventListener('click', () => {
            const show = passwordInput.type === 'password';
            passwordInput.type = show ? 'text' : 'password';
            const showLabel = String(toggle.getAttribute('data-label-show') || 'Show');
            const hideLabel = String(toggle.getAttribute('data-label-hide') || 'Hide');
            toggle.textContent = show ? hideLabel : showLabel;
            toggle.setAttribute('aria-label', show ? hideLabel : showLabel);
        });
    })();

    // Auto-reload before session expires to prevent 419 CSRF errors.
    // SESSION_LIFETIME=120 min — reload at 110 min so token stays fresh.
    setTimeout(() => location.reload(), 110 * 60 * 1000);
</script>
</body>
</html>
