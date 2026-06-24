<?php

namespace App\Models;

use Whis\Database\Model;

class ProjectResultStat extends Model
{
    protected ?string $table = "project_result_stats";

    protected string $primaryKey = "id";

    protected array $fillable = [
        "project_id",
        "value",
        "label",
        "description",
        "sort_order",
        "created_at",
        "updated_at",
    ];

    public function project(): ?Project
    {
        return Project::find($this->project_id);
    }
}
