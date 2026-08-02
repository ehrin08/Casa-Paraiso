<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

trait HandlesIndexSorting
{
    /**
     * @param  array<string, string>  $allowedSorts
     */
    protected function indexSort(Request $request, array $allowedSorts, string $default): string
    {
        $sort = (string) $request->query('sort', $default);

        return array_key_exists($sort, $allowedSorts) ? $sort : $default;
    }

    protected function indexDirection(Request $request, string $default = 'asc'): string
    {
        $direction = strtolower((string) $request->query('direction', $default));

        return $direction === 'desc' ? 'desc' : 'asc';
    }
}
