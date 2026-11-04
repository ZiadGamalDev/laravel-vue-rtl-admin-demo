<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Applies the shared list parameters (search, sort, direction, per_page)
 * to an Eloquent query. Sort columns are whitelisted per resource.
 */
class ListQuery
{
    /**
     * @param  array<string, string|\Closure>  $sortable  public sort key => column (or closure receiving builder + direction)
     * @param  \Closure(Builder, string): void|null  $search
     */
    public static function apply(
        Request $request,
        Builder $query,
        array $sortable,
        string $defaultSort,
        string $defaultDirection = 'desc',
        ?\Closure $search = null,
    ): LengthAwarePaginator {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', 'string', 'in:'.implode(',', array_keys($sortable))],
            'direction' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $term = trim((string) $request->input('search'));
        if ($term !== '' && $search) {
            $search($query, $term);
        }

        $sort = $request->input('sort', $defaultSort);
        $direction = $request->input('direction', $defaultDirection);
        $column = $sortable[$sort];

        if ($column instanceof \Closure) {
            $column($query, $direction);
        } else {
            $query->orderBy($column, $direction);
        }
        // Stable order for rows with equal sort values.
        $query->orderBy($query->getModel()->getQualifiedKeyName(), $direction);

        return $query->paginate((int) $request->input('per_page', 10))->withQueryString();
    }

    /** Escape LIKE wildcards in user input. */
    public static function like(string $term): string
    {
        return '%'.addcslashes($term, '%_\\').'%';
    }

    /**
     * OR-match the term as a literal substring in any of the columns.
     * The explicit ESCAPE clause matters: SQLite ignores backslash escapes
     * without it, so "50%" or "a_b" would otherwise act as wildcards.
     *
     * @template TBuilder of Builder
     *
     * @param  TBuilder  $query
     * @param  list<string>  $columns
     * @return TBuilder
     */
    public static function whereContainsAny(Builder $query, array $columns, string $term): Builder
    {
        $grammar = $query->getQuery()->getGrammar();

        // Own group, so the ORs never escape into constraints already on the query
        // (e.g. the relation condition inside whereHas).
        return $query->where(function (Builder $group) use ($grammar, $columns, $term) {
            foreach ($columns as $column) {
                $group->orWhereRaw($grammar->wrap($column).' like ? escape ?', [self::like($term), '\\']);
            }
        });
    }
}
