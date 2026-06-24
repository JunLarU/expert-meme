<?php

namespace App\Models;

use Whis\Database\Model;

class Message extends Model
{
    protected ?string $table = "messages";

    protected string $primaryKey = "id";

    protected array $fillable = [
        "name",
        "company",
        "email",
        "phone",
        "service",
        "subject",
        "project_location",
        "message",
        "source_page",
        "source_url",
        "referrer_url",
        "status",
        "priority",
        "assigned_to",
        "ip_address",
        "user_agent",
        "read_at",
        "answered_at",
        "archived_at",
        "created_at",
        "updated_at",
    ];

    public const STATUS_NEW = "new";
    public const STATUS_READ = "read";
    public const STATUS_IN_PROGRESS = "in_progress";
    public const STATUS_ANSWERED = "answered";
    public const STATUS_ARCHIVED = "archived";
    public const STATUS_SPAM = "spam";

    public const PRIORITY_LOW = "low";
    public const PRIORITY_NORMAL = "normal";
    public const PRIORITY_HIGH = "high";
    public const PRIORITY_URGENT = "urgent";

    public static function newest(): array
    {
        return static::all("created_at", true);
    }

    public function assignedUser(): ?User
    {
        if (empty($this->assigned_to)) {
            return null;
        }

        return User::find($this->assigned_to);
    }
}
