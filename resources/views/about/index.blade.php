@extends('layouts.app')

@section('title', 'About - '.config('app.name', 'Laravel'))

@section('content')
    <style>
        .about-hero {
            display: grid;
            gap: 10px;
            justify-items: center;
            text-align: center;
            padding: 30px 18px;
        }
        .about-title {
            margin: 0;
            font-size: clamp(28px, 5vw, 54px);
            line-height: 1.05;
            letter-spacing: -0.04em;
        }
        .about-subtitle {
            margin: 0;
            color: var(--muted);
            max-width: 720px;
        }
        .about-updates {
            max-height: 520px;
            overflow: auto;
            border: 1px solid var(--border);
            border-radius: 12px;
        }
        .about-update-row {
            display: grid;
            grid-template-columns: 120px minmax(130px, 180px) minmax(0, 1fr);
            gap: 12px;
            align-items: start;
            padding: 12px 14px;
            border-bottom: 1px solid var(--border);
        }
        .about-update-row:last-child {
            border-bottom: 0;
        }
        .about-update-hash {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 13px;
            font-weight: 800;
            color: var(--accent);
            word-break: break-all;
        }
        .about-update-date {
            color: var(--muted);
            font-size: 13px;
        }
        .about-update-message {
            font-weight: 700;
            line-height: 1.45;
        }
        .about-empty {
            padding: 18px;
            color: var(--muted);
        }
        @media (max-width: 720px) {
            .about-update-row {
                grid-template-columns: 1fr;
                gap: 4px;
            }
        }
    </style>

    <div class="card">
        <div class="about-hero">
            <h1 class="about-title">{{ $appLabel }}</h1>
            <p class="about-subtitle">
                Halaman ini berisi riwayat update aplikasi. Daftar update diambil otomatis dari Git commit terbaru.
            </p>
        </div>
    </div>

    <div class="card" style="margin-top: 14px;">
        <div class="flex" style="margin-bottom: 12px;">
            <h2 class="page-title" style="margin: 0;">List Update</h2>
            <span class="muted">Commit terbaru tampil paling atas</span>
        </div>

        <div class="about-updates">
            @forelse($updates as $update)
                <div class="about-update-row">
                    <div class="about-update-hash">{{ $update['short_hash'] }}</div>
                    <div class="about-update-date">
                        @if(!empty($update['committed_at']))
                            {{ \Illuminate\Support\Carbon::parse($update['committed_at'])->timezone(config('app.timezone', 'Asia/Jakarta'))->format('d-m-Y H:i') }}
                        @else
                            -
                        @endif
                    </div>
                    <div class="about-update-message">{{ $update['message'] }}</div>
                </div>
            @empty
                <div class="about-empty">
                    Riwayat update belum tersedia. Ini bisa terjadi kalau folder <code>.git</code> tidak ikut tersedia di server.
                </div>
            @endforelse
        </div>
    </div>
@endsection
