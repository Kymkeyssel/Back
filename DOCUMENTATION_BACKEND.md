# Guide Complet du Backend TransCam

Ce document explique de A à Z le fonctionnement du backend TransCam pour permettre à un développeur PHP (même débutant sur Symfony) de comprendre et de travailler sur le projet.

---

## Table des Matières

1. [Vue d'Ensemble du Projet](#1-vue-densemble-du-projet)
2. [Architecture du Projet](#2-architecture-du-projet)
3. [Structure des Dossiers](#3-structure-des-dossiers)
4. [Les Entités (Models)](#4-les-entités-models)
5. [Les Repositories](#5-les-repositories)
6. [Les Controllers API](#6-les-controllers-api)
7. [Les Services](#7-les-services)
8. [Configuration](#8-configuration)
9. [Flux de Données](#9-flux-de-données)
10. [Commandes Utiles](#10-commandes-utiles)

---

## 1. Vue d'Ensemble du Projet

### Qu'est-ce que TransCam ?

TransCam est une plateforme de transport au Cameroun qui permet aux utilisateurs de :
- Rechercher des trajets entre les villes
- Réserver des places dans des bus, minibus ou taxis
- Payer en ligne via Mobile Money (MTN, Orange) ou carte bancaire
- Recevoir des billets avec QR Code
- Recevoir des notifications sur leurs réservations

### Technologies Utilisées

- **PHP 8.1+** - Langage serveur
- **Symfony 6.4** - Framework PHP
- **PostgreSQL 15** - Base de données relationnelle
- **Doctrine ORM** - Gestion de la base de données
- **JWT** - Authentification par tokens
- **Mercure** - WebSocket pour temps réel

---

## 2. Architecture du Projet

Le projet est divisé en deux parties :

```
cameroon-connect-transport/
├── Back/           → Backend (Symfony PHP) ← ON SE CONCENTRE ICI
└── Front/          → Frontend (React/TypeScript)
```

### Comment fonctionne Symfony ?

Symfony est un framework PHP qui suit le pattern **MVC** (Model-View-Controller) :

1. **Model** = Entités (src/Entity/) = Représentent les données
2. **View** = Templates (templates/) = Rendu HTML (peu utilisé en API)
3. **Controller** = src/Controller/Api/ = Logique de l'application

Le flux typical est :

```
Requête HTTP (API)
    ↓
Controller (réçoit la requête)
    ↓
Repository (interroge la base de données)
    ↓
Entity (représente les données)
    ↓
Response JSON
```

---

## 3. Structure des Dossiers

Voici l'arborescence du dossier `Back/` :

```
Back/
├── .env                          # Variables d'environnement (config sensitive)
├── composer.json                 # Dépendances PHP (comme package.json)
├── config/                       # Configuration du projet
│   ├── bundles.php               # Liste des bundles actifs
│   ├── services.yaml             # Configuration des services
│   ├── packages/                 # Configuration des bundles
│   │   ├── doctrine.yaml         # Base de données
│   │   ├── security.yaml         # Sécurité/Authentification
│   │   ├── framework.yaml        # Framework principal
│   │   └── ... (autres configs)
│   └── routes/                   # Définition des routes API
│       └── api.yaml             # Toutes les routes API
├── src/                         # Code source de l'application
│   ├── Entity/                  # Modèles de données (tables DB)
│   ├── Repository/              # Accès aux données
│   ├── Controller/Api/          # Les endpoints API
│   ├── Service/                 # Logique métier
│   ├── DataFixtures/            # Données de test
│   └── Kernel.php               # Point d'entrée
├── public/                      # Fichiers publics (index.php)
├── bin/                         # Commandes Symfony
├── migrations/                  # Migrations base de données
└── tests/                       # Tests unitaires
```

---

## 4. Les Entités (Models)

Les entités sont des classes PHP qui représentent les tables de la base de données. Chaque entité correspond à une table.

### 4.1 Liste des Entités

| Entité       | Fichier          | Description                         |
|--------------|------------------|-------------------------------------|
| User         | User.php         | Utilisateur (client, agency, admin) |
| Agency       | Agency.php       | Agence de transport                 |
| Vehicle      | Vehicle.php      | Véhicule (bus, minibus)             |
| Trip         | Trip.php         | Trajet (départ, arrivée, prix)      |
| Booking      | Booking.php      | Réservation                         |
| Payment      | Payment.php      | Paiement                            |
| Ticket       | Ticket.php       | Billet avec QR Code                 |
| Review       | Review.php       | Avis sur une agence                 |
| Notification | Notification.php | Notification                        |
| + 8 autres entités... | | |

### 4.2 Anatomie d'une Entité

Voici l'exemple de l'entité User (src/Entity/User.php) :

```php
<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

// Cette annotation dit à Doctrine que cette classe est une table
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
class User implements UserInterface
{
    // Chaque propriété = une colonne dans la table
    
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]  // Colonne auto-incrémentée
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]  // VARCHAR(180), unique
    private ?string $email = null;

    #[ORM\Column]  // Colonne JSON pour les rôles
    private array $roles = [];

    #[ORM\Column]  // Mot de passe hashé
    private ?string $password = null;

    #[ORM\Column(length: 100)]
    private ?string $firstName = null;

    #[ORM\Column(length: 100)]
    private ?string $lastName = null;

    // Relations avec d'autres tables
    
    #[ORM\OneToMany(targetEntity: Booking::class, mappedBy: 'user')]
    private Collection $bookings;

    // Getters et Setters (méthodes pour accéder/modifier les propriétés)
    
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    // ... autres getters/setters
}
```

### 4.3 Annotations Doctrine Courantes

- `#[ORM\Entity]` - Marque la classe comme une entité
- `#[ORM\Table(name: 'nom_table')]` - Nom de la table dans la DB
- `#[ORM\Column]` - Colonne standard (type determines selon le type PHP)
- `#[ORM\Column(type: Types::TEXT)]` - Colonne texte long
- `#[ORM\Column(type: Types::FLOAT)]` - Nombre décimal
- `#[ORM\ManyToOne]` - Relation plusieurs-à-un
- `#[ORM\OneToMany]` - Relation un-à-plusieurs
- `#[ORM\ManyToMany]` - Relation plusieurs-à-plusieurs
- `#[ORM\JoinColumn]` - Colonne de liaison étrangère

### 4.4 Exemple de Relation

Relation entre User et Booking (un utilisateur peut avoir plusieurs réservations) :

```php
// Dans User.php
#[ORM\OneToMany(targetEntity: Booking::class, mappedBy: 'user')]
private Collection $bookings;

// Dans Booking.php (l'autre côté de la relation)
#[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'bookings')]
#[ORM\JoinColumn(nullable: false)]
private ?User $user = null;
```

Cela crée une clé étrangère `user_id` dans la table `bookings`.

---

## 5. Les Repositories

Les repositories sont des classes qui permettent de faire des requêtes dans la base de données. Chaque entité a son repository.

### 5.1 Anatomie d'un Repository

```php
<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

// Étend ServiceEntityRepository pour bénéficier des méthodes自动
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    // Méthodes personnalisées
    
    // Trouver un utilisateur par email
    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }

    // Requête plus complexe avec QueryBuilder
    public function findUsersByRole(string $role): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.roles LIKE :role')
            ->setParameter('role', '%' . $role . '%')
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
```

### 5.2 Méthodes Automatiques

En étendant `ServiceEntityRepository`, on obtient automatiquement :

- `findAll()` - Retourne tous les enregistrements
- `find($id)` - Trouve par ID
- `findOneBy(['champ' => 'valeur'])` - Trouve un enregistrement
- `findBy(['champ' => 'valeur'])` - Trouve plusieurs enregistrements

### 5.3 Utilisation dans un Controller

```php
// Injection automatique du repository par Symfony
public function __construct(
    private UserRepository $userRepository
) {}

public function someMethod(): void
{
    // Trouver un utilisateur par email
    $user = $this->userRepository->findByEmail('test@example.com');
    
    // Trouver tous les utilisateurs
    $allUsers = $this->userRepository->findAll();
    
    // Trouver par ID
    $user = $this->userRepository->find(1);
}
```

---

## 6. Les Controllers API

Les controllers reçoivent les requêtes HTTP et retournent des réponses JSON.

### 6.1 Anatomie d'un Controller

Voici l'exemple de AuthController (src/Controller/Api/AuthController.php) :

```php
<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

// Étend AbstractController pour bénéficier des méthodes helper
class AuthController extends AbstractController
{
    // Injection des dépendances via le constructeur
    public function __construct(
        private EntityManagerInterface $entityManager,      // Pour sauvegarder en DB
        private UserPasswordHasherInterface $passwordHasher, // Pour hasher les mots de passe
        private JWTTokenManagerInterface $jwtTokenManager,   // Pour générer les tokens JWT
        private ValidatorInterface $validator,                // Pour valider les données
        private UserRepository $userRepository                // Pour accéder aux utilisateurs
    ) {}

    // Route: POST /api/login
    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        // 1. Récupérer les données JSON de la requête
        $data = json_decode($request->getContent(), true);

        // 2. Valider les données
        if (!isset($data['email']) || !isset($data['password'])) {
            return $this->json([
                'success' => false,
                'message' => 'Email and password are required.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // 3. Rechercher l'utilisateur
        $user = $this->userRepository->findByEmail($data['email']);

        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid credentials.'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // 4. Vérifier le mot de passe
        if (!$this->passwordHasher->isPasswordValid($user, $data['password'])) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid credentials.'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // 5. Générer le token JWT
        $token = $this->jwtTokenManager->create($user);

        // 6. Retourner la réponse JSON
        return $this->json([
            'success' => true,
            'message' => 'Login successful.',
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'firstName' => $user->getFirstName(),
                    // ...
                ]
            ]
        ]);
    }
}
```

### 6.2 Méthodes du Controller

- `$this->json($data, $statusCode)` - Retourne une réponse JSON
- `$this->getUser()` - Retourne l'utilisateur connecté (si authentifié)
- `$this->denyAccessUnlessGranted('ROLE_ADMIN')` - Vérifie les droits

### 6.3 Routes dans api.yaml

Les routes sont définies dans config/routes/api.yaml :

```yaml
api_login:
    path: /api/login
    methods: ['POST']
    controller: App\Controller\Api\AuthController::login

api_register:
    path: /api/register
    methods: ['POST']
    controller: App\Controller\Api\AuthController::register

api_agencies:
    path: /api/agencies
    methods: ['GET']
    controller: App\Controller\Api\AgencyController::index
```

---

## 7. Les Services

Les services contiennent la logique métier. Ils sont injectés dans les controllers.

### 7.1 Liste des Services

| Service | Fichier | Description |
|---------|---------|-------------|
| QRCodeService | QRCodeService.php | Génère les QR Codes |
| NotificationService | NotificationService.php | Envoie les notifications |
| PricingService | PricingService.php | Calcule les prix dynamiques |
| AnalyticsService | AnalyticsService.php | Calcule les métriques |
| ChatbotService | ChatbotService.php | Chatbot IA avec OpenAI |
| Payment services | Payment/*.php | MTN, Orange, Stripe |

### 7.2 Anatomie d'un Service

```php
<?php

namespace App\Service;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

class QRCodeService
{
    public function generateQRCode(string $data): string
    {
        $qrCode = new QrCode($data);
        $writer = new PngWriter();
        
        return $writer->write($qrCode)->getString();
    }
}
```

### 7.3 Injection dans un Controller

```php
public function __construct(
    private QRCodeService $qrCodeService
) {}

public function generateTicket(): JsonResponse
{
    $qrCodeImage = $this->qrCodeService->generateQRCode('TICKET-123');
    
    return $this->json(['qrCode' => base64_encode($qrCodeImage)]);
}
```

---

## 8. Configuration

### 8.1 Fichier .env

Le fichier .env contient les variables d'environnement (configurations sensibles) :

```env
# Base de données PostgreSQL
DATABASE_URL="postgresql://dev:dev123@127.0.0.1:5432/transcam_dev?serverVersion=16"

# JWT (Authentification)
JWT_SECRET_KEY=config/jwt/private.pem
JWT_PUBLIC_KEY=config/jwt/public.pem
JWT_PASSPHRASE=transcam_jwt_passphrase_2026

# Paiements (à configurer pour la production)
MTN_MOMO_API_KEY=
ORANGE_MONEY_API_KEY=
STRIPE_SECRET_KEY=

# Firebase (Notifications push)
FIREBASE_API_KEY=
FIREBASE_PROJECT_ID=

# OpenAI (Chatbot)
OPENAI_API_KEY=
```

### 8.2 Configuration Doctrine (config/packages/doctrine.yaml)

```yaml
doctrine:
    dbal:
        url: '%env(resolve:DATABASE_URL)%'
    orm:
        auto_mapping: true  # Scan automatiquement src/Entity
        mappings:
            App:
                type: attribute  # Utilise les annotations PHP 8
                dir: '%kernel.project_dir%/src/Entity'
                prefix: 'App\Entity'
```

### 8.3 Configuration Sécurité (config/packages/security.yaml)

```yaml
security:
    password_hashers:
        App\Entity\User:
            algorithm: auto

    providers:
        app_user_provider:
            entity:
                class: App\Entity\User
                property: email

    firewalls:
        api:
            pattern: ^/api
            stateless: true
            jwt: ~  # Authentication JWT

    access_control:
        - { path: ^/api/login, roles: PUBLIC_ACCESS }
        - { path: ^/api/register, roles: PUBLIC_ACCESS }
        - { path: ^/api/trips, roles: PUBLIC_ACCESS }
        - { path: ^/api, roles: IS_AUTHENTICATED_FULLY }
```

---

## 9. Flux de Données

### 9.1 Inscription d'un Utilisateur

```
POST /api/register
    ↓
AuthController::register()
    ↓
Vérifie si l'email existe déjà (UserRepository)
    ↓
Crée un nouvel objet User
    ↓
Hash le mot de passe (UserPasswordHasher)
    ↓
Valide les données (Validator)
    ↓
Sauvegarde en base de données (EntityManager)
    ↓
Génère un token JWT (JWTTokenManager)
    ↓
Retourne JSON avec token et données utilisateur
```

### 9.2 Recherche de Trajets

```
GET /api/trips?from=Douala&to=Yaounde
    ↓
TripController::index()
    ↓
TripRepository->findTripsByCities()
    ↓
Requête SQL: SELECT * FROM trips WHERE...
    ↓
Retourne liste de trips
    ↓
Retourne JSON avec les trips
```

---

## 10. Commandes Utiles

### 10.1 Commandes de Développement

```bash
# Démarrer le serveur内置
cd Back
php -S 127.0.0.1:8000 -t public

# Vider le cache
php bin/console cache:clear

# Créer une entité (avec MakerBundle)
php bin/console make:entity

# Créer un controller
php bin/console make:controller

# Créer une migration
php bin/console make:migration

# Exécuter les migrations
php bin/console doctrine:migrations:migrate

# Charger les fixtures (données de test)
php bin/console doctrine:fixtures:load

# Lister les routes
php bin/console debug:router

# Vérifier la configuration
php bin/console debug:config
```

### 10.2 Commandes Docker

```bash
# Démarrer les containers
docker-compose up -d

# Voir les logs
docker logs transcam_php

# Accéder à PostgreSQL
docker exec -it transcam_database psql -U dev -d transcam_dev
```

---

## Résumé

1. **Entités** (src/Entity/) = Tables de la base de données en objets PHP
2. **Repositories** = Classes pour interroger la DB (SELECT, INSERT, etc.)
3. **Controllers** = Reçoivent les requêtes API et appellent les services
4. **Services** = Logique métier (paiement, notifications, etc.)
5. **Configuration** = .env et config/packages/ pour la config globale

Pour commencer à développer sur ce projet :

1. Regardez une entité pour comprendre la structure des données
2. Regardez le repository correspondant pour voir comment récupérer ces données
3. Regardez le controller pour voir comment les endpoints API fonctionnent
4. Regardez les services pour la logique métier spécifique

---

Document généré le 13 Avril 2026
Projet TransCam - Cameroon Connect Transport