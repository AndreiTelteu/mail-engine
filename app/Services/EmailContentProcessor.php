<?php

namespace App\Services;

use App\DataTransferObjects\EmailData;
use App\DataTransferObjects\ProcessedEmailContent;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Str;

class EmailContentProcessor
{
    public const PARSER_VERSION = 1;

    public function process(EmailData $email): ProcessedEmailContent
    {
        $text = $this->normalize($email->bodyText ?: $this->textFromHtml($email->bodyHtml));
        [$bodyCurrent, $bodyQuoted] = $this->separateQuotedContent($text);
        $previewSource = $bodyCurrent !== '' ? $bodyCurrent : $bodyQuoted;

        return new ProcessedEmailContent(
            bodyCurrent: $bodyCurrent,
            bodyQuoted: $bodyQuoted,
            preview: Str::limit($previewSource, 280, ''),
            contentHash: hash('sha256', "{$bodyCurrent}\n{$bodyQuoted}"),
            parserVersion: self::PARSER_VERSION,
        );
    }

    private function textFromHtml(?string $html): string
    {
        if (blank($html)) {
            return '';
        }

        $document = new DOMDocument;
        $previousInternalErrors = libxml_use_internal_errors(true);

        try {
            $loaded = $document->loadHTML(
                '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>'.$html.'</body></html>',
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET,
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousInternalErrors);
        }

        if (! $loaded) {
            return strip_tags($html);
        }

        $xpath = new DOMXPath($document);

        foreach ($xpath->query('//script|//style|//noscript|//template|//head|//*[@hidden]|//*[contains(translate(@style, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "display:none")]') ?: [] as $node) {
            $node->parentNode?->removeChild($node);
        }

        return $document->textContent ?: '';
    }

    /**
     * @return array{string, string}
     */
    private function separateQuotedContent(string $text): array
    {
        $withoutSignature = preg_split('/(?:^|\R)--\s*\R/u', $text, 2)[0] ?? $text;
        $quotedAt = preg_match('/^On .+wrote:\s*$/miu', $withoutSignature, $match, PREG_OFFSET_CAPTURE);

        if ($quotedAt === 1) {
            $offset = $match[0][1];

            return [
                $this->normalize(mb_substr($withoutSignature, 0, $offset)),
                $this->normalize(mb_substr($withoutSignature, $offset)),
            ];
        }

        $lines = preg_split('/\R/u', $withoutSignature) ?: [];
        $current = [];
        $quoted = [];

        foreach ($lines as $line) {
            if (str_starts_with(ltrim($line), '>')) {
                $quoted[] = ltrim(ltrim($line), '>');
            } else {
                $current[] = $line;
            }
        }

        return [$this->normalize(implode("\n", $current)), $this->normalize(implode("\n", $quoted))];
    }

    private function normalize(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\R+/u', "\n", $text) ?? $text;
        $text = preg_replace('/[^\S\n]+/u', ' ', $text) ?? $text;

        if (class_exists('Normalizer')) {
            $text = \Normalizer::normalize($text, \Normalizer::FORM_C) ?: $text;
        }

        return trim($text);
    }
}
