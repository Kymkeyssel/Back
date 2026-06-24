<?php

namespace App\Dto;

class GpsTrackingResponse
{
    public function __construct(
        public readonly int $tripId,
        public readonly array $departure,
        public readonly array $arrival,
        public readonly string $status,
        public readonly ?string $departureTime = null,
        public readonly ?string $arrivalTime = null,
    ) {
    }
}
