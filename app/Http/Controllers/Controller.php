<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

abstract class Controller
{
    /**
     * Standard JSON response for API endpoints (body is the payload as-is, no wrapper key).
     *
     * @param  array<string, mixed>  $payload
     */
    protected function jsonResponse(array $payload, int $status = 200): JsonResponse
    {
        return response()->json($payload, $status);
    }
}
