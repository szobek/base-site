<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;

class HtmlSanitizer
{
    /** @var array<string, list<string>> */
    private const ALLOWED = [
        'p' => [],
        'br' => [],
        'strong' => [],
        'b' => [],
        'em' => [],
        'i' => [],
        'u' => [],
        'h2' => [],
        'h3' => [],
        'ul' => [],
        'ol' => [],
        'li' => [],
        'blockquote' => [],
        'a' => ['href'],
        'img' => ['src', 'alt', 'class'],
    ];

    /** @var list<string> */
    private const IMAGE_SIZES = ['size-25', 'size-50', 'size-75', 'size-100'];

    /** @var list<string> */
    private const IMAGE_ALIGNS = ['align-left', 'align-center', 'align-right'];

    /** @var list<string> */
    private const DROP = [
        'script',
        'style',
        'iframe',
        'object',
        'embed',
        'form',
        'textarea',
        'input',
        'button',
        'link',
        'meta',
        'svg',
    ];

    public function clean(string $html): string
    {
        $html = trim($html);

        if ($html === '') {
            return '';
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"><html><body>'.$html.'</body></html>',
            LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $body = $document->getElementsByTagName('body')->item(0);

        if (! $body instanceof DOMElement) {
            return '';
        }

        $this->sanitizeChildren($body);

        $clean = '';

        foreach ($body->childNodes as $child) {
            $clean .= $document->saveHTML($child);
        }

        return trim($clean);
    }

    private function sanitizeChildren(DOMNode $node): void
    {
        if (! $node->hasChildNodes()) {
            return;
        }

        for ($index = $node->childNodes->length - 1; $index >= 0; $index--) {
            $child = $node->childNodes->item($index);

            if (! $child instanceof DOMElement) {
                if (! $child instanceof \DOMText) {
                    $node->removeChild($child);
                }

                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::DROP, true)) {
                $node->removeChild($child);

                continue;
            }

            if ($tag === 'div') {
                $child = $this->rename($child, 'p');
                $tag = 'p';
            }

            if (! array_key_exists($tag, self::ALLOWED)) {
                $this->sanitizeChildren($child);

                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }

                $node->removeChild($child);

                continue;
            }

            $this->sanitizeChildren($child);
            $this->filterAttributes($child, self::ALLOWED[$tag]);
        }
    }

    /**
     * @param  list<string>  $allowed
     */
    private function filterAttributes(DOMElement $element, array $allowed): void
    {
        $remove = [];

        foreach ($element->attributes ?? [] as $attribute) {
            $name = strtolower($attribute->name);

            if (! in_array($name, $allowed, true)) {
                $remove[] = $attribute->name;
            }
        }

        foreach ($remove as $name) {
            $element->removeAttribute($name);
        }

        $tag = strtolower($element->tagName);

        if ($tag === 'a') {
            $href = trim($element->getAttribute('href'));

            if (! $this->isSafeLink($href)) {
                $element->removeAttribute('href');
            } else {
                $element->setAttribute('rel', 'noopener noreferrer');
            }
        }

        if ($tag === 'img') {
            if (! $this->isSafeImage($element->getAttribute('src'))) {
                $element->parentNode?->removeChild($element);

                return;
            }

            $this->filterImageClass($element);
        }
    }

    private function filterImageClass(DOMElement $element): void
    {
        $size = null;
        $align = null;

        foreach (preg_split('/\s+/', trim($element->getAttribute('class'))) ?: [] as $token) {
            if (in_array($token, self::IMAGE_SIZES, true)) {
                $size = $token;
            }

            if (in_array($token, self::IMAGE_ALIGNS, true)) {
                $align = $token;
            }
        }

        $classes = array_values(array_filter([$size, $align]));

        if ($classes === []) {
            $element->removeAttribute('class');

            return;
        }

        $element->setAttribute('class', implode(' ', $classes));
    }

    private function isSafeLink(string $href): bool
    {
        if ($href === '' || str_starts_with($href, '//')) {
            return false;
        }

        $scheme = strtolower((string) parse_url($href, PHP_URL_SCHEME));

        if ($scheme === '') {
            return str_starts_with($href, '/') && ! str_starts_with($href, '//');
        }

        return in_array($scheme, ['http', 'https', 'mailto'], true);
    }

    private function isSafeImage(string $src): bool
    {
        $src = trim(html_entity_decode($src));

        if ($src === '' || preg_match('/^\s*(javascript|data):/i', $src) === 1 || str_starts_with($src, '//')) {
            return false;
        }

        $path = (string) (parse_url($src, PHP_URL_PATH) ?? $src);

        if (str_starts_with($path, '/storage/')) {
            $host = parse_url($src, PHP_URL_HOST);

            if ($host === null || $host === false || $host === '') {
                return true;
            }

            $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

            return $host === $appHost;
        }

        return false;
    }

    private function rename(DOMElement $element, string $tag): DOMElement
    {
        $replacement = $element->ownerDocument->createElement($tag);

        while ($element->firstChild) {
            $replacement->appendChild($element->firstChild);
        }

        $element->parentNode?->replaceChild($replacement, $element);

        return $replacement;
    }
}
