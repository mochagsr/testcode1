@props(['style' => ''])

{{-- Horizontal-scroll wrapper for wide tables. Reusable across list pages. --}}
<div {{ $attributes->merge(['class' => 'table-scroll']) }} style="overflow-x:auto;{{ $style }}">
    {{ $slot }}
</div>
