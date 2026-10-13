<?php

namespace App\Models;

use App\Enums\ImportBatchStatus;
use Database\Factories\ImportBatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'source_type', 'default_genre', 'year', 'month', 'status', 'raw_input'])]
class ImportBatch extends Model
{
    /** @use HasFactory<ImportBatchFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'source_type' => 'manual',
        'status' => 'draft',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ImportBatchStatus::class,
            'year' => 'integer',
            'month' => 'integer',
        ];
    }

    /**
     * @return HasMany<ImportItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ImportItem::class);
    }

    /**
     * @return HasMany<ExportFile, $this>
     */
    public function exportFiles(): HasMany
    {
        return $this->hasMany(ExportFile::class);
    }
}
