<?php

namespace App\Services;

use App\Models\Email;
use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Throwable;

class EmailHtmlDocumentService
{
    public function __construct(protected ImapConnectionService $imap) {}

    public function buildForEmail(Email $email): string
    {
        $html = $email->body_html;
        $inlineImages = [];

        if (is_string($html) && preg_match_all('~\bcid:([^\s"\x27<>]+)~i', $html, $matches)) {
            $email->loadMissing('mailFolder.imapSetting');
            $contentIds = array_values(array_unique(array_map(
                fn (string $contentId): string => trim(rawurldecode($contentId), '<>()'),
                $matches[1],
            )));
            $connection = null;

            try {
                $setting = $email->mailFolder?->imapSetting;

                if ($setting !== null && $setting->user_id === $email->user_id) {
                    $connection = $this->imap->connect($setting);
                    $folder = $email->mailFolder->path;

                    if ($this->imap->getFolderStatus($connection, $folder)->uidValidity === (int) $email->uid_validity) {
                        $inlineImages = $this->imap->getInlineImages($connection, $folder, $email->imap_uid, $contentIds);
                    }
                }
            } catch (Throwable $exception) {
                report($exception);
            } finally {
                $connection?->disconnect();
            }
        }

        return $this->build($html, $email->body_text, $inlineImages);
    }

    /** @param array<string, array{mimeType: string, content: string}> $inlineImages */
    public function build(?string $html, ?string $text, array $inlineImages = []): string
    {
        if (blank($html)) {
            return $this->plainTextDocument($text ?? '');
        }

        $document = new DOMDocument;
        $previousInternalErrors = libxml_use_internal_errors(true);

        try {
            $document->loadHTML($html, LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousInternalErrors);
        }

        $xpath = new DOMXPath($document);
        foreach ($xpath->query('//script|//base|//form|//object|//embed|//iframe') ?: [] as $node) {
            $node->parentNode?->removeChild($node);
        }

        foreach ($xpath->query('//@*') ?: [] as $attribute) {
            if (! $attribute instanceof DOMAttr) {
                continue;
            }

            $attributeName = strtolower($attribute->name);
            $attributeValue = strtolower(trim($attribute->value));

            if (
                str_starts_with($attributeName, 'on')
                || (in_array($attributeName, ['href', 'src', 'action'], true) && str_starts_with($attributeValue, 'javascript:'))
            ) {
                $attribute->ownerElement?->removeAttributeNode($attribute);
            } elseif (in_array($attributeName, ['src', 'background'], true) && str_starts_with($attributeValue, 'cid:')) {
                $contentId = trim(rawurldecode(substr(trim($attribute->value), 4)), '<>');
                $image = $inlineImages[$contentId] ?? null;

                if ($image !== null) {
                    $attribute->value = 'data:'.$image['mimeType'].';base64,'.base64_encode($image['content']);
                }
            }
        }

        $head = $document->getElementsByTagName('head')->item(0);

        if (! $head instanceof DOMElement) {
            $htmlNode = $document->getElementsByTagName('html')->item(0);
            $head = $document->createElement('head');
            $htmlNode?->insertBefore($head, $htmlNode->firstChild);
        }

        $charset = $document->createElement('meta');
        $charset->setAttribute('charset', 'utf-8');
        $head->appendChild($charset);

        $contentSecurityPolicy = $document->createElement('meta');
        $contentSecurityPolicy->setAttribute('http-equiv', 'Content-Security-Policy');
        $contentSecurityPolicy->setAttribute('content', "default-src 'none'; img-src data: https: http:; style-src 'unsafe-inline'; font-src data:; media-src data:;");
        $head->appendChild($contentSecurityPolicy);

        $base = $document->createElement('base');
        $base->setAttribute('target', '_blank');
        $head->appendChild($base);

        return $document->saveHTML() ?: $this->plainTextDocument($text ?? '');
    }

    private function plainTextDocument(string $text): string
    {
        return '<!DOCTYPE html><html><head><meta charset="utf-8"><meta http-equiv="Content-Security-Policy" content="default-src \'none\'; style-src \'unsafe-inline\'"><base target="_blank"><style>html,body{margin:0;padding:0;background:#fff}pre{margin:0;white-space:pre-wrap;word-break:break-word;font:14px/1.6 Arial,Helvetica,sans-serif;color:#111827}</style></head><body><pre>'
            .e($text).'</pre></body></html>';
    }
}
