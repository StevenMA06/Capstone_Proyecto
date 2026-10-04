<?php

namespace App\Http\Middleware;

use App\Support\AccessCatalog;
use Closure;
use Illuminate\Http\Request;

class RequireModulePermission
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()) {
            $name = $request->route()?->getName() ?? '';
            $permission = AccessCatalog::forRoute($name, $request->method());
            if ($permission) abort_unless($request->user()->hasPermission($permission), 403, 'No tienes permiso para realizar esta acción.');
            elseif (in_array('auth', $request->route()?->gatherMiddleware() ?? [], true)) {
                abort_unless(in_array($name, ['logout', 'session.status', 'dashboard']), 403, 'Esta funcionalidad no tiene un permiso configurado.');
            }
        }
        return $next($request);
    }
}
