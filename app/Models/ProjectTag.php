<?php

namespace App\Models;

use Whis\Database\Model;

class ProjectTag extends Model
{
    protected ?string $table = "project_tags";

    protected string $primaryKey = "id";

    protected array $fillable = [
        "project_id",
        "name",
        "slug",
        "type",
        "sort_order",
        "created_at",
        "updated_at",
    ];

    public const TYPE_TAG = "tag";
    public const TYPE_CATEGORY = "category";
    public const TYPE_SERVICE = "service";
    public const TYPE_SPECIALTY = "specialty";
    public const TYPE_MATERIAL = "material";

    public function project(): ?Project
    {
        return Project::find($this->project_id);
    }
}
