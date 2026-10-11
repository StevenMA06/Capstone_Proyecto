<?php

use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\ExpireEmployees;
use App\Http\Middleware\FinalizeContracts;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // VS Code Dev Tunnels forwards requests through the local loopback proxy.
        // Honor its public host and HTTPS headers so login and asset URLs stay on the tunnel.
        $middleware->trustProxies(at: ['127.0.0.1', '::1']);
        $middleware->trimStrings(except: ['contrasena_router', 'contrasena_wifi']);
        $middleware->web(append: [ExpireEmployees::class, FinalizeContracts::class, EnsureAccountActive::class, \App\Http\Middleware\RequireModulePermission::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['code', 'contrasena_router', 'contrasena_wifi']);
        $exceptions->respond(function ($response) {
            if ($response instanceof \Illuminate\Http\JsonResponse) {
                $messages = [
                    401 => 'Debes iniciar sesión.',
                    403 => 'No tienes permiso para realizar esta acción.',
                    404 => 'No se encontró el recurso solicitado.',
                    405 => 'Esta acción no está permitida.',
                    419 => 'La sesión ha vencido. Recarga la página e inicia sesión nuevamente.',
                    429 => 'Demasiadas solicitudes. Espera un momento e inténtalo nuevamente.',
                    500 => 'Ocurrió un error en el servidor. Inténtalo nuevamente más tarde.',
                    503 => 'El servicio no está disponible temporalmente.',
                ];
                if (isset($messages[$response->getStatusCode()]) && ! config('app.debug')) {
                    $data = $response->getData(true);
                    $data['message'] = $messages[$response->getStatusCode()];
                    $response->setData($data);
                }
            }
            return $response;
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
