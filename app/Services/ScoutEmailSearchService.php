<?php

namespace App\Services;

use App\Models\Email;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Laravel\Scout\Builder as ScoutBuilder;
use Throwable;

class ScoutEmailSearchService implements EmailSearchService
{
    public function __construct(
        protected mixed $searchExecutor = null,
    ) {}

    public function search(User $user, string $query, ?string $folder = null, int $perPage = 20): LengthAwarePaginator
    {
        $query = trim($query);

        if ($query === '') {
            return $this->recentQuery($user, $folder)->paginate($perPage);
        }

        if (! $this->shouldUseScout()) {
            return $this->fallbackSearch($user, $query, $folder, $perPage);
        }

        try {
            $builder = is_callable($this->searchExecutor)
                ? new ScoutBuilder(new Email, $query)
                : Email::search($query);

            $builder->where('user_id', $user->id);

            if ($folder !== null) {
                $builder->where('folder', $folder);
            }

            if (is_callable($this->searchExecutor)) {
                return ($this->searchExecutor)($builder, $perPage);
            }

            return $builder->paginate($perPage);
        } catch (Throwable) {
            $this->flashFallbackWarning();

            return $this->fallbackSearch($user, $query, $folder, $perPage);
        }
    }

    public function getRecent(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return $this->recentQuery($user)->paginate($perPage);
    }

    /**
     * Result fields are HTML: message text is escaped here and matched terms are
     * wrapped in `<mark>`, so callers render them as markup without trusting mail
     * content.
     */
    public function formatResult(Email $email, ?string $query = null): array
    {
        $from = e($email->from_name ? "{$email->from_name} <{$email->from_address}>" : (string) $email->from_address);
        $subject = e($email->subject ?: '(no subject)');
        $previewSource = $email->body_text ?: strip_tags((string) $email->body_html);
        $preview = e($this->makePreview($previewSource));

        if ($query !== null && trim($query) !== '') {
            $from = $this->highlightTerms($from, $query);
            $subject = $this->highlightTerms($subject, $query);
            $preview = $this->highlightTerms($preview, $query);
        }

        return [
            'from' => $from,
            'subject' => $subject,
            'preview' => $preview,
            'display' => trim("{$from}: {$subject} {$preview}"),
            'folder' => $email->folder,
            'date' => $email->date?->toIso8601String() ?? '',
        ];
    }

    protected function shouldUseScout(): bool
    {
        return filled(config('scout.driver'));
    }

    protected function fallbackSearch(User $user, string $query, ?string $folder, int $perPage): LengthAwarePaginator
    {
        return Email::query()
            ->whereBelongsTo($user)
            ->when($folder !== null, fn ($emailQuery) => $emailQuery->where('folder', $folder))
            ->where(function ($emailQuery) use ($query): void {
                $like = '%'.$query.'%';

                $emailQuery
                    ->where('from_address', 'like', $like)
                    ->orWhere('from_name', 'like', $like)
                    ->orWhere('subject', 'like', $like)
                    ->orWhere('body_text', 'like', $like)
                    ->orWhere('body_html', 'like', $like);
            })
            ->orderByDesc('date')
            ->paginate($perPage);
    }

    protected function recentQuery(User $user, ?string $folder = null)
    {
        return Email::query()
            ->whereBelongsTo($user)
            ->when($folder !== null, fn ($emailQuery) => $emailQuery->where('folder', $folder))
            ->orderByDesc('date');
    }

    protected function makePreview(?string $text): string
    {
        return Str::limit(
            preg_replace('/\s+/u', ' ', trim((string) $text)) ?: '',
            140,
        );
    }

    /**
     * Wraps matched terms in `<mark>` inside already-escaped text.
     */
    protected function highlightTerms(string $text, string $query): string
    {
        $terms = collect(preg_split('/\s+/u', trim($query)) ?: [])
            ->map(fn (string $term): string => e($term))
            ->filter()
            ->unique()
            ->sortByDesc(fn (string $term): int => mb_strlen($term))
            ->values();

        return $terms->reduce(
            fn (string $carry, string $term): string => preg_replace(
                '/('.preg_quote($term, '/').')/iu',
                '<mark>$1</mark>',
                $carry,
            ) ?? $carry,
            $text,
        );
    }

    protected function flashFallbackWarning(): void
    {
        if (app()->bound('session')) {
            session()->flash('warning', 'Search is temporarily unavailable. Showing basic results.');
        }
    }
}
