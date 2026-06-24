<?php

namespace App\Service;

use App\Entity\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

class JWTService
{
    private string $privateKey;
    private int $accessTokenTTL;
    private int $refreshTokenTTL;

    public function __construct(
        private JWTTokenManagerInterface $jwtTokenManager
    ) {
        // Access Token : 1 heure
        $this->accessTokenTTL = 3600;

        // Refresh Token : 7 jours
        $this->refreshTokenTTL = 604800;

        $privateKeyPath = dirname(__DIR__, 2) . '/config/jwt/private.pem';

        if (!file_exists($privateKeyPath)) {
            throw new \RuntimeException(
                sprintf('Clé JWT introuvable : %s', $privateKeyPath)
            );
        }

        $this->privateKey = file_get_contents($privateKeyPath);
    }

    /**
     * Génère une paire de tokens.
     */
    public function generateTokenPair(User $user): array
    {
        return [
            'access_token' => $this->jwtTokenManager->create($user),
            'refresh_token' => $this->generateRefreshToken($user),
            'token_type' => 'Bearer',
            'expires_in' => $this->accessTokenTTL,
            'refresh_expires_in' => $this->refreshTokenTTL,
        ];
    }

    /**
     * Génère un refresh token.
     */
    private function generateRefreshToken(User $user): string
    {
        $issuedAt = time();
        $expiresAt = $issuedAt + $this->refreshTokenTTL;

        $payload = [
            'iat' => $issuedAt,
            'exp' => $expiresAt,
            'user_id' => $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'type' => 'refresh',
        ];

        return JWT::encode(
            $payload,
            $this->privateKey,
            'HS256'
        );
    }

    /**
     * Décode un refresh token.
     */
    public function decodeToken(string $token): array
    {
        try {
            $decoded = JWT::decode(
                $token,
                new Key($this->privateKey, 'HS256')
            );

            return (array) $decoded;
        } catch (\Throwable $e) {
            throw new \Exception(
                'Token invalide ou expiré : ' . $e->getMessage()
            );
        }
    }

    /**
     * Vérifie la validité d'un token.
     */
    public function validateToken(string $token): bool
    {
        try {
            $this->decodeToken($token);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Retourne les données d'un token.
     */
    public function getTokenData(string $token): ?array
    {
        try {
            return $this->decodeToken($token);
        } catch (\Throwable) {
            return null;
        }
    }
}