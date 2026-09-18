<?php

namespace App\Http\Middleware;

use App\Services\ContractService;
use Closure;
use Illuminate\Http\Request;

class FinalizeContracts
{
    public function handle(Request $request, Closure $next)
    {
        app(ContractService::class)->finalizeExpired();

        return $next($request);
    }
}
