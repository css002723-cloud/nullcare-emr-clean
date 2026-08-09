<?php

namespace App\Providers;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    /**
     * The old automatic AuditLogObserver registration is gone — Phase 5b
     * switched to explicit AuditLogger::log(...) calls at each meaningful
     * controller action (matching the Flask reference's log_action()
     * style), so nothing needs to be registered here anymore.
     */
    public function boot(): void
    {
        // Laravel wraps bare Resource/ResourceCollection responses in a
        // {"data": ...} envelope by default. Most controllers in this app
        // return plain arrays (or call ->toArray() explicitly) and the
        // frontend expects raw arrays/objects everywhere — so disable the
        // envelope globally rather than special-casing every endpoint.
        JsonResource::withoutWrapping();
    }
}
