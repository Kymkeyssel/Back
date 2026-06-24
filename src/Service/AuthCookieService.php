<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class AuthCookieService
{
    public const ACCESS_COOKIE = 'one4all_token';
    public const REFRESH_COOKIE = 'one4all_refresh';

    public function attachTokens(JsonResponse $response, array $tokenPair): JsonResponse
    {
        $secure = ($_ENV['APP_ENV'] ?? 'dev') === 'prod';

        $response->headers->setCookie($this->createAccessCookie(
            $tokenPair['access_token'],
            (int) ($tokenPair['expires_in'] ?? 3600),
            $secure
        ));

        $response->headers->setCookie($this->createRefreshCookie(
            $tokenPair['refresh_token'],
            (int) ($tokenPair['refresh_expires_in'] ?? 604800),
            $secure
        ));

        return $response;
    }

    public function createAuthResponse(array $payload, int $status, array $tokenPair): JsonResponse
    {
        unset($payload['tokens']);

        $response = new JsonResponse($payload, $status);
        $this->attachTokens($response, $tokenPair);

        return $response;
    }

    public function clearTokens(JsonResponse $response): JsonResponse
    {
        $response->headers->clearCookie(self::ACCESS_COOKIE, '/', null, false, true, Cookie::SAMESITE_LAX);
        $response->headers->clearCookie(self::REFRESH_COOKIE, '/', null, false, true, Cookie::SAMESITE_LAX);

        return $response;
    }

    public function getRefreshTokenFromRequest(Request $request): ?string
    {
        return $request->cookies->get(self::REFRESH_COOKIE)
            ?? (json_decode($request->getContent(), true)['refresh_token'] ?? null);
    }

    private function createAccessCookie(string $token, int $ttl, bool $secure): Cookie
    {
        return Cookie::create(
            self::ACCESS_COOKIE,
            $token,
            time() + $ttl,
            '/',
            null,
            $secure,
            true,
            false,
            Cookie::SAMESITE_LAX
        );
    }

    private function createRefreshCookie(string $token, int $ttl, bool $secure): Cookie
    {
        return Cookie::create(
            self::REFRESH_COOKIE,
            $token,
            time() + $ttl,
            '/',
            null,
            $secure,
            true,
            false,
            Cookie::SAMESITE_LAX
        );
    }
}
