@props([
    'status',
])

@if ($status)
    <div {{ $attributes->merge(['class' => 'text-sm font-medium text-positive-text']) }}>
        {{ $status }}
    </div>
@endif
