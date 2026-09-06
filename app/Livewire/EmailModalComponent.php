<?php

namespace App\Livewire;

use App\Models\Email;
use App\Services\EmailHtmlDocumentService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class EmailModalComponent extends Component
{
    public bool $standalone = false;

    public ?int $emailId = null;

    public ?Email $email = null;

    public bool $show = false;

    public string $iframeDocument = '';

    public function mount(?int $emailId = null): void
    {
        $this->standalone = request()->routeIs('emails.show');
        $this->emailId = $emailId;

        if ($emailId !== null) {
            $this->open($emailId);
        }
    }

    public function open(int $emailId): void
    {
        $this->email = Email::query()
            ->whereBelongsTo(Auth::user())
            ->findOrFail($emailId);

        $this->iframeDocument = app(EmailHtmlDocumentService::class)->build(
            $this->email->body_html,
            $this->email->body_text,
        );
        $this->show = true;
    }

    public function close(): void
    {
        $this->show = false;

        if ($this->standalone) {
            $this->redirect(route('emails.index', absolute: false), navigate: true);

            return;
        }

        $this->dispatch('close-email-modal');
    }

    public function openInNewTab(): void
    {
        if (! $this->email instanceof Email) {
            return;
        }

        $this->dispatch('open-email-in-new-tab', url: route('emails.show', ['emailId' => $this->email->id]));
    }

    public function render()
    {
        return view('livewire.email-modal-component')
            ->layout('layouts.app', [
                'title' => __('Email detail'),
            ]);
    }
}
