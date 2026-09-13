<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/**
 * Serve public disk files without requiring `php artisan storage:link`.
 * Matches API image paths: storage/app/public/{relative_path}
 */
Route::get('storage/app/public/{path}', function (string $path) {
    $path = str_replace(['..', '\\'], ['', '/'], $path);
    $fullPath = storage_path('app/public/'.$path);

    abort_unless(is_file($fullPath), 404);

    return response()->file($fullPath);
})->where('path', '.*');
