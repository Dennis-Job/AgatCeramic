<?php

namespace App\Support;

use App\Enums\ContactRequestStatus;

class AuditEventPresenter
{
    /** @param array<string, mixed> $identity */
    public static function summary(string $action, array $identity, ?string $type, mixed $id): string
    {
        $label = AuditLabels::ACTIONS[$action] ?? $action;
        $object = isset($identity['name']) ? self::value($identity['name']) : ($type ? $type.' #'.self::value($id) : '');
        if (isset($identity['sku'])) {
            $object = 'SKU '.self::value($identity['sku']).($object ? ' · '.$object : '');
        }

        return $label.($object ? ': '.$object : '');
    }

    /**
     * @param  array<array-key, mixed>  $metadata
     * @return list<array{label: string, value: string}>
     */
    public static function details(array $metadata, string $action = ''): array
    {
        $details = [];
        foreach (['values', 'changes'] as $section) {
            $values = $metadata[$section] ?? [];
            if (! is_array($values)) {
                continue;
            }
            foreach ($values as $field => $value) {
                $label = AuditLabels::FIELDS[$field] ?? (string) $field;
                if ($section === 'changes' && is_array($value)) {
                    $formatted = self::value($value['before'] ?? null, (string) $field).' → '.self::value($value['after'] ?? null, (string) $field);
                } else {
                    $formatted = self::value($value, (string) $field);
                }
                if ($formatted !== '—') {
                    $details[] = ['label' => $label, 'value' => $formatted];
                }
            }
        }
        if (array_key_exists('from_status', $metadata) || array_key_exists('to_status', $metadata)) {
            $contact = $action === 'contact.status-changed';
            $details[] = ['label' => $contact ? 'Статус обращения' : 'Статус заказа', 'value' => self::value($metadata['from_status_label'] ?? $metadata['from_status'] ?? null, $contact ? 'contact_status' : 'status').' → '.self::value($metadata['to_status_label'] ?? $metadata['to_status'] ?? null, $contact ? 'contact_status' : 'status')];
        }
        if (array_key_exists('from_payment_status', $metadata) || array_key_exists('to_payment_status', $metadata)) {
            $details[] = ['label' => 'Статус оплаты', 'value' => self::value($metadata['from_payment_status'] ?? null, 'payment_status').' → '.self::value($metadata['to_payment_status'] ?? null, 'payment_status')];
        }
        if (array_key_exists('from_assignee_id', $metadata) || array_key_exists('to_assignee_id', $metadata)) {
            $details[] = ['label' => 'Ответственный (ID)', 'value' => self::value($metadata['from_assignee_id'] ?? null).' → '.self::value($metadata['to_assignee_id'] ?? null)];
        }
        foreach (['fields', 'import_id', 'operation', 'total', 'processed', 'created', 'updated', 'replaced', 'failed', 'automatic', 'trigger_image_id', 'trigger_product_id', 'source_product_id', 'synchronized', 'payment_amount'] as $field) {
            if (array_key_exists($field, $metadata) && $metadata[$field] !== []) {
                $details[] = ['label' => AuditLabels::FIELDS[$field], 'value' => self::value($metadata[$field], $field)];
            }
        }

        return $details;
    }

    private static function value(mixed $value, string $field = ''): string
    {
        if ($value === null || $value === '' || $value === []) {
            return '—';
        }
        if (is_bool($value)) {
            return $value ? 'Да' : 'Нет';
        }
        if (is_array($value)) {
            if (! array_is_list($value)) {
                $title = $value['title'] ?? $value['label'] ?? $value['sku'] ?? (isset($value['id']) ? '#'.self::value($value['id']) : null);
                if ($title !== null) {
                    return self::value($title).(array_key_exists('value', $value) ? ': '.self::value($value['value']) : '').self::properties($value);
                }

                return implode(', ', array_map(fn ($key): string => (AuditLabels::FIELDS[$key] ?? $key).': '.self::value($value[$key]), array_keys($value)));
            }

            return implode('; ', array_map(fn ($item): string => self::value($item, $field), $value));
        }
        $text = is_string($value) || is_numeric($value) ? (string) $value : '—';
        if ($field === 'fields') {
            return AuditLabels::FIELDS[$text] ?? $text;
        }
        if ($field === 'status') {
            return AuditLabels::STATUSES[$text] ?? $text;
        }
        if ($field === 'contact_status') {
            return ContactRequestStatus::tryFrom($text)?->label() ?? $text;
        }
        if ($field === 'payment_status') {
            return ['not_paid' => 'Не оплачен', 'pending' => 'Ожидается оплата', 'paid' => 'Оплачен', 'partially_paid' => 'Частично оплачен', 'refunded' => 'Возврат'][$text] ?? $text;
        }
        if ($field === 'operation') {
            return ['product' => 'Товары', 'image' => 'Фотографии', 'price_status' => 'Цены и статусы', 'group' => 'Объединения'][$text] ?? $text;
        }

        return $text;
    }

    /** @param array<array-key, mixed> $value */
    private static function properties(array $value): string
    {
        $parts = [];
        foreach (['type', 'sort_order', 'is_required'] as $field) {
            if (array_key_exists($field, $value)) {
                $parts[] = AuditLabels::FIELDS[$field].': '.self::value($value[$field]);
            }
        }

        return $parts === [] ? '' : ' ('.implode(', ', $parts).')';
    }
}
