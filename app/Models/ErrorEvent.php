<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

class ErrorEvent extends Model
{
    use Prunable;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'trace' => 'array',
            'count' => 'integer',
            'line' => 'integer',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /** Resolved issues that have been quiet for 30 days are removed by model:prune. */
    public function prunable(): Builder
    {
        return static::query()
            ->whereNotNull('resolved_at')
            ->where('last_seen_at', '<', now()->subDays(30));
    }
}
