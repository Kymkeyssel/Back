<?php

namespace App\Service;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class FirebaseService
{
    private $messaging;

    public function __construct()
    {
        $firebase = (new Factory)->withServiceAccount(
            $_ENV['FIREBASE_CREDENTIALS'] ?? '/path/to/firebase-credentials.json'
        );

        $this->messaging = $firebase->createMessaging();
    }

    /**
     * Send notification to single device
     */
    public function sendToDevice(string $token, string $title, string $body, array $data = []): array
    {
        try {
            $message = CloudMessage::withTarget('token', $token)
                ->withNotification(Notification::create($title, $body))
                ->withData($data);

            $result = $this->messaging->send($message);

            return [
                'success' => true,
                'messageId' => $result->name(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send notification to multiple devices
     */
    public function sendMulticast(array $tokens, string $title, string $body, array $data = []): array
    {
        try {
            $message = CloudMessage::new()
                ->withNotification(Notification::create($title, $body))
                ->withData($data);

            $result = $this->messaging->sendMulticast($message, $tokens);

            return [
                'success' => true,
                'successCount' => $result->successes()->count(),
                'failureCount' => $result->failures()->count(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send notification to topic
     */
    public function sendToTopic(string $topic, string $title, string $body, array $data = []): array
    {
        try {
            $message = CloudMessage::withTarget('topic', $topic)
                ->withNotification(Notification::create($title, $body))
                ->withData($data);

            $result = $this->messaging->send($message);

            return [
                'success' => true,
                'messageId' => $result->name(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Subscribe device to topic
     */
    public function subscribeToTopic(string $token, string $topic): array
    {
        try {
            $this->messaging->subscribeToTopic($token, $topic);

            return [
                'success' => true,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Unsubscribe device from topic
     */
    public function unsubscribeFromTopic(string $token, string $topic): array
    {
        try {
            $this->messaging->unsubscribeFromTopic($token, $topic);

            return [
                'success' => true,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
