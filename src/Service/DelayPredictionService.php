<?php

namespace App\Service;

use App\Entity\Agency;
use App\Entity\DelayHistory;
use App\Entity\DelayPrediction;
use App\Entity\Trip;
use App\Repository\DelayHistoryRepository;
use App\Repository\DelayPredictionRepository;
use App\Repository\TripRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class DelayPredictionService
{
    private string $apiKey;
    private string $model;
    private string $baseUrl;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private DelayPredictionRepository $predictionRepository,
        private DelayHistoryRepository $historyRepository,
        private TripRepository $tripRepository,
        private HttpClientInterface $httpClient,
        string $openaiApiKey = '',
        string $openaiModel = 'gpt-4',
        string $openaiBaseUrl = 'https://api.openai.com/v1'
    ) {
        $this->apiKey = $openaiApiKey;
        $this->model = $openaiModel;
        $this->baseUrl = $openaiBaseUrl;
    }

    /**
     * Predict delay for a trip
     */
    public function predictDelay(Trip $trip): DelayPrediction
    {
        // Get historical data
        $history = $this->historyRepository->findByTrip($trip);
        $recentPredictions = $this->predictionRepository->findByTrip($trip);

        // Analyze factors
        $factors = $this->analyzeFactors($trip, $history);

        // Get AI prediction
        $aiPrediction = $this->getAIPrediction($trip, $factors, $history);

        // Create prediction
        $prediction = new DelayPrediction();
        $prediction->setTrip($trip);
        $prediction->setPredictedDelayMinutes($aiPrediction['delay_minutes']);
        $prediction->setConfidenceScore((string) $aiPrediction['confidence']);
        $prediction->setRiskLevel($aiPrediction['risk_level']);
        $prediction->setFactors($factors);
        $prediction->setReasoning($aiPrediction['reasoning']);
        $prediction->setPredictedAt(new \DateTimeImmutable());

        $this->predictionRepository->save($prediction, true);

        return $prediction;
    }

    /**
     * Analyze factors affecting delay
     */
    private function analyzeFactors(Trip $trip, array $history): array
    {
        $factors = [];

        // Time of day factor
        $hour = (int) $trip->getDepartureTime()->format('H');
        if ($hour >= 7 && $hour <= 9) {
            $factors['time_of_day'] = ['type' => 'morning_rush', 'impact' => 'high'];
        } elseif ($hour >= 16 && $hour <= 19) {
            $factors['time_of_day'] = ['type' => 'evening_rush', 'impact' => 'high'];
        } else {
            $factors['time_of_day'] = ['type' => 'normal', 'impact' => 'low'];
        }

        // Day of week factor
        $dayOfWeek = (int) $trip->getDepartureTime()->format('N');
        if ($dayOfWeek >= 6) {
            $factors['day_of_week'] = ['type' => 'weekend', 'impact' => 'medium'];
        } else {
            $factors['day_of_week'] = ['type' => 'weekday', 'impact' => 'low'];
        }

        // Historical delay factor
        $avgDelay = $this->historyRepository->getAverageDelayByTrip($trip);
        if ($avgDelay > 30) {
            $factors['historical_delay'] = ['type' => 'high', 'impact' => 'high', 'average' => $avgDelay];
        } elseif ($avgDelay > 15) {
            $factors['historical_delay'] = ['type' => 'medium', 'impact' => 'medium', 'average' => $avgDelay];
        } else {
            $factors['historical_delay'] = ['type' => 'low', 'impact' => 'low', 'average' => $avgDelay];
        }

        // Distance factor
        $distance = $trip->getDistance();
        if ($distance > 500) {
            $factors['distance'] = ['type' => 'long', 'impact' => 'high', 'km' => $distance];
        } elseif ($distance > 200) {
            $factors['distance'] = ['type' => 'medium', 'impact' => 'medium', 'km' => $distance];
        } else {
            $factors['distance'] = ['type' => 'short', 'impact' => 'low', 'km' => $distance];
        }

        // Season factor
        $month = (int) $trip->getDepartureTime()->format('n');
        if (in_array($month, [7, 8, 12])) {
            $factors['season'] = ['type' => 'peak', 'impact' => 'high'];
        } elseif (in_array($month, [3, 4, 5, 9, 10, 11])) {
            $factors['season'] = ['type' => 'shoulder', 'impact' => 'medium'];
        } else {
            $factors['season'] = ['type' => 'off_peak', 'impact' => 'low'];
        }

        return $factors;
    }

    /**
     * Get AI prediction
     */
    private function getAIPrediction(Trip $trip, array $factors, array $history): array
    {
        if (empty($this->apiKey)) {
            return $this->getFallbackPrediction($trip, $factors);
        }

        try {
            $prompt = $this->buildPredictionPrompt($trip, $factors, $history);

            $response = $this->httpClient->request('POST', $this->baseUrl . '/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $this->model,
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are a transport delay prediction expert. Analyze the trip data and predict potential delays.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'max_tokens' => 500,
                    'temperature' => 0.3,
                ],
            ]);

            $data = $response->toArray(false);
            $content = $data['choices'][0]['message']['content'] ?? '';

            return $this->parseAIResponse($content);
        } catch (\Exception $e) {
            return $this->getFallbackPrediction($trip, $factors);
        }
    }

    /**
     * Build prediction prompt
     */
    private function buildPredictionPrompt(Trip $trip, array $factors, array $history): string
    {
        $historyText = '';
        if (!empty($history)) {
            $historyText = "\nHistorical delays:\n";
            foreach (array_slice($history, 0, 10) as $record) {
                $historyText .= sprintf(
                    "- %s: %d minutes delay (reason: %s)\n",
                    $record->getOccurredAt()->format('Y-m-d'),
                    $record->getDelayMinutes(),
                    $record->getReason() ?? 'unknown'
                );
            }
        }

        return sprintf(
            "Predict delay for this trip:\n\n" .
            "Trip Details:\n" .
            "- Route: %s to %s\n" .
            "- Departure: %s\n" .
            "- Distance: %s km\n" .
            "- Vehicle: %s\n\n" .
            "Factors:\n%s\n\n" .
            "%s\n\n" .
            "Provide prediction in JSON format:\n" .
            "{\"delay_minutes\": <number>, \"confidence\": <0-100>, \"risk_level\": \"low|medium|high|critical\", \"reasoning\": \"<explanation>\"}",
            $trip->getDepartureCity(),
            $trip->getArrivalCity(),
            $trip->getDepartureTime()->format('Y-m-d H:i'),
            $trip->getDistance() ?? 'unknown',
            $trip->getVehicle()->getBrand() . ' ' . $trip->getVehicle()->getModel(),
            json_encode($factors, JSON_PRETTY_PRINT),
            $historyText
        );
    }

    /**
     * Parse AI response
     */
    private function parseAIResponse(string $content): array
    {
        // Try to extract JSON from response
        if (preg_match('/\{.*\}/s', $content, $matches)) {
            $json = json_decode($matches[0], true);
            if ($json) {
                return [
                    'delay_minutes' => $json['delay_minutes'] ?? 0,
                    'confidence' => $json['confidence'] ?? 50,
                    'risk_level' => $json['risk_level'] ?? 'medium',
                    'reasoning' => $json['reasoning'] ?? 'AI prediction',
                ];
            }
        }

        // Fallback parsing
        return [
            'delay_minutes' => 0,
            'confidence' => 50,
            'risk_level' => 'medium',
            'reasoning' => 'Unable to parse AI response',
        ];
    }

    /**
     * Get fallback prediction
     */
    private function getFallbackPrediction(Trip $trip, array $factors): array
    {
        $delayMinutes = 0;
        $confidence = 60;
        $riskLevel = 'low';
        $reasoning = 'Based on historical data and factors analysis.';

        // Calculate based on factors
        if (isset($factors['time_of_day']['impact']) && $factors['time_of_day']['impact'] === 'high') {
            $delayMinutes += 15;
            $riskLevel = 'medium';
        }

        if (isset($factors['historical_delay']['average'])) {
            $delayMinutes += (int) ($factors['historical_delay']['average'] * 0.5);
        }

        if (isset($factors['distance']['impact']) && $factors['distance']['impact'] === 'high') {
            $delayMinutes += 10;
        }

        // Determine risk level
        if ($delayMinutes > 30) {
            $riskLevel = 'high';
            $confidence = 70;
        } elseif ($delayMinutes > 15) {
            $riskLevel = 'medium';
            $confidence = 65;
        }

        return [
            'delay_minutes' => $delayMinutes,
            'confidence' => $confidence,
            'risk_level' => $riskLevel,
            'reasoning' => $reasoning,
        ];
    }

    /**
     * Record actual delay
     */
    public function recordActualDelay(Trip $trip, int $delayMinutes, ?string $reason = null, array $conditions = []): DelayHistory
    {
        $history = new DelayHistory();
        $history->setTrip($trip);
        $history->setDelayMinutes($delayMinutes);
        $history->setReason($reason);
        $history->setConditions($conditions);
        $history->setOccurredAt(new \DateTimeImmutable());

        $this->historyRepository->save($history, true);

        // Update prediction accuracy
        $this->updatePredictionAccuracy($trip, $delayMinutes);

        return $history;
    }

    /**
     * Update prediction accuracy
     */
    private function updatePredictionAccuracy(Trip $trip, int $actualDelay): void
    {
        $prediction = $this->predictionRepository->findLatestByTrip($trip);
        if ($prediction) {
            $prediction->setActualDelayMinutes($actualDelay);
            $prediction->setActualArrivalTime(new \DateTimeImmutable());
            $prediction->calculateAccuracy();
            $this->predictionRepository->save($prediction, true);
        }
    }

    /**
     * Get delay statistics for agency
     */
    public function getDelayStatistics(Agency $agency): array
    {
        $trips = $this->tripRepository->findByAgency($agency->getId());

        $totalTrips = count($trips);
        $totalDelays = 0;
        $totalDelayMinutes = 0;
        $accuratePredictions = 0;
        $totalPredictions = 0;

        foreach ($trips as $trip) {
            $delays = $this->historyRepository->countByTrip($trip);
            $totalDelays += $delays;
            $totalDelayMinutes += $this->historyRepository->getTotalDelayByTrip($trip);

            $predictions = $this->predictionRepository->countByTrip($trip);
            $totalPredictions += $predictions;

            $avgConfidence = $this->predictionRepository->getAverageConfidenceByTrip($trip);
            if ($avgConfidence > 70) {
                $accuratePredictions++;
            }
        }

        return [
            'total_trips' => $totalTrips,
            'total_delays' => $totalDelays,
            'average_delay_minutes' => $totalTrips > 0 ? round($totalDelayMinutes / $totalTrips, 2) : 0,
            'delay_rate' => $totalTrips > 0 ? round(($totalDelays / $totalTrips) * 100, 2) : 0,
            'total_predictions' => $totalPredictions,
            'accurate_predictions' => $accuratePredictions,
            'prediction_accuracy_rate' => $totalPredictions > 0 ? round(($accuratePredictions / $totalPredictions) * 100, 2) : 0,
        ];
    }

    /**
     * Get delay trends
     */
    public function getDelayTrends(Agency $agency, string $period = 'month'): array
    {
        $now = new \DateTimeImmutable();
        $startDate = match ($period) {
            'week' => $now->modify('-1 week'),
            'month' => $now->modify('-1 month'),
            'quarter' => $now->modify('-3 months'),
            'year' => $now->modify('-1 year'),
            default => $now->modify('-1 month'),
        };

        $trips = $this->tripRepository->findByAgency($agency->getId());
        $trends = [];

        foreach ($trips as $trip) {
            $history = $this->historyRepository->findByTripAndDateRange($trip, $startDate, $now);

            $dailyDelays = [];
            foreach ($history as $record) {
                $date = $record->getOccurredAt()->format('Y-m-d');
                $dailyDelays[$date] = ($dailyDelays[$date] ?? 0) + $record->getDelayMinutes();
            }

            $trends[] = [
                'trip_id' => $trip->getId(),
                'route' => $trip->getDepartureCity() . ' → ' . $trip->getArrivalCity(),
                'daily_delays' => $dailyDelays,
                'total_delays' => count($history),
                'average_delay' => $this->historyRepository->getAverageDelayByTrip($trip),
            ];
        }

        return [
            'period' => $period,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $now->format('Y-m-d'),
            'trends' => $trends,
        ];
    }

    /**
     * Get common delay reasons
     */
    public function getCommonDelayReasons(int $limit = 10): array
    {
        return $this->historyRepository->getMostCommonReasons($limit);
    }
}
