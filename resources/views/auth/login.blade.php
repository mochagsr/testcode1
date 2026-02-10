<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('ui.login_title') }} - PgPOS ERP</title>
    <style>
        body { margin:0; font-family: "Segoe UI", Tahoma, sans-serif; background:#f3f3f3; color:#111; }
        .wrap { min-height:100vh; display:grid; place-items:center; padding:20px; }
        .card { width:100%; max-width:420px; background:#fff; border:1px solid #ddd; border-radius:10px; padding:20px; }
        h1 { margin:0 0 14px; font-size:24px; }
        label { display:block; margin-bottom:6px; font-size:14px; }
        .label-required { color:#c0392b; font-weight:700; }
        input { width:100%; box-sizing:border-box; border:1px solid #ccc; border-radius:8px; padding:10px; margin-bottom:12px; }
        button { width:100%; border:none; border-radius:8px; padding:10px; background:#111; color:#fff; cursor:pointer; }
        .error { background:#fff0f0; border:1px solid #f1a5a5; border-radius:8px; padding:10px; margin-bottom:10px; }
        .hint { margin-top:10px; font-size:12px; color:#666; }
    </style>
</head>
<body>
<div class="wrap">
    <form method="post" action="{{ route('login.post') }}" class="card">
        @csrf
        <h1>{{ __('ui.login_title') }}</h1>

        @if ($errors->any())
            <div class="error">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <label>{{ __('ui.email') }} <span class="label-required">*</span></label>
        <input type="email" name="email" value="{{ old('email') }}" required>

        <label>{{ __('ui.password') }} <span class="label-required">*</span></label>
        <input type="password" name="password" required>

        <label style="display:flex; align-items:center; gap:6px; margin-bottom:12px;">
            <input type="checkbox" name="remember" value="1" style="width:auto; margin:0;">
            {{ __('ui.remember_me') }}
        </label>

        <button type="submit">{{ __('ui.sign_in') }}</button>
        <div class="hint">{{ __('ui.login_hint') }}</div>
    </form>
</div>
</body>
</html>
