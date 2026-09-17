<?php

use App\Http\Controllers\PlaceholderController;
use App\Support\Access\MenuCatalog;
use Illuminate\Support\Facades\Route;

foreach (MenuCatalog::placeholderRoutes() as $route) {
    $middleware = ['permission:'.$route['permission']];

    if ($route['scope'] === 'saas') {
        $middleware[] = 'permission:saas.access';
    }

    Route::get($route['uri'], PlaceholderController::class)
        ->middleware($middleware)
        ->name($route['name']);
}
