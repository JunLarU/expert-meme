<?php

namespace App\Models;

use Whis\Database\Model;

class ProjectFact extends Model
{
    protected ?string $table = "project_facts";

    protected string $primaryKey = "id";

    protected array $fillable = [
        "project_id",
        "label",
        "value",
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
