<?php

namespace App\Support;

// Parser deliberadamente simples: o formato de front matter usado na
// importação de conhecimento é só pares "chave: valor" de uma linha, sem
// listas/aninhamento/multilinha — não precisa de um parser YAML de verdade
// (symfony/yaml não está instalado no projeto).
class FrontMatterParser
{
    /** @return array{meta: array<string, string>, body: string} */
    public static function parse(string $raw): array
    {
        $normalized = str_replace("\xEF\xBB\xBF", '', $raw);
        $normalized = str_replace("\r\n", "\n", $normalized);
        $lines = explode("\n", $normalized);

        if (! isset($lines[0]) || trim($lines[0]) !== '---') {
            return ['meta' => [], 'body' => trim($normalized)];
        }

        $meta = [];
        $i = 1;

        for (; $i < count($lines); $i++) {
            $line = $lines[$i];

            if (trim($line) === '---') {
                break;
            }

            if (trim($line) === '' || str_starts_with(trim($line), '#')) {
                continue;
            }

            if (! str_contains($line, ':')) {
                continue;
            }

            [$key, $value] = explode(':', $line, 2);
            $key = mb_strtolower(trim($key));
            $value = trim($value);
            $value = self::stripSurroundingQuotes($value);

            if ($key !== '') {
                $meta[$key] = $value;
            }
        }

        $body = implode("\n", array_slice($lines, $i + 1));

        return ['meta' => $meta, 'body' => trim($body)];
    }

    private static function stripSurroundingQuotes(string $value): string
    {
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];

            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                return substr($value, 1, -1);
            }
        }

        return $value;
    }
}
