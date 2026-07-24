<div class="pk-panel">
    <table class="pk-table" style="table-layout:fixed;">
        <thead>
            <tr>
                <th style="width:160px;">Mesin</th>
                @foreach($timeline['cols'] as $col)
                    <th class="{{ $col['isToday'] ? '' : '' }}" style="text-align:center;{{ $col['isToday'] ? 'color:#2f4b8f;' : '' }}">{{ $col['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($timeline['rows'] as $row)
                <tr>
                    <td style="font-weight:600;">{{ $row['name'] }}</td>
                    @foreach($row['cells'] as $cell)
                        <td class="pk-tl-cell">
                            @foreach($cell as $chip)
                                @php $cs = $chip['spk']; $cstatus = $cs->computedStatus(); @endphp
                                <span class="pk-tl-chip {{ $cstatus }} pk-spk-el"
                                      data-konsumen="{{ $cs->konsumen }}"
                                      data-status="{{ $cstatus }}"
                                      data-spk-id="{{ $cs->id }}"
                                      title="{{ $cs->no_spk }} — {{ $cs->konsumen }}">{{ $cs->no_spk }}</span>
                            @endforeach
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
    <p class="muted" style="font-size:11px; margin-top:8px;">Menampilkan tahap terjadwal minggu ini (Senin–Minggu).</p>
</div>
