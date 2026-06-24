<?php

namespace App\Tests\Entity;

use App\Entity\Booking;
use App\Entity\User;
use App\Entity\Trip;
use App\Entity\Payment;
use App\Entity\Ticket;
use PHPUnit\Framework\TestCase;

class BookingTest extends TestCase
{
    private Booking $booking;

    protected function setUp(): void
    {
        $this->booking = new Booking();
    }

    public function testBookingCanBeCreated(): void
    {
        $this->assertInstanceOf(Booking::class, $this->booking);
    }

    public function testBookingHasDefaultStatusPending(): void
    {
        $this->assertEquals('pending', $this->booking->getStatus());
    }

    public function testBookingReferenceCanBeSet(): void
    {
        $this->booking->setReference('BK-001-2026');
        $this->assertEquals('BK-001-2026', $this->booking->getReference());
    }

    public function testBookingStatusCanBeChangedToConfirmed(): void
    {
        $this->booking->setStatus('confirmed');
        $this->assertEquals('confirmed', $this->booking->getStatus());
    }

    public function testBookingStatusCanBeChangedToCancelled(): void
    {
        $this->booking->setStatus('cancelled');
        $this->assertEquals('cancelled', $this->booking->getStatus());
    }

    public function testBookingStatusCanBeChangedToCompleted(): void
    {
        $this->booking->setStatus('completed');
        $this->assertEquals('completed', $this->booking->getStatus());
    }

    public function testBookingTotalPriceCanBeSet(): void
    {
        $this->booking->setTotalPrice('3500');
        $this->assertEquals('3500', $this->booking->getTotalPrice());
    }

    public function testBookingNumberOfSeatsCanBeSet(): void
    {
        $this->booking->setNumberOfSeats(2);
        $this->assertEquals(2, $this->booking->getNumberOfSeats());
    }

    public function testBookingSeatNumbersDefaultToEmptyArray(): void
    {
        $this->assertEquals([], $this->booking->getSeatNumbers());
    }

    public function testBookingSeatNumbersCanBeSet(): void
    {
        $this->booking->setSeatNumbers(['A1', 'A2']);
        $this->assertEquals(['A1', 'A2'], $this->booking->getSeatNumbers());
    }

    public function testBookingPassengersDefaultToEmptyArray(): void
    {
        $this->assertEquals([], $this->booking->getPassengers());
    }

    public function testBookingPassengersCanBeSet(): void
    {
        $passengers = [
            ['name' => 'John Doe', 'phone' => '+237612345678'],
            ['name' => 'Jane Doe', 'phone' => '+237612345679']
        ];
        $this->booking->setPassengers($passengers);
        $this->assertEquals($passengers, $this->booking->getPassengers());
    }

    public function testBookingSpecialRequestsCanBeNull(): void
    {
        $this->assertNull($this->booking->getSpecialRequests());
    }

    public function testBookingSpecialRequestsCanBeSet(): void
    {
        $this->booking->setSpecialRequests('Wheelchair access needed');
        $this->assertEquals('Wheelchair access needed', $this->booking->getSpecialRequests());
    }

    public function testBookingCreatedAtIsSet(): void
    {
        $this->assertNotNull($this->booking->getCreatedAt());
    }

    public function testBookingUpdatedAtIsSet(): void
    {
        $this->assertNotNull($this->booking->getUpdatedAt());
    }

    public function testBookingCancelledAtCanBeNull(): void
    {
        $this->assertNull($this->booking->getCancelledAt());
    }

    public function testBookingCancelledAtCanBeSet(): void
    {
        $cancelledAt = new \DateTimeImmutable();
        $this->booking->setCancelledAt($cancelledAt);
        $this->assertEquals($cancelledAt, $this->booking->getCancelledAt());
    }

    public function testBookingUserCanBeSet(): void
    {
        $user = new User();
        $this->booking->setUser($user);
        $this->assertEquals($user, $this->booking->getUser());
    }

    public function testBookingTripCanBeSet(): void
    {
        $trip = new Trip();
        $this->booking->setTrip($trip);
        $this->assertEquals($trip, $this->booking->getTrip());
    }

    public function testBookingPaymentsCollectionIsInitialized(): void
    {
        $this->assertInstanceOf(\Doctrine\Common\Collections\Collection::class, $this->booking->getPayments());
    }

    public function testBookingTicketsCollectionIsInitialized(): void
    {
        $this->assertInstanceOf(\Doctrine\Common\Collections\Collection::class, $this->booking->getTickets());
    }
}
