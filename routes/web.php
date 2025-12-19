<?php

use Illuminate\Support\Facades\Route;

// web.php
Route::get('/', function () {
    $frontendUrl = env('WEB_APP_URL');

    if ($frontendUrl) {
        return redirect($frontendUrl);
    }

    // Fallback si no está definida la variable
    return response()->json(['message' => 'Frontend URL not configured']);
});
