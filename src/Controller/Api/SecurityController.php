<?php

namespace App\Controller\Api;

use App\Entity\User;
use Firebase\JWT\JWT;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class SecurityController extends AbstractController
{
    public function __construct(
        private CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[Route('/api/csrf-token', name: 'api_csrf_token', methods: ['GET'])]
    public function csrfToken(): JsonResponse
    {
        return $this->json([
            'token' => $this->csrfTokenManager->getToken('api')->getValue(),
        ]);
    }

    #[Route('/api/mercure-token', name: 'api_mercure_token', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function mercureToken(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $userId = $user->getId();

        $topics = [
            "user/{$userId}/notifications",
            "user/{$userId}/bookings",
        ];

        if ($this->isGranted('ROLE_AGENCY') || $this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_SUPER_ADMIN')) {
            $topics[] = 'trip/*/location';
            $topics[] = 'trip/*/status';
        }

        $secret = $_ENV['MERCURE_JWT_SECRET'] ?? '!ChangeThisMercureHubJWTSecretKey!';
        $publicUrl = $_ENV['MERCURE_PUBLIC_URL'] ?? 'http://127.0.0.1:3000/.well-known/mercure';

        $payload = [
            'mercure' => ['subscribe' => array_values(array_unique($topics))],
            'exp' => time() + 3600,
        ];

        $token = JWT::encode($payload, $secret, 'HS256');

        return $this->json([
            'token' => $token,
            'hub_url' => $publicUrl,
            'topics' => $topics,
        ]);
    }
}
