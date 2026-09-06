<?php

namespace App\DataTransferObjects;

use App\Models\Email;

readonly class EmailSearchResult
{
    public function __construct(
        public int $id,
        public string $from,
        public string $subject,
        public string $preview,
        public string $folder,
        public ?string $date,
        public int $attachmentCount,
    ) {}

    public static function fromEmail(Email $email): self
    {
        $from = $email->from_name
            ? "{$email->from_name} <{$email->from_address}>"
            : $email->from_address;

        return new self(
            id: $email->id,
            from: e($from),
            subject: e($email->subject ?: '(no subject)'),
            preview: e($email->preview),
            folder: $email->folder,
            date: $email->date?->toIso8601String(),
            attachmentCount: $email->attachment_count,
        );
    }

    /**
     * @param  array<string, mixed>  $hit
     */
    public static function fromTypesenseHit(array $hit): self
    {
        /** @var array<string, mixed> $document */
        $document = $hit['document'];
        $highlights = self::highlights($hit);
        $fromName = (string) ($document['from_name'] ?? '');
        $fromAddress = (string) ($document['from_address'] ?? '');
        $from = $fromName !== ''
            ? self::highlight($highlights['from_name'] ?? $fromName).' &lt;'.self::highlight($highlights['from_address'] ?? $fromAddress).'&gt;'
            : self::highlight($highlights['from_address'] ?? $fromAddress);
        $preview = $highlights['body_current']
            ?? $highlights['body_quoted']
            ?? $highlights['preview']
            ?? (string) ($document['preview'] ?? '');

        return new self(
            id: (int) $document['id'],
            from: $from,
            subject: self::highlight($highlights['subject'] ?? (string) ($document['subject'] ?? '(no subject)')),
            preview: self::highlight($preview),
            folder: (string) ($document['folder'] ?? ''),
            date: isset($document['date']) ? now()->setTimestamp((int) $document['date'])->toIso8601String() : null,
            attachmentCount: (int) ($document['attachment_count'] ?? 0),
        );
    }

    /**
     * @return array{id:int,from:string,subject:string,preview:string,folder:string,date:?string,attachmentCount:int}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'from' => $this->from,
            'subject' => $this->subject,
            'preview' => $this->preview,
            'folder' => $this->folder,
            'date' => $this->date,
            'attachmentCount' => $this->attachmentCount,
        ];
    }

    /**
     * @param  array<string, mixed>  $hit
     * @return array<string, string>
     */
    private static function highlights(array $hit): array
    {
        return collect($hit['highlights'] ?? $hit['highlight'] ?? [])
            ->mapWithKeys(function (array $highlight): array {
                $field = $highlight['field'] ?? null;
                $snippet = $highlight['snippet'] ?? $highlight['value'] ?? null;

                return is_string($field) && is_string($snippet) ? [$field => $snippet] : [];
            })
            ->all();
    }

    private static function highlight(string $value): string
    {
        $parts = preg_split('/(<mark>.*?<\/mark>)/isu', $value, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];

        return collect($parts)
            ->map(function (string $part): string {
                if (preg_match('/^<mark>(.*)<\/mark>$/isu', $part, $matches) === 1) {
                    return '<mark>'.e(strip_tags($matches[1])).'</mark>';
                }

                return e($part);
            })
            ->join('');
    }
}
