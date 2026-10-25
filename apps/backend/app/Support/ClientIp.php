<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * The visitor's IP. Customer-account calls arrive through the storefront's server (it holds the login
 * cookie), so every request would share its IP; the storefront forwards the real one in X-Client-IP
 * and proves it with the shared revalidate secret. Without the secret the header is ignored.
 */
final class ClientIp
{
    public static function of(Request $request): string
    {
        $secret = (string) config('shop.revalidate_secret');
        $forwarded = $request->header('X-Client-IP');

        if ($secret !== '' && $forwarded && hash_equals($secret, (string) $request->header('X-Storefront-Key')) && filter_var($forwarded, FILTER_VALIDATE_IP)) {
            return $forwarded;
        }

        return (string) $request->ip();
    }
}
