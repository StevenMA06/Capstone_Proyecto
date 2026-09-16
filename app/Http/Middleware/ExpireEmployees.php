<?php

namespace App\Http\Middleware;

use App\Services\EmployeeService;
use Closure;
use Illuminate\Http\Request;

class ExpireEmployees
{
    public function handle(Request $request, Closure $next)
    {
        app(EmployeeService::class)->expire();

        return $next($request);
    }
}
