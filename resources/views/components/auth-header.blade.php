@props([
    'title',
    'description',
])

<div class="flex w-full flex-col gap-1">
    <flux:heading size="lg">{{ $title }}</flux:heading>
    <flux:subheading>{{ $description }}</flux:subheading>
</div>
