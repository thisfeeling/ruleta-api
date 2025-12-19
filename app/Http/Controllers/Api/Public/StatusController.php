<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class StatusController extends Controller
{
    /**
     * Health-check endpoint used by load balancers, monitoring and CI.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        // Prefer the config value (this is cache-friendly in production); if it's
        // not present (or null), fallback to the environment variable. This keeps
        // behavior predictable across config:cache situations.
        $appVersionFromEnv = env('APP_VERSION', null);
        $appVersion = $appVersionFromEnv;

        // Simple database connectivity check
        try {
            DB::connection()->getPdo();
            $dbStatus = true;
        } catch (\Exception $e) {
            $dbStatus = false;
        }

        // Return a JSON response with status information
        return response()->json([
            'status' => 'success',
            'message' => 'API Working',
            'database' => $dbStatus,
            'timestamp' => now(),
            'version' => $appVersion,
        ]);
    }
}
