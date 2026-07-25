<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            if ($e instanceof \Illuminate\Validation\ValidationException) {
                return null; // Laravel ya maneja este caso correctamente
            }

            // AuthenticationException no implementa HttpExceptionInterface, asi
            // que sin este caso caia en el 500 generico: un token expirado o
            // revocado respondia "Error interno del servidor" en vez de 401, y
            // ningun cliente podia distinguir "reautenticate" de "esta caido".
            if ($e instanceof \Illuminate\Auth\AuthenticationException) {
                return response()->json(['message' => 'No autorizado.'], 401);
            }

            $status = $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
                ? $e->getStatusCode()
                : 500;

            $message = match (true) {
                $status >= 500 => 'Error interno del servidor.',
                $status === 422 => 'Los datos enviados no son válidos.',
                $status === 403 => 'No tienes permisos para realizar esta acción.',
                $status === 401 => 'No autorizado.',
                $status === 404 => 'Recurso no encontrado.',
                default        => 'Ocurrió un error. Intenta nuevamente.',
            };

            return response()->json(['message' => $message], $status);
        });
    })->create();
