<?php

namespace App\Service;

use App\Dto\GpsTrackingResponse;
use App\Entity\Trip;
use App\Repository\TripRepository;
use Doctrine\ORM\EntityManagerInterface;

class GpsTrackingService
{
    public function __construct(
        private TripRepository $tripRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function getGpsData(int $tripId): ?GpsTrackingResponse
    {
        $trip = $this->tripRepository->find($tripId);

        if (!$trip) {
            return null;
        }

        $departureLat = $trip->getDepartureLatitude() ?? $this->getCityLatitude($trip->getDepartureCity());
        $departureLng = $trip->getDepartureLongitude() ?? $this->getCityLongitude($trip->getDepartureCity());
        $arrivalLat = $trip->getArrivalLatitude() ?? $this->getCityLatitude($trip->getArrivalCity());
        $arrivalLng = $trip->getArrivalLongitude() ?? $this->getCityLongitude($trip->getArrivalCity());

        return new GpsTrackingResponse(
            tripId: $trip->getId(),
            departure: [
                'name' => $trip->getDepartureCity(),
                'lat' => $departureLat,
                'lng' => $departureLng,
            ],
            arrival: [
                'name' => $trip->getArrivalCity(),
                'lat' => $arrivalLat,
                'lng' => $arrivalLng,
            ],
            status: $trip->getStatus(),
            departureTime: $trip->getDepartureTime()?->format('Y-m-d H:i:s'),
            arrivalTime: $trip->getArrivalTime()?->format('Y-m-d H:i:s'),
        );
    }

    private function getCityLatitude(string $city): float
    {
        return match (strtolower($city)) {
            'yaoundé', 'yaounde' => 3.8480,
            'douala' => 4.0511,
            'bafoussam' => 5.4772,
            'bertoua' => 4.5750,
            'maroua' => 10.5910,
            'kribi' => 2.9400,
            'bamenda' => 5.9590,
            'garoua' => 9.3010,
            'ngaoundéré', 'ngaoundere' => 7.3270,
            default => 3.8480,
        };
    }

    private function getCityLongitude(string $city): float
    {
        return match (strtolower($city)) {
            'yaoundé', 'yaounde' => 11.5021,
            'douala' => 9.7679,
            'bafoussam' => 10.4176,
            'bertoua' => 13.6840,
            'maroua' => 14.3150,
            'kribi' => 9.9100,
            'bamenda' => 10.1460,
            'garoua' => 13.3980,
            'ngaoundéré', 'ngaoundere' => 13.5840,
            default => 11.5021,
        };
    }
}
