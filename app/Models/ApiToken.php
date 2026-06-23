<?php

namespace App\Models;

use Whis\Database\Model;

class ApiToken extends Model
{
    protected ?string $table = 'api_tokens';

    protected string $primaryKey = 'id';

    protected array $fillable = [
        'tokenable_type',
        'tokenable_id',
        'name',
        'token_prefix',
        'token_hash',
        'abilities',
        'expires_at',
        'last_used_at',
        'revoked_at',
        'created_at',
        'updated_at',
    ];

    protected array $hidden = [
        'token_hash',
    ];

    protected bool $insertTimestamps = false;
}
