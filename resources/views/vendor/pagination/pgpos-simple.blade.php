@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination Navigation" class="flex" style="justify-content: space-between;">
        @if ($paginator->onFirstPage())
            <span class="btn secondary" style="opacity:.6; cursor:not-allowed;">{{ __('ui.prev') }}</span>
        @else
            <a class="btn secondary" href="{{ $paginator->previousPageUrl() }}" rel="prev">{{ __('ui.prev') }}</a>
        @endif

        @if ($paginator->hasMorePages())
            <a class="btn secondary" href="{{ $paginator->nextPageUrl() }}" rel="next">{{ __('ui.next') }}</a>
        @else
            <span class="btn secondary" style="opacity:.6; cursor:not-allowed;">{{ __('ui.next') }}</span>
        @endif
    </nav>
@endif
