<?php

namespace App\Livewire;

use App\Jobs\SyncUserEmailsJob;
use App\Models\SyncSession;
use App\Models\SyncSessionLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class SyncModalComponent extends Component
{
    public bool $showModal = false;

    public ?int $syncSessionId = null;

    public bool $justCompleted = false;

    public function openModal(): void
    {
        $this->syncSessionId = SyncSession::query()
            ->where('user_id', Auth::id())
            ->latest()
            ->value('id');

        $this->showModal = true;
    }

    public function startSync(): void
    {
        $activeSession = SyncSession::query()
            ->where('user_id', Auth::id())
            ->whereIn('status', ['pending', 'counting', 'syncing'])
            ->exists();

        if ($activeSession) {
            return;
        }

        $this->justCompleted = false;

        $syncSession = SyncSession::create([
            'user_id' => Auth::id(),
            'status' => 'pending',
        ]);

        $this->syncSessionId = $syncSession->id;

        SyncUserEmailsJob::dispatch(Auth::id(), $syncSession->id);
    }

    public function resetSync(): void
    {
        if ($this->syncSessionId === null) {
            return;
        }

        $session = SyncSession::query()
            ->where('id', $this->syncSessionId)
            ->where('user_id', Auth::id())
            ->first();

        if (! $session instanceof SyncSession) {
            return;
        }

        $syncSessionId = $session->id;

        DB::table('jobs')
            ->where('payload', 'like', "%\"syncSessionId\":{$syncSessionId}%")
            ->orWhere('payload', 'like', "%\"syncSessionId\":\"{$syncSessionId}\"%")
            ->delete();

        $session->logs()->delete();
        $session->delete();

        $this->syncSessionId = SyncSession::query()
            ->where('user_id', Auth::id())
            ->latest()
            ->value('id');
    }

    public function pollSync(): void
    {
        if (! $this->showModal) {
            return;
        }

        $session = $this->syncSession;

        if ($session !== null && $session->status === 'completed' && ! $this->justCompleted) {
            $this->justCompleted = true;
            $this->dispatch('sync-completed');
        }
    }

    public function getSyncSessionProperty(): ?SyncSession
    {
        if ($this->syncSessionId === null) {
            return null;
        }

        return SyncSession::query()->find($this->syncSessionId);
    }

    /**
     * @return Collection<int, SyncSessionLog>
     */
    public function getLogsProperty(): Collection
    {
        if ($this->syncSessionId === null) {
            return collect();
        }

        return SyncSessionLog::query()
            ->where('sync_session_id', $this->syncSessionId)
            ->latest('id')
            ->limit(50)
            ->get()
            ->reverse()
            ->values();
    }

    public function getIsPollingProperty(): bool
    {
        $session = $this->syncSession;

        return $session !== null && $session->isActive();
    }

    public function render()
    {
        return view('livewire.sync-modal-component');
    }
}
