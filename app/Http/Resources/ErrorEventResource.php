<?php

namespace App\Http\Resources;

use App\Models\ErrorEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ErrorEvent */
class ErrorEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fingerprint' => $this->fingerprint,
            'exception_class' => $this->exception_class,
            'message' => $this->message,
            'file' => $this->file,
            'line' => $this->line,
            'count' => $this->count,
            'first_seen_at' => $this->first_seen_at->toIso8601String(),
            'last_seen_at' => $this->last_seen_at->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            $this->mergeWhen($request->routeIs('error-events.show', 'error-events.update'), fn () => [
                'method' => $this->last_method,
                'url' => $this->last_url, // request path only, never the query string
                'trace' => $this->trace ?? [],
            ]),
        ];
    }
}
