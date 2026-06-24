<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\AuthCookieService;
use App\Service\JWTService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AuthController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private JWTService $jwtService,
        private ValidatorInterface $validator,
        private UserRepository $userRepository,
        private RateLimiterFactory $loginLimiter,
        private RateLimiterFactory $registrationLimiter,
        private Security $security,
        private AuthCookieService $authCookieService,
    ) {
    }

    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        // Rate limiting - max 10 attempts per 15 minutes per IP
        $limiter = $this->loginLimiter->create($request->getClientIP());
        $limit = $limiter->consume();
        
        if (!$limit->isAccepted()) {
            return $this->json([
                'error' => 'Trop de tentatives de connexion. Veuillez réessayer plus tard.',
                'retry_after' => $limit->getRetryAfter()?->format('Y-m-d H:i:s')
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $data = json_decode($request->getContent(), true);

        // Validate required fields
        $requiredFields = ['email', 'password'];
        $missingFields = [];
        foreach ($requiredFields as $field) {
            if (empty($data[$field] ?? null)) {
                $missingFields[] = $field;
            }
        }
        if (!empty($missingFields)) {
            return $this->json([
                'error' => 'Champs manquants : ' . implode(', ', $missingFields)
            ], Response::HTTP_BAD_REQUEST);
        }

        // Find user by email
        $user = $this->userRepository->findByEmail($data['email']);

        if (!$user) {
            return $this->json([
                'error' => 'Utilisateur non trouvé.'
            ], Response::HTTP_NOT_FOUND);
        }

        // Verify password
        if (!$this->passwordHasher->isPasswordValid($user, $data['password'])) {
            return $this->json([
                'error' => 'Mot de passe incorrect.'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Update last login
        $user->setLastLoginAt(new \DateTimeImmutable());
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Generate token pair
        $tokenPair = $this->jwtService->generateTokenPair($user);

        return $this->authCookieService->createAuthResponse([
            'message' => 'Connexion réussie.',
            'user' => $this->serializeUser($user),
        ], Response::HTTP_OK, $tokenPair);
    }

    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        // Rate limiting - max 5 registrations per hour per IP
        $limiter = $this->registrationLimiter->create($request->getClientIP());
        $limit = $limiter->consume();
        
        if (!$limit->isAccepted()) {
            return $this->json([
                'error' => 'Trop de tentatives d\'inscription. Veuillez réessayer plus tard.',
                'retry_after' => $limit->getRetryAfter()?->format('Y-m-d H:i:s')
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $data = json_decode($request->getContent(), true);

        // Validate required fields
        $requiredFields = ['email', 'password', 'firstName', 'lastName'];
        $missingFields = [];
        foreach ($requiredFields as $field) {
            if (empty($data[$field] ?? null)) {
                $missingFields[] = $field;
            }
        }
        if (!empty($missingFields)) {
            return $this->json([
                'error' => 'Champs manquants : ' . implode(', ', $missingFields)
            ], Response::HTTP_BAD_REQUEST);
        }

        // Check if email already exists
        $existingUser = $this->userRepository->findByEmail($data['email']);
        if ($existingUser) {
            return $this->json([
                'error' => 'Un utilisateur avec cet email existe déjà.'
            ], Response::HTTP_CONFLICT);
        }

        // Create new user
        $user = new User();
        $user->setEmail($data['email']);
        $user->setFirstName($data['firstName']);
        $user->setLastName($data['lastName']);
        $user->setPhone($data['phone'] ?? null);
        $user->setPreferredLanguage($data['preferredLanguage'] ?? 'fr');

        // Hash password
        $hashedPassword = $this->passwordHasher->hashPassword($user, $data['password']);
        $user->setPassword($hashedPassword);

        // Validate user
        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return $this->json([
                'error' => 'Erreurs de validation : ' . implode(', ', $errorMessages)
            ], Response::HTTP_BAD_REQUEST);
        }

        // Save user
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Generate token pair
        $tokenPair = $this->jwtService->generateTokenPair($user);

        return $this->authCookieService->createAuthResponse([
            'message' => 'Utilisateur créé avec succès.',
            'user' => $this->serializeUser($user),
        ], Response::HTTP_CREATED, $tokenPair);
    }

    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = $this->security->getUser();

        if (!$user) {
            return $this->json([
                'error' => 'Utilisateur non trouvé'
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'user' => $this->serializeUser($user, true),
        ], Response::HTTP_OK);
    }

    #[Route('/api/me', name: 'api_update_profile', methods: ['PUT'])]
    public function updateProfile(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->security->getUser();

        if (!$user) {
            return $this->json([
                'error' => 'Utilisateur non trouvé'
            ], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        // Update allowed fields
        if (isset($data['firstName'])) {
            $user->setFirstName($data['firstName']);
        }
        if (isset($data['lastName'])) {
            $user->setLastName($data['lastName']);
        }
        if (isset($data['phone'])) {
            $user->setPhone($data['phone']);
        }
        if (isset($data['preferredLanguage'])) {
            $user->setPreferredLanguage($data['preferredLanguage']);
        }
        if (isset($data['avatar'])) {
            $user->setAvatar($data['avatar']);
        }

        // Validate user
        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return $this->json([
                'error' => 'Erreurs de validation : ' . implode(', ', $errorMessages)
            ], Response::HTTP_BAD_REQUEST);
        }

        // Save changes
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $this->json([
            'message' => 'Profil mis à jour avec succès.',
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'fullName' => $user->getFullName(),
                'phone' => $user->getPhone(),
                'avatar' => $user->getAvatar(),
                'roles' => $user->getRoles(),
                'preferredLanguage' => $user->getPreferredLanguage(),
            ]
        ], Response::HTTP_OK);
    }

    #[Route('/api/refresh-token', name: 'api_refresh_token', methods: ['POST'])]
    public function refreshToken(Request $request): JsonResponse
    {
        $refreshToken = $this->authCookieService->getRefreshTokenFromRequest($request);

        if (empty($refreshToken)) {
            return $this->json([
                'error' => 'Refresh token manquant'
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $decoded = $this->jwtService->decodeToken($refreshToken);
            
            // Verify that it's a refresh token
            if (!isset($decoded['type']) || $decoded['type'] !== 'refresh') {
                return $this->json([
                    'error' => 'Token de type invalide'
                ], Response::HTTP_UNAUTHORIZED);
            }

            // Get user
            $user = $this->userRepository->find($decoded['user_id']);
            if (!$user) {
                return $this->json([
                    'error' => 'Utilisateur non trouvé'
                ], Response::HTTP_NOT_FOUND);
            }

            // Generate new token pair
            $tokenPair = $this->jwtService->generateTokenPair($user);

            return $this->authCookieService->createAuthResponse([
                'message' => 'Tokens renouvelés avec succès',
            ], Response::HTTP_OK, $tokenPair);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Refresh token invalide ou expiré'
            ], Response::HTTP_UNAUTHORIZED);
        }
    }

    #[Route('/api/validate-token', name: 'api_validate_token', methods: ['POST'])]
    public function validateToken(Request $request): JsonResponse
    {
        // With Symfony security, the user is already authenticated if we reach here
        /** @var User $user */
        $user = $this->security->getUser();
        
        if (!$user) {
            return $this->json([
                'error' => 'Utilisateur non trouvé'
            ], Response::HTTP_NOT_FOUND);
        }
        
        return $this->json([
            'valid' => true,
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'roles' => $user->getRoles(),
            ]
        ], Response::HTTP_OK);
    }

    #[Route('/api/logout', name: 'api_logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        $response = $this->json([
            'message' => 'Déconnexion réussie',
        ], Response::HTTP_OK);

        return $this->authCookieService->clearTokens($response);
    }

    private function serializeUser(User $user, bool $extended = false): array
    {
        $data = [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'fullName' => $user->getFullName(),
            'phone' => $user->getPhone(),
            'avatar' => $user->getAvatar(),
            'roles' => $user->getRoles(),
            'preferredLanguage' => $user->getPreferredLanguage(),
            'isVerified' => $user->isVerified(),
        ];

        if ($extended) {
            $data['createdAt'] = $user->getCreatedAt()->format('Y-m-d H:i:s');
            $data['lastLoginAt'] = $user->getLastLoginAt()?->format('Y-m-d H:i:s');
        } else {
            $data['lastLoginAt'] = $user->getLastLoginAt()?->format('Y-m-d H:i:s');
        }

        return $data;
    }

    #[Route('/api/heartbeat', name: 'api_heartbeat', methods: ['POST'])]
    public function heartbeat(Request $request): JsonResponse
    {
        // Get user from Symfony security
        /** @var User $user */
        $user = $this->security->getUser();
        
        if (!$user) {
            return $this->json([
                'error' => 'Utilisateur non trouvé'
            ], Response::HTTP_NOT_FOUND);
        }

        // Update last login to track online status
        $user->setLastLoginAt(new \DateTimeImmutable());
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $this->json([
            'ok' => true,
            'lastLoginAt' => $user->getLastLoginAt()?->format('Y-m-d H:i:s'),
        ], Response::HTTP_OK);
    }
}
