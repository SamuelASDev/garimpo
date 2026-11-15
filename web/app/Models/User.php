<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, OAuthenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /** LGPD: a IA grátis só recebe dados de quem autorizou. */
    public function hasAiConsent(): bool
    {
        return $this->ai_consent_at !== null;
    }

    /** LGPD: ao excluir a conta, nenhum acesso de IA conectada pode sobrar. */
    protected static function booted(): void
    {
        static::deleting(function (User $user): void {
            $tokenIds = $user->tokens()->pluck('id');
            DB::table('oauth_refresh_tokens')->whereIn('access_token_id', $tokenIds)->delete();
            $user->tokens()->delete();
            DB::table('oauth_auth_codes')->where('user_id', $user->id)->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
        });
    }

    public function resume(): HasOne
    {
        return $this->hasOne(Resume::class);
    }

    public function jobAlerts(): HasMany
    {
        return $this->hasMany(JobAlert::class);
    }

    public function jobApplications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'terms_accepted_at' => 'datetime',
            'ai_consent_at' => 'datetime',
            'is_admin' => 'boolean',
        ];
    }
}
