<?php

namespace App\Services;

final class SensitiveDataRedactor
{
    public function redact(array $value): array
    {
        return $this->redactValue($value) ?? [];
    }

    private function redactValue(mixed $value, int $depth = 0): mixed
    {
        if ($depth > 8) return '[REDACTED]';
        if (! is_array($value)) return $value;
        $result = [];
        foreach ($value as $key => $item) {
            $normalized = strtolower(str_replace(['-', ' '], '_', (string) $key));
            $result[$key] = preg_match('/(?:password|token|secret|recovery|private_key|session|authorization|cookie|credential|api_key|access_key|refresh)/', $normalized)
                ? '[REDACTED]'
                : $this->redactValue($item, $depth + 1);
        }
        return $result;
    }
}
