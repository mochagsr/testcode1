<div class="pk-panel">
    <table class="pk-table">
        <thead>
            <tr>
                <th>No. SPK</th>
                <th>Konsumen</th>
                <th>Jenis</th>
                <th>Deadline</th>
                <th>Status</th>
                <th style="width:160px;">Progress</th>
            </tr>
        </thead>
        <tbody>
            @forelse($listRows as $spk)
                @php $lstatus = $spk->computedStatus(); $pct = (int) round($spk->progress() * 100); @endphp
                <tr class="pk-spk-el pk-list-row" data-konsumen="{{ $spk->konsumen }}" data-status="{{ $lstatus }}" data-spk-id="{{ $spk->id }}" style="cursor:pointer;">
                    <td class="pk-mono" style="font-weight:600;">{{ $spk->no_spk }}</td>
                    <td>{{ $spk->konsumen }}<div class="muted" style="font-size:11px;">{{ $spk->alamat }}</div></td>
                    <td>{{ $spk->jenis_order }}</td>
                    <td class="pk-mono">{{ $spk->deadline_kirim?->format('d M Y') }}</td>
                    <td><span class="pk-status {{ $lstatus }}">{{ ucfirst($lstatus) }}</span></td>
                    <td>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <div class="pk-prog"><span style="width: {{ $pct }}%;"></span></div>
                            <span class="pk-mono" style="font-size:11px; color:#7a8798;">{{ $pct }}%</span>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted" style="text-align:center; padding:24px;">Belum ada SPK pada bulan ini.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
