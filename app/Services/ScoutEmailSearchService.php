<?php

namespace App\Services;

use App\DataTransferObjects\EmailSearchResult;
use App\Models\Email;
use App\Models\User;
use Closure;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Throwable;

class ScoutEmailSearchService implements EmailSearchService
{
    public function __construct(
        protected ?Closure $searchExecutor = null,
    ) {}

    public function search(User $user, string $query, ?string $folder = null, int $perPage = 20): LengthAwarePaginator
    {
        $query = trim($query);

        if ($query === '') {
            return $this->recent($user, $folder, $perPage);
        }

        try {
            $page = Paginator::resolveCurrentPage();
            $builder = Email::search($query);

            $builder->where('user_id', $user->id);

            if ($folder !== null) {
                $builder->where('folder', $folder);
            }

            /** @var array{found?: int, hits?: array<int, array<string, mixed>>} $results */
            $builder->options([
                'page' => $page,
                'per_page' => $perPage,
            ]);
            $results = $this->searchExecutor instanceof Closure
                ? ($this->searchExecutor)($builder, $page, $perPage)
                : $builder->raw();

            return new LengthAwarePaginator(
                collect($results['hits'] ?? [])
                    ->map(fn (array $hit): EmailSearchResult => EmailSearchResult::fromTypesenseHit($hit)),
                (int) ($results['found'] ?? 0),
                $perPage,
                $page,
                ['path' => Paginator::resolveCurrentPath()],
            );
        } catch (Throwable $exception) {
            report($exception);

            throw new ServiceUnavailableHttpException(
                retryAfter: null,
                message: 'Typesense is unavailable. Search cannot run until it is reachable again.',
                previous: $exception,
            );
        }
    }

    public function getRecent(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return $this->recent($user, null, $perPage);
    }

    private function recent(User $user, ?string $folder, int $perPage): LengthAwarePaginator
    {
        return $this->recentQuery($user, $folder)
            ->paginate($perPage)
            ->through(fn (Email $email): EmailSearchResult => EmailSearchResult::fromEmail($email));
    }

    protected function recentQuery(User $user, ?string $folder = null)
    {
        return Email::query()
            ->select($this->resultColumns())
            ->whereBelongsTo($user)
            ->when($folder !== null, fn ($emailQuery) => $emailQuery->where('folder', $folder))
            ->orderByDesc('date');
    }

    /**
     * @return array<int, string>
     */
    protected function resultColumns(): array
    {
        return [
            'id',
            'user_id',
            'folder',
            'from_address',
            'from_name',
            'subject',
            'date',
            'preview',
            'attachment_count',
        ];
    }
}
