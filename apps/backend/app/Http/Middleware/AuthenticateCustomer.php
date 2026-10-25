<?php

namespace App\Http\Middleware;

use App\Domain\Customer\Models\Customer;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Bearer token of a storefront customer (not a staff user), who isn't blocked. */
class AuthenticateCustomer
{
    public function handle(Request $request, Closure $next): Response
    {
        $customer = Auth::guard('sanctum')->user();

        if (! $customer instanceof Customer || $customer->is_blocked) {
            return response()->json(['message' => 'লগইন করুন।'], 401);
        }

        $request->setUserResolver(fn () => $customer);

        return $next($request);
    }
}
