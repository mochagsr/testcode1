@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination Navigation" class="flex" style="justify-content: space-between;">
        <div class="flex">
            @if ($paginator->onFirstPage())
                <span class="btn secondary" style="opacity:.6; cursor:not-allowed;">{{ __('ui.prev') }}</span>
            @else
                <a class="btn secondary" href="{{ $paginator->previousPageUrl() }}" rel="prev">{{ __('ui.prev') }}</a>
            @endif
        </div>

        <div class="flex">
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="btn secondary" style="opacity:.7;">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="btn">{{ $page }}</span>
                        @else
                            <a class="btn secondary" href="{{ $url }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach
        </div>

        <div class="flex">
            @if ($paginator->hasMorePages())
                <a class="btn secondary" href="{{ $paginator->nextPageUrl() }}" rel="next">{{ __('ui.next') }}</a>
            @else
                <span class="btn secondary" style="opacity:.6; cursor:not-allowed;">{{ __('ui.next') }}</span>
            @endif
        </div>
    </nav>
@endif
