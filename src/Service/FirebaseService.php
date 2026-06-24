<?php

namespace App\Service;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class FirebaseService
{
    private $messaging;
    private $enabled;

    public function __construct(
        private ?string $credentialsPath = null,
        private ?string $projectId = null
    ) {
        $this->credentialsPath = $credentialsPath ?? $_ENV['FIREBASE_CREDENTIALS'] ?? null;
        $this->projectId = $projectId ?? $_ENV['FIREBASE_PROJECT_ID'] ?? null;
        $this->enabled = !empty($this->credentialsPath) && !empty($this->projectId);

        if ($this->enabled && file_exists($this->credentialsPath)) {
            try {
                $firebase = (new Factory)->withServiceAccount($this->credentialsPath);
                $this->messaging = $firebase->createMessaging();
            } catch (\Exception $e) {
                $this->enabled = false;
            }
        } else {
            $this->enabled = false;
        }
    }

    /**
     * Check if Firebase is enabled
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Send notification to single device
     */
    public function sendToDevice(string $token, string $title, string $body, array $data = []): array
    {
        if (!$this->enabled) {
            return [
                'success' => false,
                'error' => 'Firebase not configured',
            ];
        }

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
        if (!$this->enabled) {
            return [
                'success' => false,
                'error' => 'Firebase not configured',
            ];
        }

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
        if (!$this->enabled) {
            return [
                'success' => false,
                'error' => 'Firebase not configured',
            ];
        }

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
        if (!$this->enabled) {
            return [
                'success' => false,
                'error' => 'Firebase not configured',
            ];
        }

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
        if (!$this->enabled) {
            return [
                'success' => false,
                'error' => 'Firebase not configured',
            ];
        }

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
