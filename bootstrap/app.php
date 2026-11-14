<?php

use App\Exceptions\MobileApiException;
use App\Http\Middleware\EnforceRoutePermission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use App\Http\Middleware\EnsureOwnerOnboarding;
use App\Http\Middleware\EnsureTenantScope;
use App\Http\Middleware\EnsureTenantSubscriptionActive;
use App\Http\Middleware\ForceHttps;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
        $middleware->appendToGroup('web', ForceHttps::class);
        $middleware->validateCsrfTokens(except: [
            'midtrans/notification',
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'tenant.scope' => EnsureTenantScope::class,
            'tenant.active' => EnsureTenantSubscriptionActive::class,
            'route.permission' => EnforceRoutePermission::class,
            'record.mutable' => \App\Http\Middleware\EnsureMedicalRecordMutable::class,
            'onboarding.owner' => EnsureOwnerOnboarding::class,
            'mobile.access' => \App\Http\Middleware\EnsureMobileAccess::class,
            'mobile.branch' => \App\Http\Middleware\ResolveActiveBranch::class,
            'mobile.writes' => \App\Http\Middleware\EnsureMobileWritesAllowed::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
            return $request->is('api/*') || $request->expectsJson();
        });

        $exceptions->render(function (MobileApiException $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            $payload = [
                'message' => $e->getMessage(),
                'code' => $e->errorCode,
            ];

            if ($e->errors !== null) {
                $payload['errors'] = $e->errors;
            }

            return response()->json($payload, $e->status);
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => $e->getMessage() ?: 'This action is unauthorized.',
                'code' => 'forbidden',
            ], 403);
        });

        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if (! $request->is('api/*') || ! $e->getPrevious() instanceof AuthorizationException) {
                return null;
            }

            $previous = $e->getPrevious();

            return response()->json([
                'message' => $previous->getMessage() ?: 'This action is unauthorized.',
                'code' => 'forbidden',
            ], 403);
        });

        $exceptions->reportable(function (Throwable $e) {
            if (app()->environment('production')) {
                logger()->error($e->getMessage(), ['exception' => $e]);
            }
        });
    })->create();
