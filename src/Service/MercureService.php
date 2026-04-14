<?php

namespace App\Service;

use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

class MercureService
{
    public function __construct(
        private HubInterface $hub
    ) {
    }

    /**
     * Publish trip location update
     */
    public function publishTripLocation(int $tripId, float $latitude, float $longitude): void
    {
        $update = new Update(
            "trip/{$tripId}/location",
            json_encode([
                'tripId' => $tripId,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'timestamp' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ])
        );

        $this->hub->publish($update);
    }

    /**
     * Publish trip status change
     */
    public function publishTripStatusChange(int $tripId, string $status): void
    {
        $update = new Update(
            "trip/{$tripId}/status",
            json_encode([
                'tripId' => $tripId,
                'status' => $status,
                'timestamp' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ])
        );

        $this->hub->publish($update);
    }

    /**
     * Publish booking status change
     */
    public function publishBookingStatusChange(int $bookingId, string $status, int $userId): void
    {
        $update = new Update(
            "user/{$userId}/bookings",
            json_encode([
                'bookingId' => $bookingId,
                'status' => $status,
                'timestamp' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ])
        );

        $this->hub->publish($update);
    }

    /**
     * Publish new notification
     */
    public function publishNotification(int $userId, array $notification): void
    {
        $update = new Update(
            "user/{$userId}/notifications",
            json_encode([
                'notification' => $notification,
                'timestamp' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ])
        );

        $this->hub->publish($update);
    }

    /**
     * Publish chat message
     */
    public function publishChatMessage(string $conversationId, array $message): void
    {
        $update = new Update(
            "chat/{$conversationId}",
            json_encode([
                'message' => $message,
                'timestamp' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ])
        );

        $this->hub->publish($update);
    }
}
