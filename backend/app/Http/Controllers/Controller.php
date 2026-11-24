<?php

namespace App\Http\Controllers;

use App\Support\ApiResponse;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class Controller
{
    use AuthorizesRequests;

    /** Paginates and wraps a query with the given resource class in the standard envelope. */
    protected function paginated(Builder $query, string $resource, Request $request, int $default = 20): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->integer('per_page', $default)));

        return ApiResponse::ok($resource::collection($query->paginate($perPage)->withQueryString()));
    }

    /** Whitelisted sorting: ?sort=-created_at */
    protected function sort(Builder $query, Request $request, array $allowed, string $default = '-created_at'): Builder
    {
        $sort = (string) $request->query('sort', $default);
        $dir = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $col = ltrim($sort, '-');
        if (! in_array($col, $allowed, true)) {
            $col = ltrim($default, '-');
            $dir = str_starts_with($default, '-') ? 'desc' : 'asc';
        }

        return $query->orderBy($col, $dir)->orderBy('id', $dir);
    }

    protected function like(string $term): string
    {
        return '%'.addcslashes($term, '%_\\').'%';
    }
}
