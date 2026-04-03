<?php

namespace App\Livewire;

use App\Models\Email;
use App\Services\EmailSearchService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class EmailSearchComponent extends Component
{
    use WithPagination;

    public string $query = '';

    public ?string $folderFilter = null;

    public ?int $selectedEmailId = null;

    public int $perPage = 10;

    protected $queryString = [
        'query' => ['except' => ''],
        'folderFilter' => ['except' => null],
    ];

    public function updatedQuery(): void
    {
        $this->resetPage();
    }

    public function updatedFolderFilter(): void
    {
        $this->resetPage();
    }

    public function selectEmail(int $emailId): void
    {
        $this->selectedEmailId = $emailId;
    }

    #[On('close-email-modal')]
    public function closeModal(): void
    {
        $this->selectedEmailId = null;
    }

    public function render()
    {
        $user = Auth::user();
        $searchService = app(EmailSearchService::class);

        $results = blank($this->query) && $this->folderFilter === null
            ? $searchService->getRecent($user, $this->perPage)
            : $searchService->search($user, $this->query, $this->folderFilter, $this->perPage);

        $formattedResults = collect($results->items())
            ->mapWithKeys(fn (Email $email): array => [
                $email->id => $searchService->formatResult($email, $this->query ?: null),
            ]);

        $folders = Email::query()
            ->whereBelongsTo($user)
            ->select('folder')
            ->distinct()
            ->orderBy('folder')
            ->pluck('folder');

        return view('livewire.email-search-component', [
            'folders' => $folders,
            'formattedResults' => $formattedResults,
            'results' => $results,
        ]);
    }
}
