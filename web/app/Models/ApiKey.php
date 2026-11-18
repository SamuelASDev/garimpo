<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Chave da API pública. Guardamos só o hash: se o banco vazar, as chaves não servem para nada.
 */
class ApiKey extends Model
{
    /** Uma chave por conta: gerar outra substitui a atual. */
    public const MAX_PER_USER = 1;

    protected $fillable = ['name', 'key_hash', 'prefix', 'last_used_at'];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Cria uma chave nova e devolve o texto dela (mostrado uma vez só).
     *
     * @return array{0: self, 1: string}
     */
    public static function generateFor(User $user, string $name): array
    {
        $plain = 'gp_'.Str::random(40);
        $key = $user->apiKeys()->create(['name' => $name, 'key_hash' => self::hash($plain), 'prefix' => substr($plain, 0, 10)]);

        return [$key, $plain];
    }

    public static function findByPlain(string $plain): ?self
    {
        return str_starts_with($plain, 'gp_') ? self::where('key_hash', self::hash($plain))->first() : null;
    }

    private static function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }
}
