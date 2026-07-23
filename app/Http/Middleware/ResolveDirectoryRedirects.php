<?php

namespace App\Http\Middleware;

use App\Models\DirectoryRedirect;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ResolveDirectoryRedirects
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $response = $next($request);
        } catch (NotFoundHttpException $exception) {
            $response = $this->resolve($request);

            if ($response) {
                return $response;
            }

            throw $exception;
        }

        if (! in_array($request->method(), ['GET', 'HEAD'], true) || $response->getStatusCode() !== 404) {
            return $response;
        }

        return $this->resolve($request) ?? $response;
    }

    private function resolve(Request $request): ?Response
    {
        if (! in_array($request->method(), ['GET', 'HEAD'], true)) {
            return null;
        }

        $sourcePath = '/'.ltrim($request->path(), '/');
        $redirect = DirectoryRedirect::query()
            ->where('source_path', $sourcePath)
            ->where('is_active', true)
            ->first();

        if (! $redirect) {
            return null;
        }

        if ($redirect->status_code === 410) {
            return response('Gone', 410, [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'X-Robots-Tag' => 'noindex, nofollow',
            ]);
        }

        return redirect($redirect->target_path, $redirect->status_code);
    }
}
