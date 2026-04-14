<?php

namespace App\Service;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Label\Alignment\LabelAlignment;
use Endroid\QrCode\Label\Font\OpenSans;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;

class QRCodeService
{
    /**
     * Generate QR code for ticket
     */
    public function generateTicketQRCode(array $ticketData): string
    {
        $qrContent = json_encode([
            'ticketId' => $ticketData['ticketId'],
            'bookingRef' => $ticketData['bookingRef'],
            'tripId' => $ticketData['tripId'],
            'passengerName' => $ticketData['passengerName'],
            'seatNumber' => $ticketData['seatNumber'],
            'departureTime' => $ticketData['departureTime'],
            'signature' => $this->generateSignature($ticketData),
        ]);

        $result = Builder::create()
            ->writer(new PngWriter())
            ->data($qrContent)
            ->encoding(new Encoding('UTF-8'))
            ->errorCorrectionLevel(ErrorCorrectionLevel::High)
            ->size(300)
            ->margin(10)
            ->roundBlockSizeMode(RoundBlockSizeMode::Margin)
            ->labelText('TransCam Ticket')
            ->labelAlignment(LabelAlignment::Center)
            ->build();

        return $result->getDataUri();
    }

    /**
     * Generate QR code for booking
     */
    public function generateBookingQRCode(array $bookingData): string
    {
        $qrContent = json_encode([
            'bookingRef' => $bookingData['reference'],
            'userId' => $bookingData['userId'],
            'tripId' => $bookingData['tripId'],
            'totalPrice' => $bookingData['totalPrice'],
            'numberOfSeats' => $bookingData['numberOfSeats'],
            'status' => $bookingData['status'],
            'signature' => $this->generateSignature($bookingData),
        ]);

        $result = Builder::create()
            ->writer(new PngWriter())
            ->data($qrContent)
            ->encoding(new Encoding('UTF-8'))
            ->errorCorrectionLevel(ErrorCorrectionLevel::High)
            ->size(300)
            ->margin(10)
            ->roundBlockSizeMode(RoundBlockSizeMode::Margin)
            ->labelText('TransCam Booking')
            ->labelAlignment(LabelAlignment::Center)
            ->build();

        return $result->getDataUri();
    }

    /**
     * Validate QR code data
     */
    public function validateQRCode(string $qrCodeData): array
    {
        $data = json_decode($qrCodeData, true);

        if (!$data) {
            return [
                'valid' => false,
                'message' => 'Invalid QR code format.',
            ];
        }

        // Verify signature
        $expectedSignature = $this->generateSignature($data);
        if (!isset($data['signature']) || $data['signature'] !== $expectedSignature) {
            return [
                'valid' => false,
                'message' => 'Invalid QR code signature.',
            ];
        }

        return [
            'valid' => true,
            'data' => $data,
        ];
    }

    /**
     * Generate signature for data
     */
    private function generateSignature(array $data): string
    {
        // Remove signature from data if present
        $dataForSignature = $data;
        unset($dataForSignature['signature']);

        // Create signature
        $secret = $_ENV['APP_SECRET'] ?? 'default_secret';
        return hash_hmac('sha256', json_encode($dataForSignature), $secret);
    }
}
