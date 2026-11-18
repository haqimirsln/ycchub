<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Support\Str;

/**
 * Cleans what the MT writes in the email editor so only simple, safe
 * formatting reaches members' inboxes: text styles, headings, lists, links,
 * colours, alignment and pictures. Scripts, forms and anything else are dropped.
 */
class EmailHtml
{
    /** tag => attributes kept on it */
    private const ALLOWED = [
        'p' => [], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [],
        'h2' => [], 'h3' => [], 'ul' => [], 'ol' => [], 'li' => [], 'blockquote' => [], 'hr' => [],
        'span' => [], 'a' => ['href'], 'img' => ['src', 'alt'],
    ];

    /** Removed together with everything inside them. */
    private const DROP = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'select', 'textarea', 'svg', 'math', 'video', 'audio', 'head', 'title', 'meta', 'link'];

    private const STYLES = [
        'blockquote' => 'border-left:4px solid #d9aa2e;margin:0 0 16px;padding:4px 0 4px 14px;color:#4a5b57;',
        'img' => 'display:block;max-width:100%;height:auto;border-radius:8px;margin:8px 0 16px;',
        'hr' => 'border:0;border-top:1px solid #d6e3de;margin:20px 0;',
        'h2' => 'margin:0 0 12px;',
        'h3' => 'margin:0 0 10px;',
    ];

    public static function clean(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }
        // Emails written before the editor existed were plain text with Markdown.
        if (! preg_match('/<[a-z][\s\S]*>/i', $html)) {
            $html = Str::markdown($html, ['html_input' => 'escape', 'allow_unsafe_links' => false]);
        }

        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?><div id="ycc-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementById('ycc-root');
        if (! $root) {
            return '';
        }
        self::walk($root);
        self::tidy($doc, $root);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $doc->saveHTML($child);
        }

        // One line: the mail template runs Markdown, which would turn indented lines into code blocks.
        return trim(preg_replace('/\s*\n\s*/', ' ', $out));
    }

    /** True when there's nothing to read or see. */
    public static function isEmpty(string $clean): bool
    {
        return trim(html_entity_decode(strip_tags($clean))) === '' && ! str_contains($clean, '<img');
    }

    /**
     * Email clients and the mail theme give bold text and links their own colour,
     * so a colour set around them is copied onto them. List items lose the extra
     * paragraph the editor wraps them in, so lists aren't double spaced.
     */
    private static function tidy(DOMDocument $doc, DOMElement $root): void
    {
        $xpath = new \DOMXPath($doc);
        foreach ($xpath->query('.//*[contains(@style, "color:")]', $root) as $coloured) {
            if (! preg_match('/(?<![-\w])color:([^;]+);/', $coloured->getAttribute('style'), $m)) {
                continue;
            }
            foreach ($xpath->query('.//strong|.//b|.//em|.//i|.//u|.//s|.//a', $coloured) as $inner) {
                if (! preg_match('/(?<![-\w])color:/', $inner->getAttribute('style'))) {
                    $inner->setAttribute('style', $inner->getAttribute('style').'color:'.$m[1].';');
                }
            }
        }

        foreach (iterator_to_array($xpath->query('.//li', $root)) as $li) {
            $paragraphs = [];
            foreach ($li->childNodes as $child) {
                if ($child instanceof DOMElement && strtolower($child->tagName) === 'p') {
                    $paragraphs[] = $child;
                }
            }
            foreach ($paragraphs as $i => $p) {
                if ($i > 0) {
                    $li->insertBefore($doc->createElement('br'), $p);
                }
                if ($p->hasAttribute('style') && ! $li->hasAttribute('style')) {
                    $li->setAttribute('style', $p->getAttribute('style'));
                }
                while ($p->firstChild) {
                    $li->insertBefore($p->firstChild, $p);
                }
                $li->removeChild($p);
            }
        }
    }

    private static function walk(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if (! $child instanceof DOMElement) {
                if ($child->nodeType === XML_COMMENT_NODE) {
                    $node->removeChild($child);
                }

                continue;
            }
            $tag = strtolower($child->tagName);

            if (in_array($tag, self::DROP, true)) {
                $node->removeChild($child);

                continue;
            }
            self::walk($child);

            if (! array_key_exists($tag, self::ALLOWED)) {
                // Unknown wrapper (div, font, table...): keep its contents, lose the tag.
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);

                continue;
            }
            self::cleanAttributes($child, $tag);
        }
    }

    private static function cleanAttributes(DOMElement $el, string $tag): void
    {
        $style = (string) $el->getAttribute('style');
        foreach (iterator_to_array($el->attributes) as $attr) {
            if (! in_array($attr->name, self::ALLOWED[$tag], true)) {
                $el->removeAttribute($attr->name);
            }
        }

        if ($tag === 'a') {
            $href = trim($el->getAttribute('href'));
            if (! preg_match('#^(https?://|mailto:)#i', $href)) {
                $el->removeAttribute('href');
            } else {
                $el->setAttribute('target', '_blank');
                $el->setAttribute('rel', 'noopener');
            }
        }
        if ($tag === 'img') {
            if (! preg_match('#^https?://#i', trim($el->getAttribute('src')))) {
                $el->parentNode?->removeChild($el);

                return;
            }
            $el->setAttribute('alt', mb_substr($el->getAttribute('alt'), 0, 150));
        }

        $keep = self::STYLES[$tag] ?? '';
        if (in_array($tag, ['p', 'h2', 'h3', 'li'], true) && preg_match('/text-align\s*:\s*(left|center|right|justify)/i', $style, $m)) {
            $keep .= 'text-align:'.strtolower($m[1]).';';
        }
        if (in_array($tag, ['span', 'strong', 'em', 'u', 's', 'a'], true) && preg_match('/(?<![-\w])color\s*:\s*(#[0-9a-f]{3,8}|rgba?\([\d\s.,%]+\))/i', $style, $m)) {
            $keep .= 'color:'.$m[1].';';
        }
        if ($keep !== '') {
            $el->setAttribute('style', $keep);
        }
        if ($tag === 'span' && ! $el->hasAttribute('style')) {
            // A plain span does nothing; unwrap it.
            $parent = $el->parentNode;
            while ($el->firstChild) {
                $parent->insertBefore($el->firstChild, $el);
            }
            $parent->removeChild($el);
        }
    }
}
