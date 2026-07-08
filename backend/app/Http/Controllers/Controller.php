<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    use AuthorizesRequests;

    /**
     * Resolve a safe per-page size for paginated endpoints.
     *
     * Clamps the client-supplied value so a request like `?per_page=100000`
     * cannot exhaust memory or hammer the database.
     */
    protected function perPage(int $default = 15, int $max = 100): int
    {
        $perPage = (int) request('per_page', $default);

        if ($perPage < 1) {
            return $default;
        }

        return min($perPage, $max);
    }
}
