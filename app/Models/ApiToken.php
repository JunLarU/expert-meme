<?php

namespace App\Models;

use Whis\Database\Model;

class ApiToken extends Model
{
    protected ?string $table = 'api_tokens';

    protected string $primaryKey = 'id';

    protected array $hidden = [
        'token_hash',
    ];

    protected array $fillable = [
        'tokenable_type',
        'tokenable_id',
        'name',
        'token_prefix',
        'token_hash',
        'abilities',
        'expires_at',
        'last_used_at',
        'last_used_ip',
        'last_used_user_agent',
        'revoked_at',
        'created_at',
        'updated_at',
        'system',
    ];

    /**
     * Obtiene el usuario propietario del token.
     */
    public function tokenable()
    {
        if (!$this->tokenable_type || !$this->tokenable_id) {
            return null;
        }
        if (!class_exists($this->tokenable_type)) {
            return null;
        }
        return $this->tokenable_type::find($this->tokenable_id);
    }

    /**
     * Verifica si el token está activo.
     */
    public function isActive(): bool
    {
        return $this->revoked_at === null
            && ($this->expires_at === null || strtotime($this->expires_at) >= time());
    }

    /**
     * Verifica si es un token de sistema.
     */
    public function isSystem(): bool
    {
        return (bool) $this->system;
    }
}