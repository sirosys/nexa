<?php

namespace App\Models;

use Database\Factories\CoverageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['code', 'site_id', 'name', 'description', 'created_by', 'updated_by'])]
class Coverage extends Model
{
    /** @use HasFactory<CoverageFactory> */
    use HasFactory;

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
