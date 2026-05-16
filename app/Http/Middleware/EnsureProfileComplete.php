<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProfileComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->hasRole('applicant') && ! $user->applicantProfile()->exists()) {
            return redirect()->route('profile.setup');
        }

        return $next($request);
    }
}
