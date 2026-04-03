<section>
    @if ($show && $email)
        <article>
            <h1>{{ $email->subject }}</h1>
            <p>{{ $email->from_address }}</p>
            <p>{{ $email->folder }}</p>

            <button type="button" wire:click="close">Close</button>
            <button type="button" wire:click="openInNewTab">Open in new tab</button>

            @if ($sanitizedBodyHtml !== '')
                <div>{!! $sanitizedBodyHtml !!}</div>
            @else
                <div>{{ $email->body_text }}</div>
            @endif

            <ul>
                @foreach ($email->attachments ?? [] as $attachment)
                    <li>{{ $attachment['filename'] ?? 'unknown' }} ({{ $attachment['filetype'] ?? 'unknown' }})</li>
                @endforeach
            </ul>
        </article>
    @endif
</section>
