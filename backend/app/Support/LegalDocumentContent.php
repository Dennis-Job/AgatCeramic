<?php

namespace App\Support;

use InvalidArgumentException;
use JsonException;

final class LegalDocumentContent
{
    public function toPlainText(mixed $content): string
    {
        try {
            $serialized = json_encode($content, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES, 64);
        } catch (JsonException) {
            throw new InvalidArgumentException('Некорректная структура документа.');
        }
        if (strlen($serialized) > 200000) {
            throw new InvalidArgumentException('Документ превышает 200000 байт.');
        }
        $count = 0;
        $text = $this->node($content, ['doc'], 0, $count);
        if (preg_match('/[^\s\p{Z}\p{Cf}]/u', $text) !== 1) {
            throw new InvalidArgumentException('Введите текст документа.');
        }

        return $text;
    }

    /** @param list<string> $allowed */
    private function node(mixed $value, array $allowed, int $depth, int &$count): string
    {
        if ($depth > 16 || ++$count > 5000 || ! is_array($value) || ! is_string($value['type'] ?? null)
            || ! in_array($value['type'], $allowed, true)) {
            throw new InvalidArgumentException('Некорректная структура документа.');
        }
        $type = $value['type'];
        $this->keys($value, match ($type) {
            'text' => ['type', 'text', 'marks'],
            'hardBreak' => ['type'],
            'paragraph', 'heading', 'orderedList' => ['type', 'attrs', 'content'],
            default => ['type', 'content'],
        });
        if ($type === 'text') {
            if (! is_string($value['text'] ?? null) || $value['text'] === '') {
                throw new InvalidArgumentException('Некорректный текст документа.');
            }
            $this->marks(array_key_exists('marks', $value) ? $value['marks'] : []);

            return $value['text'];
        }
        if ($type === 'hardBreak') {
            return "\n";
        }
        $this->attributes($type, array_key_exists('attrs', $value) ? $value['attrs'] : []);
        $children = array_key_exists('content', $value) ? $value['content'] : [];
        if (! is_array($children) || ! array_is_list($children)
            || ($children === [] && ! in_array($type, ['paragraph', 'heading'], true))) {
            throw new InvalidArgumentException('Некорректные блоки документа.');
        }
        $allowedChildren = match ($type) {
            'doc' => ['paragraph', 'heading', 'bulletList', 'orderedList'],
            'paragraph', 'heading' => ['text', 'hardBreak'],
            'bulletList', 'orderedList' => ['listItem'],
            'listItem' => ['paragraph', 'bulletList', 'orderedList'],
            default => throw new InvalidArgumentException('Неподдерживаемый блок документа.'),
        };
        if ($type === 'listItem' && (! is_array($children[0] ?? null) || ($children[0]['type'] ?? null) !== 'paragraph')) {
            throw new InvalidArgumentException('Пункт списка должен начинаться с абзаца.');
        }
        $parts = [];
        foreach ($children as $child) {
            $parts[] = $this->node($child, $allowedChildren, $depth + 1, $count);
        }

        return implode(match ($type) {
            'doc' => "\n\n",
            'paragraph', 'heading' => '',
            default => "\n",
        }, $parts);
    }

    private function attributes(string $type, mixed $attrs): void
    {
        if (! is_array($attrs)) {
            throw new InvalidArgumentException('Некорректные атрибуты документа.');
        }
        $this->keys($attrs, match ($type) {
            'paragraph' => ['textAlign'],
            'heading' => ['level', 'textAlign'],
            'orderedList' => ['start'],
            default => [],
        });
        if ($type === 'heading' && ! in_array($attrs['level'] ?? null, [2, 3], true)) {
            throw new InvalidArgumentException('Допустимы заголовки второго и третьего уровня.');
        }
        if (array_key_exists('textAlign', $attrs) && ! in_array($attrs['textAlign'], ['left', 'right', 'justify'], true)) {
            throw new InvalidArgumentException('Некорректное выравнивание текста.');
        }
        if (array_key_exists('start', $attrs) && (! is_int($attrs['start']) || $attrs['start'] < 1)) {
            throw new InvalidArgumentException('Начальный номер списка должен быть положительным целым числом.');
        }
    }

    private function marks(mixed $marks): void
    {
        if (! is_array($marks) || ! array_is_list($marks) || count($marks) > 3) {
            throw new InvalidArgumentException('Некорректное форматирование текста.');
        }
        $seen = [];
        foreach ($marks as $mark) {
            if (! is_array($mark) || ! is_string($mark['type'] ?? null)
                || ! in_array($mark['type'], ['bold', 'italic', 'link'], true) || in_array($mark['type'], $seen, true)) {
                throw new InvalidArgumentException('Неподдерживаемое форматирование текста.');
            }
            $seen[] = $mark['type'];
            $this->keys($mark, $mark['type'] === 'link' ? ['type', 'attrs'] : ['type']);
            if ($mark['type'] === 'link') {
                $attrs = $mark['attrs'] ?? null;
                if (! is_array($attrs)) {
                    throw new InvalidArgumentException('Некорректная ссылка.');
                }
                $this->keys($attrs, ['href']);
                if (! $this->safeLink($attrs['href'] ?? null)) {
                    throw new InvalidArgumentException('Укажите безопасную ссылку HTTP, HTTPS, mailto или внутренний путь.');
                }
            }
        }
    }

    private function safeLink(mixed $href): bool
    {
        if (! is_string($href) || preg_match('/[\x00-\x20\x7f\\\\]/', $href) || preg_match('/%(?:0[0-9a-f]|1[0-9a-f]|7f)/i', $href)) {
            return false;
        }
        if (str_starts_with($href, '/') && ! str_starts_with($href, '//')) {
            return true;
        }
        if (str_starts_with(strtolower($href), 'mailto:')) {
            return filter_var(substr($href, 7), FILTER_VALIDATE_EMAIL) !== false;
        }
        $parts = parse_url($href);

        return filter_var($href, FILTER_VALIDATE_URL) !== false && is_array($parts)
            && in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            && ! array_key_exists('user', $parts) && ! array_key_exists('pass', $parts);
    }

    /** @param array<array-key, mixed> $value
     * @param  list<string>  $allowed
     */
    private function keys(array $value, array $allowed): void
    {
        if (array_diff(array_keys($value), $allowed) !== []) {
            throw new InvalidArgumentException('Неподдерживаемые поля документа.');
        }
    }
}
