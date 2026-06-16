@props([
    'label' => 'Export',
    'options' => [],
    'size' => 'md',
    'mode' => 'open',
])

@php
    $sizeClass = match ($size) {
        'sm' => 'action-menu-sm',
        'lg' => 'action-menu-lg',
        default => 'action-menu-md',
    };
    $changeAction = $mode === 'location'
        ? "if(this.value){window.location.href=this.value; this.selectedIndex=0;}"
        : "if(this.value){window.open(this.value,'_blank'); this.selectedIndex=0;}";
@endphp

<select {{ $attributes->merge(['class' => 'action-menu '.$sizeClass, 'aria-label' => $label]) }} onchange="{{ $changeAction }}">
    <option value="" selected disabled>{{ $label }}</option>
    @foreach($options as $option)
        <option
            value="{{ $option['url'] ?? '' }}"
            @foreach(($option['attributes'] ?? []) as $attribute => $value)
                {{ $attribute }}="{{ $value }}"
            @endforeach
        >{{ $option['label'] ?? '-' }}</option>
    @endforeach
</select>
