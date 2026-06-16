<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStudentPortalAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! session()->has('student_portal_id')) {
            return redirect()->route('portal.login');
        }

        return $next($request);
    }
}
