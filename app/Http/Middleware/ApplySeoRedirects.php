<?php

namespace App\Http\Middleware;

use App\Models\SeoRedirect;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class ApplySeoRedirects
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), ['GET', 'HEAD'], true)) {
            return $next($request);
        }

        try {
            if (! Schema::hasTable('seo_redirects')) {
                return $next($request);
            }
        } catch (\Throwable) {
            return $next($request);
        }

        $path = '/'.ltrim($request->getPathInfo(), '/');
        if ($path !== '/') {
            $path = rtrim($path, '/') ?: '/';
        }

        $redirect = SeoRedirect::query()
            ->where('is_active', true)
            ->where('source_path', $path)
            ->first();

        if (! $redirect) {
            return $next($request);
        }

        $destination = $this->safeDestination($redirect->destination_url, $request);
        if ($destination === null) {
            return $next($request);
        }

        $redirect->increment('hit_count');

        return redirect()->to($destination, $redirect->status_code === 302 ? 302 : 301);
    }

    protected function safeDestination(string $destination, Request $request): ?string
    {
        $destination = trim($destination);
        if ($destination === '') {
            return null;
        }

        if (str_starts_with($destination, '/')) {
            return $destination;
        }

        $host = parse_url($destination, PHP_URL_HOST);
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        if ($host && $appHost && strcasecmp($host, $appHost) === 0) {
            return $destination;
        }

        return null;
    }
}
