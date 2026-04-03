<section>
    <input wire:model.live.debounce.300ms="query" type="search" />

    <select wire:model.live="folderFilter">
        <option value="">All folders</option>
        @foreach ($folders as $folder)
            <option value="{{ $folder }}">{{ $folder }}</option>
        @endforeach
    </select>

    <div>
        @foreach ($results as $email)
            @php($result = $formattedResults[$email->id])

            <button type="button" wire:click="selectEmail({{ $email->id }})">
                <span>{!! $result['from'] !!}</span>
                <span>{!! $result['subject'] !!}</span>
                <span>{!! $result['preview'] !!}</span>
            </button>
        @endforeach
    </div>

    <div>{{ $results->links() }}</div>

    @if ($selectedEmailId !== null)
        <div>Selected email: {{ $selectedEmailId }}</div>
    @endif
</section>
