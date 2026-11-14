<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Mobile\ActiveBranchResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveActiveBranch
{
    public function __construct(protected ActiveBranchResolver $branches) {}

    public function handle(Request $request, Closure $next, string $mode = 'required'): Response
    {
        $user = $request->user();

        if ($user instanceof User) {
            $request->attributes->set(
                'active_branch',
                $this->branches->resolve($user, $this->branches->requestedId($request->header('X-Branch-Id')), $mode !== 'optional')
            );
        }

        return $next($request);
    }
}