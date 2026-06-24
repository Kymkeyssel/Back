<?php

namespace App\Controller;

use App\Service\Payment\NotchPayService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TestNotchPayController extends AbstractController
{
    #[Route('/test/notchpay', name: 'test_notchpay', methods: ['GET'])]
    public function test(NotchPayService $notchPayService): JsonResponse
    {
        if ($this->getParameter('kernel.environment') !== 'dev') {
            return $this->json([
                'success' => false,
                'message' => 'NotchPay test endpoint is only available in dev environment.'
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $response = $notchPayService->createPayment(
                100,
                'Test User',
                'test@example.com',
                '+237690000000',
                'TEST-' . time(),
                'http://localhost:5173/payment/callback'
            );

            return $this->json($response);
        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'message' => 'Unable to initialize NotchPay test payment.',
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_GATEWAY);
        }
    }
}
