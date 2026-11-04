<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ErrorEventResource;
use App\Models\ErrorEvent;
use App\Support\ListQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use RuntimeException;

class ErrorEventController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ErrorEvent::class);

        $request->validate(['status' => ['nullable', 'in:unresolved,resolved']]);

        $query = ErrorEvent::query()
            ->select(['id', 'fingerprint', 'exception_class', 'message', 'file', 'line', 'count', 'first_seen_at', 'last_seen_at', 'resolved_at'])
            ->when($request->input('status') === 'unresolved', fn (Builder $q) => $q->whereNull('resolved_at'))
            ->when($request->input('status') === 'resolved', fn (Builder $q) => $q->whereNotNull('resolved_at'));

        return ErrorEventResource::collection(ListQuery::apply(
            $request,
            $query,
            sortable: ['last_seen_at' => 'last_seen_at', 'first_seen_at' => 'first_seen_at', 'count' => 'count'],
            defaultSort: 'last_seen_at',
            search: function (Builder $query, string $term) {
                $query->where(fn (Builder $q) => ListQuery::whereContainsAny($q, ['message', 'exception_class', 'file'], $term));
            },
        ));
    }

    public function show(ErrorEvent $errorEvent): ErrorEventResource
    {
        $this->authorize('view', $errorEvent);

        return new ErrorEventResource($errorEvent);
    }

    /** Mark an issue resolved or reopen it. */
    public function update(Request $request, ErrorEvent $errorEvent): ErrorEventResource
    {
        $this->authorize('update', $errorEvent);

        $request->validate(['resolved' => ['required', 'boolean']]);

        $errorEvent->update(['resolved_at' => $request->boolean('resolved') ? now() : null]);

        return new ErrorEventResource($errorEvent);
    }

    /**
     * Throws on purpose so the real exception handler records it.
     * Only reachable when APP_ENV=local (see the local.only middleware).
     */
    public function trigger(): never
    {
        $this->authorize('trigger', ErrorEvent::class);

        throw new RuntimeException(__('messages.test_error'));
    }
}
