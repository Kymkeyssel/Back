<?php

namespace App\Tests\Entity;

use App\Entity\Trip;
use App\Entity\Agency;
use App\Entity\Vehicle;
use App\Entity\TransportMode;
use PHPUnit\Framework\TestCase;

class TripTest extends TestCase
{
    private Trip $trip;

    protected function setUp(): void
    {
        $this->trip = new Trip();
    }

    public function testTripCanBeCreated(): void
    {
        $this->assertInstanceOf(Trip::class, $this->trip);
    }

    public function testTripHasDefaultStatusScheduled(): void
    {
        $this->assertEquals('scheduled', $this->trip->getStatus());
    }

    public function testTripDepartureCityCanBeSet(): void
    {
        $this->trip->setDepartureCity('Douala');
        $this->assertEquals('Douala', $this->trip->getDepartureCity());
    }

    public function testTripArrivalCityCanBeSet(): void
    {
        $this->trip->setArrivalCity('Yaoundé');
        $this->assertEquals('Yaoundé', $this->trip->getArrivalCity());
    }

    public function testTripDepartureAddressCanBeSet(): void
    {
        $this->trip->setDepartureAddress('Gare Routière de Douala');
        $this->assertEquals('Gare Routière de Douala', $this->trip->getDepartureAddress());
    }

    public function testTripArrivalAddressCanBeSet(): void
    {
        $this->trip->setArrivalAddress('Gare Routière de Yaoundé');
        $this->assertEquals('Gare Routière de Yaoundé', $this->trip->getArrivalAddress());
    }

    public function testTripPriceCanBeSet(): void
    {
        $this->trip->setPrice(3500);
        $this->assertEquals('3500', $this->trip->getPrice());
    }

    public function testTripAvailableSeatsCanBeSet(): void
    {
        $this->trip->setAvailableSeats(45);
        $this->assertEquals(45, $this->trip->getAvailableSeats());
    }

    public function testTripTotalSeatsCanBeSet(): void
    {
        $this->trip->setTotalSeats(50);
        $this->assertEquals(50, $this->trip->getTotalSeats());
    }

    public function testTripStatusCanBeChangedToInProgress(): void
    {
        $this->trip->setStatus('in_progress');
        $this->assertEquals('in_progress', $this->trip->getStatus());
    }

    public function testTripStatusCanBeChangedToCompleted(): void
    {
        $this->trip->setStatus('completed');
        $this->assertEquals('completed', $this->trip->getStatus());
    }

    public function testTripStatusCanBeChangedToCancelled(): void
    {
        $this->trip->setStatus('cancelled');
        $this->assertEquals('cancelled', $this->trip->getStatus());
    }

    public function testTripDistanceCanBeSet(): void
    {
        $this->trip->setDistance(230.5);
        $this->assertEquals(230.5, $this->trip->getDistance());
    }

    public function testTripDurationCanBeSet(): void
    {
        $this->trip->setDuration(180);
        $this->assertEquals(180, $this->trip->getDuration());
    }

    public function testTripDepartureTimeCanBeSet(): void
    {
        $departureTime = new \DateTimeImmutable('2026-04-15 06:00:00');
        $this->trip->setDepartureTime($departureTime);
        $this->assertEquals($departureTime, $this->trip->getDepartureTime());
    }

    public function testTripArrivalTimeCanBeSet(): void
    {
        $arrivalTime = new \DateTimeImmutable('2026-04-15 09:00:00');
        $this->trip->setArrivalTime($arrivalTime);
        $this->assertEquals($arrivalTime, $this->trip->getArrivalTime());
    }

    public function testTripAgencyCanBeSet(): void
    {
        $agency = new Agency();
        $this->trip->setAgency($agency);
        $this->assertEquals($agency, $this->trip->getAgency());
    }

    public function testTripVehicleCanBeSet(): void
    {
        $vehicle = new Vehicle();
        $this->trip->setVehicle($vehicle);
        $this->assertEquals($vehicle, $this->trip->getVehicle());
    }

    public function testTripTransportModeCanBeSet(): void
    {
        $transportMode = new TransportMode();
        $this->trip->setTransportMode($transportMode);
        $this->assertEquals($transportMode, $this->trip->getTransportMode());
    }

    public function testTripCreatedAtIsSet(): void
    {
        $this->assertNotNull($this->trip->getCreatedAt());
    }

    public function testTripUpdatedAtIsSet(): void
    {
        $this->assertNotNull($this->trip->getUpdatedAt());
    }

    public function testTripBookingsCollectionIsInitialized(): void
    {
        $this->assertInstanceOf(\Doctrine\Common\Collections\Collection::class, $this->trip->getBookings());
    }
}
