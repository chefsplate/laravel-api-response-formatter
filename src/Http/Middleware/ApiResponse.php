<?php

namespace ChefsPlate\API\Http\Middleware;

use ChefsPlate\API\Http\ResponseObject;
use Closure;
use Illuminate\Http\Response;

class ApiResponse
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure                 $next
     *
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $result = $next($request);

        if ($result instanceof Response) {
            $content = $result->getOriginalContent();
            if ($content instanceof ResponseObject) {
                return response($content, $content->getStatus());
            }
        }

        return $result;
    }
}
