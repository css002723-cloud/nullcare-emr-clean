<?php

use Illuminate\Support\Facades\Route;


Route::get('/{any?}', function () {
    $indexPath = public_path('app/index.html');

    if (! file_exists($indexPath)) {
        abort(404, 'Frontend build not found. Run `npm run build` in the frontend/ folder first.');
    }

    return response(file_get_contents($indexPath), 200)->header('Content-Type', 'text/html');
})->where('any', '^(?!api).*$');