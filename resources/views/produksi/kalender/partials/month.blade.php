<div class="pk-panel">
    <div class="pk-grid" style="margin-bottom:6px;">
        @foreach(['Sen','Sel','Rab','Kam','Jum','Sab','Min'] as $dn)
            <div class="head">{{ $dn }}</div>
        @endforeach
    </div>

    @foreach($weeks as $week)
        <div class="pk-week" style="grid-auto-rows: minmax(84px, auto);">
            @foreach($week['days'] as $day)
                <div class="pk-cell {{ $day['inMonth'] ? '' : 'out' }} {{ $day['isToday'] ? 'today' : '' }}">
                    <div class="d">{{ $day['day'] }}</div>
                </div>
            @endforeach

            <div class="pk-bars" style="grid-template-rows: repeat({{ $week['lanes'] }}, 20px);">
                @foreach($week['bars'] as $bar)
                    @php $bs = $bar['spk']; $bstatus = $bs->computedStatus(); @endphp
                    <div class="pk-bar {{ $bstatus }} pk-spk-el"
                         data-konsumen="{{ $bs->konsumen }}"
                         data-status="{{ $bstatus }}"
                         data-spk-id="{{ $bs->id }}"
                         title="{{ $bs->no_spk }} — {{ $bs->konsumen }}"
                         style="grid-column: {{ $bar['si'] + 1 }} / span {{ $bar['span'] }}; grid-row: {{ $bar['lane'] + 1 }};">
                        {{ $bs->no_spk }} · {{ $bs->konsumen }}
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach

    @if($spks->isEmpty())
        <p class="muted" style="text-align:center; padding:24px;">Belum ada SPK pada bulan ini.</p>
    @endif
</div>
