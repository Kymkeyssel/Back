<?php

namespace App\Controller\Api;

use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class NotificationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private NotificationRepository $notificationRepository
    ) {
    }

    #[Route('/api/notifications', name: 'api_notifications', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'Authentication required.'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $notifications = $this->notificationRepository->findByUser($user->getId());

        $data = [];
        foreach ($notifications as $notification) {
            $data[] = $this->serializeNotification($notification);
        }

        return $this->json([
            'success' => true,
            'data' => $data
        ]);
    }

    #[Route('/api/notifications/{id}/read', name: 'api_notification_read', methods: ['PUT'])]
    public function markAsRead(int $id): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'Authentication required.'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $notification = $this->notificationRepository->find($id);

        if (!$notification) {
            return $this->json([
                'success' => false,
                'message' => 'Notification not found.'
            ], Response::HTTP_NOT_FOUND);
        }

        // Check ownership
        if ($notification->getUser() !== $user) {
            return $this->json([
                'success' => false,
                'message' => 'You do not have permission to update this notification.'
            ], Response::HTTP_FORBIDDEN);
        }

        // Mark as read
        $notification->setIsRead(true);
        $notification->setReadAt(new \DateTimeImmutable());

        // Save
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Notification marked as read.',
            'data' => $this->serializeNotification($notification)
        ]);
    }

    #[Route('/api/notifications/read-all', name: 'api_notifications_read_all', methods: ['PUT'])]
    public function markAllAsRead(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'Authentication required.'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Mark all as read
        $this->notificationRepository->markAllAsReadByUser($user->getId());

        return $this->json([
            'success' => true,
            'message' => 'All notifications marked as read.'
        ]);
    }

    private function serializeNotification($notification): array
    {
        return [
            'id' => $notification->getId(),
            'type' => $notification->getType(),
            'title' => $notification->getTitle(),
            'message' => $notification->getMessage(),
            'data' => $notification->getData(),
            'isRead' => $notification->isRead(),
            'readAt' => $notification->getReadAt()?->format('Y-m-d H:i:s'),
            'createdAt' => $notification->getCreatedAt()->format('Y-m-d H:i:s'),
        ];
    }
}
