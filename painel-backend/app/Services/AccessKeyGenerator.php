<?php

namespace App\Services;

use App\Models\ChatAccessKey;
use App\Models\ErpExecutionKey;
use Illuminate\Support\Str;

class AccessKeyGenerator
{
    // Str::random usa random_bytes (CSPRNG do PHP), não mt_rand. 64 caracteres
    // em base62 dão ~381 bits de entropia — fora de alcance de força bruta.
    public function generate(): string
    {
        do {
            $key = Str::random(64);
        } while (
            ChatAccessKey::where('key_hash', $this->hash($key))->exists()
            || ErpExecutionKey::where('access_key', $key)->exists()
        );

        return $key;
    }

    // SHA-256 puro basta e é proposital: a chave é aleatória de alta entropia,
    // então não existe ataque de dicionário pra bcrypt/Argon defender — e a
    // verificação precisa ser um lookup determinístico por índice.
    public function hash(string $key): string
    {
        return hash('sha256', $key);
    }
}
