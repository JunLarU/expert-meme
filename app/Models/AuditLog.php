<?php

namespace App\Models;

use Whis\Database\Model;

class AuditLog extends Model
{
    protected ?string $table = "audit_logs";

    protected string $primaryKey = "id";

    protected array $fillable = [
        "table_name",
        "record_id",
        "action",
        "actor_user_id",
        "actor_name",
        "actor_email",
        "record_label",
        "old_values",
        "new_values",
        "ip_address",
        "user_agent",
        "request_method",
        "request_url",
        "created_at",
    ];

    public const ACTION_CREATED = "created";
    public const ACTION_UPDATED = "updated";
    public const ACTION_DELETED = "deleted";
    public const ACTION_RESTORED = "restored";
    public const ACTION_STATUS_CHANGED = "status_changed";

    public function changes(): array
    {
        return AuditLogChange::where("audit_log_id", $this->id, "created_at") ?? [];
    }

    public function actor(): ?User
    {
        if (empty($this->actor_user_id)) {
            return null;
        }

        return User::find($this->actor_user_id);
    }
}
