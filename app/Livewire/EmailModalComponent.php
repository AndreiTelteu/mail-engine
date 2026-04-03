<?php

namespace App\Livewire;

use App\Models\Email;
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

        $this->iframeDocument = $this->buildIframeDocument($this->email);
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

    protected function buildIframeDocument(Email $email): string
    {
        if (filled($email->body_html)) {
            return $this->wrapEmailHtmlDocument($email->body_html);
        }

        $plainTextBody = e($email->body_text ?? '');

        return $this->wrapEmailHtmlDocument(<<<HTML
<pre style="margin:0; white-space:pre-wrap; word-break:break-word; font:14px/1.6 Arial, Helvetica, sans-serif; color:#111827;">{$plainTextBody}</pre>
HTML);
    }

    protected function wrapEmailHtmlDocument(string $content): string
    {
        $trimmedContent = trim($content);

        if ($trimmedContent === '') {
            return <<<'HTML'
<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
    </head>
    <body></body>
</html>
HTML;
        }

        if (preg_match('/<(?:!DOCTYPE|html|body)\b/i', $trimmedContent) === 1) {
            return $trimmedContent;
        }

        return <<<HTML
<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <base target="_blank">
        <style>
            html, body {
                margin: 0;
                padding: 0;
                background: #ffffff;
            }

            img, table {
                max-width: 100%;
            }
        </style>
    </head>
    <body>{$trimmedContent}</body>
</html>
HTML;
    }

    public function render()
    {
        return view('livewire.email-modal-component')
            ->layout('layouts.app', [
                'title' => __('Email detail'),
            ]);
    }
}
