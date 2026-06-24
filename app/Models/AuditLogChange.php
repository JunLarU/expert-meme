<?php

namespace App\Models;

use Whis\Database\Model;

class AuditLogChange extends Model
{
    protected ?string $table = "audit_log_changes";

    protected string $primaryKey = "id";

    protected array $fillable = [
        "audit_log_id",
        "field_name",
        "old_value",
        "new_value",
        "created_at",
    ];

    public function auditLog(): ?AuditLog
    {
        return AuditLog::find($this->audit_log_id);
    }
}
