<?php

namespace App\Models;

use Whis\Database\Model;

class ProjectScopeItem extends Model
{
    protected ?string $table = "project_scope_items";

    protected string $primaryKey = "id";

    protected array $fillable = [
        "project_id",
        "number_label",
        "title",
        "description",
        "icon",
        "sort_order",
        "created_at",
        "updated_at",
    ];

    public function project(): ?Project
    {
        return Project::find($this->project_id);
    }
}
