<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rename Laravel's XSRF-TOKEN cookie to __Secure-XSRF-TOKEN.
 *
 * The __Secure- prefix instructs browsers to reject the cookie if it lacks
 * the Secure flag — protecting against MITM cookie injection over HTTP.
 * Resolves Mozilla Observatory's "cookie prefix" finding without breaking
 * Laravel's CSRF flow: validation uses the X-XSRF-TOKEN header (or @csrf
 * form input on Blade forms), not the cookie name.
 */
class RenameXsrfCookie
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() !== 'XSRF-TOKEN') {
                continue;
            }

            $response->headers->removeCookie(
                $cookie->getName(),
                $cookie->getPath(),
                $cookie->getDomain()
            );

            $response->headers->setCookie(new Cookie(
                '__Secure-XSRF-TOKEN',
                $cookie->getValue(),
                $cookie->getExpiresTime(),
                $cookie->getPath() ?: '/',
                $cookie->getDomain(),
                true,
                $cookie->isHttpOnly(),
                $cookie->isRaw(),
                $cookie->getSameSite()
            ));
        }

        return $response;
    }
}
