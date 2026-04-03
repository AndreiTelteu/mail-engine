<?php

namespace App\Livewire;

use App\Models\Email;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class EmailModalComponent extends Component
{
    public ?Email $email = null;

    public bool $show = false;

    public string $sanitizedBodyHtml = '';

    public function open(int $emailId): void
    {
        $this->email = Email::query()
            ->whereBelongsTo(Auth::user())
            ->findOrFail($emailId);

        $this->sanitizedBodyHtml = $this->sanitizeHtml($this->email->body_html);
        $this->show = true;
    }

    public function close(): void
    {
        $this->show = false;
    }

    public function openInNewTab(): void
    {
        if (! $this->email instanceof Email) {
            return;
        }

        $this->dispatch('open-email-in-new-tab', url: url("/emails/{$this->email->id}"));
    }

    public function sanitizeHtml(?string $html): string
    {
        if (blank($html)) {
            return '';
        }

        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument;

        if (! @$document->loadHTML(
            mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'),
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING,
        )) {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);

            return strip_tags($html, '<a><b><blockquote><br><code><div><em><i><li><ol><p><pre><span><strong><table><tbody><td><th><thead><tr><u><ul>');
        }

        $xpath = new DOMXPath($document);

        foreach ($xpath->query('//script|//iframe|//object|//embed|//link|//meta|//style|//form') as $node) {
            $node->parentNode?->removeChild($node);
        }

        foreach ($xpath->query('//*') as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $attributes = [];

            foreach ($node->attributes as $attribute) {
                $attributes[] = $attribute->name;
            }

            foreach ($attributes as $attributeName) {
                $value = (string) $node->getAttribute($attributeName);

                if (str_starts_with(strtolower($attributeName), 'on') || strtolower($attributeName) === 'style') {
                    $node->removeAttribute($attributeName);

                    continue;
                }

                if (in_array(strtolower($attributeName), ['href', 'src', 'xlink:href', 'formaction'], true)
                    && str_starts_with(strtolower(trim($value)), 'javascript:')) {
                    $node->removeAttribute($attributeName);
                }
            }
        }

        $sanitized = $document->saveHTML() ?: '';

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $sanitized;
    }

    public function render()
    {
        return view('livewire.email-modal-component');
    }
}
