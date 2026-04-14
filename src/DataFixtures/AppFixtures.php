<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\Agency;
use App\Entity\Vehicle;
use App\Entity\Trip;
use App\Entity\Booking;
use App\Entity\TransportMode;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    public function load(ObjectManager $manager): void
    {
        // Create Users
        $admin = new User();
        $admin->setEmail('admin@transcam.cm');
        $admin->setFirstName('Admin');
        $admin->setLastName('TransCam');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'admin123'));
        $manager->persist($admin);

        $user1 = new User();
        $user1->setEmail('john@example.com');
        $user1->setFirstName('John');
        $user1->setLastName('Doe');
        $user1->setPhone('+237612345678');
        $user1->setPassword($this->passwordHasher->hashPassword($user1, 'password123'));
        $manager->persist($user1);

        $user2 = new User();
        $user2->setEmail('alice@example.com');
        $user2->setFirstName('Alice');
        $user2->setLastName('Mouaha');
        $user2->setPhone('+237698765432');
        $user2->setPassword($this->passwordHasher->hashPassword($user2, 'password123'));
        $manager->persist($user2);

        // Create Agencies
        $agency1 = new Agency();
        $agency1->setName('Trans Cameroon');
        $agency1->setSlug('trans-cameroon');
        $agency1->setDescription('Leading transportation company in Cameroon');
        $agency1->setPhone('+237222123456');
        $agency1->setEmail('contact@transcameroon.cm');
        $agency1->setAddress('Rue de la Gare, Douala');
        $agency1->setCity('Douala');
        $agency1->setCountry('Cameroon');
        $agency1->setLatitude(4.0511);
        $agency1->setLongitude(9.7679);
        $agency1->setRating(4.5);
        $agency1->setTotalReviews(150);
        $agency1->setIsVerified(true);
        $agency1->setIsActive(true);
        $agency1->setOwner($admin);
        $manager->persist($agency1);

        $agency2 = new Agency();
        $agency2->setName('Buca Express');
        $agency2->setSlug('buca-express');
        $agency2->setDescription('Fast and reliable bus service');
        $agency2->setPhone('+237222654321');
        $agency2->setEmail('info@bucaexpress.cm');
        $agency2->setAddress('Avenue Kennedy, Yaoundé');
        $agency2->setCity('Yaoundé');
        $agency2->setCountry('Cameroon');
        $agency2->setLatitude(3.8480);
        $agency2->setLongitude(11.5021);
        $agency2->setRating(4.2);
        $agency2->setTotalReviews(89);
        $agency2->setIsVerified(true);
        $agency2->setIsActive(true);
        $agency2->setOwner($admin);
        $manager->persist($agency2);

        // Create Vehicles
        $vehicle1 = new Vehicle();
        $vehicle1->setAgency($agency1);
        $vehicle1->setType('bus');
        $vehicle1->setBrand('Mercedes-Benz');
        $vehicle1->setModel('Sprinter');
        $vehicle1->setPlateNumber('CE-1234-AE');
        $vehicle1->setTotalSeats(50);
        $vehicle1->setAmenities(['wifi', 'ac', 'usb', 'toilet']);
        $vehicle1->setIsActive(true);
        $manager->persist($vehicle1);

        $vehicle2 = new Vehicle();
        $vehicle2->setAgency($agency1);
        $vehicle2->setType('minibus');
        $vehicle2->setBrand('Toyota');
        $vehicle2->setModel('Hiace');
        $vehicle2->setPlateNumber('CE-5678-AE');
        $vehicle2->setTotalSeats(14);
        $vehicle2->setAmenities(['ac', 'music']);
        $vehicle2->setIsActive(true);
        $manager->persist($vehicle2);

        $vehicle3 = new Vehicle();
        $vehicle3->setAgency($agency2);
        $vehicle3->setType('bus');
        $vehicle3->setBrand('Scania');
        $vehicle3->setModel('K360');
        $vehicle3->setPlateNumber('CE-9012-YA');
        $vehicle3->setTotalSeats(60);
        $vehicle3->setAmenities(['wifi', 'ac', 'usb', 'toilet', 'tv']);
        $vehicle3->setIsActive(true);
        $manager->persist($vehicle3);

        // Create Transport Modes
        $busMode = new TransportMode();
        $busMode->setName('Bus');
        $busMode->setCode('BUS');
        $busMode->setDescription('Standard bus service');
        $busMode->setIcon('bus');
        $busMode->setSortOrder(1);
        $manager->persist($busMode);

        $minibusMode = new TransportMode();
        $minibusMode->setName('Minibus');
        $minibusMode->setCode('MINIBUS');
        $minibusMode->setDescription('Small van service');
        $minibusMode->setIcon('van');
        $minibusMode->setSortOrder(2);
        $manager->persist($minibusMode);

        $taxiMode = new TransportMode();
        $taxiMode->setName('Taxi');
        $taxiMode->setCode('TAXI');
        $taxiMode->setDescription('Shared taxi');
        $taxiMode->setIcon('car');
        $taxiMode->setSortOrder(3);
        $manager->persist($taxiMode);

        $manager->flush();

        // Create Trips
        $trip1 = new Trip();
        $trip1->setAgency($agency1);
        $trip1->setVehicle($vehicle1);
        $trip1->setTransportMode($busMode);
        $trip1->setDepartureCity('Douala');
        $trip1->setArrivalCity('Yaoundé');
        $trip1->setDepartureAddress('Gare Routière de Douala');
        $trip1->setArrivalAddress('Gare Routière de Yaoundé');
        $trip1->setDepartureTime(new \DateTimeImmutable('2026-04-15 06:00:00'));
        $trip1->setArrivalTime(new \DateTimeImmutable('2026-04-15 09:00:00'));
        $trip1->setPrice(3500);
        $trip1->setAvailableSeats(45);
        $trip1->setTotalSeats(50);
        $trip1->setStatus('scheduled');
        $trip1->setDistance(230);
        $trip1->setDuration(180);
        $manager->persist($trip1);

        $trip2 = new Trip();
        $trip2->setAgency($agency1);
        $trip2->setVehicle($vehicle2);
        $trip2->setTransportMode($minibusMode);
        $trip2->setDepartureCity('Douala');
        $trip2->setArrivalCity('Bafoussam');
        $trip2->setDepartureAddress('Bekoko');
        $trip2->setArrivalAddress('Gare de Bafoussam');
        $trip2->setDepartureTime(new \DateTimeImmutable('2026-04-15 14:00:00'));
        $trip2->setArrivalTime(new \DateTimeImmutable('2026-04-15 17:00:00'));
        $trip2->setPrice(2500);
        $trip2->setAvailableSeats(10);
        $trip2->setTotalSeats(14);
        $trip2->setStatus('scheduled');
        $trip2->setDistance(180);
        $trip2->setDuration(180);
        $manager->persist($trip2);

        $trip3 = new Trip();
        $trip3->setAgency($agency2);
        $trip3->setVehicle($vehicle3);
        $trip3->setTransportMode($busMode);
        $trip3->setDepartureCity('Yaoundé');
        $trip3->setArrivalCity('Douala');
        $trip3->setDepartureAddress('Yaoundé Centre');
        $trip3->setArrivalAddress('Douala Bassa');
        $trip3->setDepartureTime(new \DateTimeImmutable('2026-04-16 05:30:00'));
        $trip3->setArrivalTime(new \DateTimeImmutable('2026-04-16 08:30:00'));
        $trip3->setPrice(3000);
        $trip3->setAvailableSeats(55);
        $trip3->setTotalSeats(60);
        $trip3->setStatus('scheduled');
        $trip3->setDistance(230);
        $trip3->setDuration(180);
        $manager->persist($trip3);

        $trip4 = new Trip();
        $trip4->setAgency($agency1);
        $trip4->setVehicle($vehicle1);
        $trip4->setTransportMode($busMode);
        $trip4->setDepartureCity('Douala');
        $trip4->setArrivalCity('Kribi');
        $trip4->setDepartureAddress('Doualapk');
        $trip4->setArrivalAddress('Kribi Centre');
        $trip4->setDepartureTime(new \DateTimeImmutable('2026-04-17 07:00:00'));
        $trip4->setArrivalTime(new \DateTimeImmutable('2026-04-17 10:00:00'));
        $trip4->setPrice(4000);
        $trip4->setAvailableSeats(50);
        $trip4->setTotalSeats(50);
        $trip4->setStatus('scheduled');
        $trip4->setDistance(150);
        $trip4->setDuration(180);
        $manager->persist($trip4);

        // Create Bookings
        $booking1 = new Booking();
        $booking1->setUser($user1);
        $booking1->setTrip($trip1);
        $booking1->setReference('BK-001-2026');
        $booking1->setStatus('confirmed');
        $booking1->setTotalPrice(3500);
        $booking1->setNumberOfSeats(1);
        $booking1->setSeatNumbers(['A1']);
        $booking1->setPassengers([['name' => 'John Doe', 'phone' => '+237612345678']]);
        $manager->persist($booking1);

        $booking2 = new Booking();
        $booking2->setUser($user2);
        $booking2->setTrip($trip3);
        $booking2->setReference('BK-002-2026');
        $booking2->setStatus('pending');
        $booking2->setTotalPrice(3000);
        $booking2->setNumberOfSeats(2);
        $booking2->setSeatNumbers(['B5', 'B6']);
        $booking2->setPassengers([['name' => 'Alice Mouaha', 'phone' => '+237698765432'], ['name' => 'Marie Mouaha', 'phone' => '+237698765433']]);
        $manager->persist($booking2);

        $manager->flush();

        // Add more trips for the next few days
        for ($i = 2; $i <= 10; $i++) {
            $trip = new Trip();
            $trip->setAgency($agency1);
            $trip->setVehicle($vehicle1);
            $trip->setTransportMode($busMode);
            $trip->setDepartureCity('Douala');
            $trip->setArrivalCity('Yaoundé');
            $trip->setDepartureAddress('Gare Routière de Douala');
            $trip->setArrivalAddress('Gare Routière de Yaoundé');
            $day = str_pad($i, 2, '0', STR_PAD_LEFT);
            $date = new \DateTimeImmutable("2026-04-$day 06:00:00");
            $trip->setDepartureTime($date);
            $arrival = (clone $date)->modify('+3 hours');
            $trip->setArrivalTime($arrival);
            $trip->setPrice(3500);
            $trip->setAvailableSeats(rand(20, 50));
            $trip->setTotalSeats(50);
            $trip->setStatus('scheduled');
            $trip->setDistance(230);
            $trip->setDuration(180);
            $manager->persist($trip);
        }

        $manager->flush();
    }
}
