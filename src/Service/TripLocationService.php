<?php

namespace App\Service;

use App\Entity\Trip;
use Doctrine\ORM\EntityManagerInterface;

class TripLocationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MercureService $mercureService,
    ) {
    }

    public function updatePosition(Trip $trip, float $latitude, float $longitude): void
    {
        $trip->setCurrentLatitude($latitude);
        $trip->setCurrentLongitude($longitude);

        $conn = $this->entityManager->getConnection();
        $conn->executeStatement(
            'UPDATE trips SET current_location = ST_SetSRID(ST_MakePoint(:lng, :lat), 4326)::geography WHERE id = :id',
            ['lng' => $longitude, 'lat' => $latitude, 'id' => $trip->getId()]
        );

        $this->entityManager->flush();
        $this->mercureService->publishTripLocation($trip->getId(), $latitude, $longitude);
    }
}
