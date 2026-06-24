<?php

namespace App\DataFixtures;

use App\Entity\Agency;
use App\Entity\Booking;
use App\Entity\Notification;
use App\Entity\Payment;
use App\Entity\Review;
use App\Entity\TransportMode;
use App\Entity\Trip;
use App\Entity\User;
use App\Entity\Vehicle;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    public function load(ObjectManager $m): void
    {
        // ── 1. USERS ─────────────────────────────────────
        $users = $this->createUsers($m);
        // ── 2. TRANSPORT MODES ────────────────────────────
        [$busMode, $carpoolMode] = $this->createTransportModes($m);
        // ── 3. AGENCIES ───────────────────────────────────
        $agencies = $this->createAgencies($m, $users);
        // ── 4. VEHICLES ───────────────────────────────────
        $vehicles = $this->createVehicles($m, $agencies, $users);
        // ── 5. TRIPS ──────────────────────────────────────
        $trips = $this->createTrips($m, $agencies, $vehicles, $busMode, $carpoolMode);
        // ── 6. BOOKINGS ───────────────────────────────────
        $bookings = $this->createBookings($m, $users, $trips, $agencies);
        // ── 7. PAYMENTS ───────────────────────────────────
        $this->createPayments($m, $users, $bookings);
        // ── 8. REVIEWS ────────────────────────────────────
        $this->createReviews($m, $users, $agencies, $bookings);
        // ── 9. NOTIFICATIONS ──────────────────────────────
        $this->createNotifications($m, $users, $bookings);

        $m->flush();
    }

    private function createUsers(ObjectManager $m): array
    {
        $items = [
            ['superadmin@one4all.cm', 'Jean-Claude', 'Biya', ['ROLE_SUPER_ADMIN', 'ROLE_ADMIN'], 'password123', '+237691234501', true],
            ['admin@one4all.cm', 'Marie', 'Nkolo', ['ROLE_ADMIN'], 'password123', '+237691234502', true],
            ['paul@bucavoyages.cm', 'Paul', 'Biya Jr', ['ROLE_AGENCY'], 'password123', '+237691234503', true],
            ['bruno@garantieexpress.cm', 'Bruno', 'Foche', ['ROLE_AGENCY'], 'password123', '+237691234504', true],
            ['carole@finex.cm', 'Carole', 'Bilong', ['ROLE_AGENCY'], 'password123', '+237691234505', true],
            ['herve@confortplus.cm', 'Hervé', 'Tchamba', ['ROLE_AGENCY'], 'password123', '+237691234516', true],
            ['samuel@bucavoyages.cm', 'Samuel', 'Eto\'o', ['ROLE_AGENCY'], 'password123', '+237691234506', true],
            ['roger@bucavoyages.cm', 'Roger', 'Milla', ['ROLE_AGENCY'], 'password123', '+237691234507', true],
            ['patrick@garantie.cm', 'Patrick', 'Mboma', ['ROLE_AGENCY'], 'password123', '+237691234508', true],
            ['rigobert@finex.cm', 'Rigobert', 'Song', ['ROLE_AGENCY'], 'password123', '+237691234509', true],
            ['vincent@confortplus.cm', 'Vincent', 'Aboubakar', ['ROLE_AGENCY'], 'password123', '+237691234510', true],
            ['alice.ngo@gmail.com', 'Alice', 'Ngo', ['ROLE_CLIENT'], 'password123', '+237691234511', true],
            ['david.mvogo@yahoo.fr', 'David', 'Mvogo', ['ROLE_CLIENT'], 'password123', '+237691234512', true],
            ['esther.mbah@outlook.com', 'Esther', 'Mbah', ['ROLE_CLIENT'], 'password123', '+237691234513', true],
            ['francois.kamga@gmail.com', 'François', 'Kamga', ['ROLE_CLIENT'], 'password123', '+237691234514', true],
            ['grace.nganou@gmail.com', 'Grâce', 'Nganou', ['ROLE_CLIENT'], 'password123', '+237691234515', true],
            ['jeanne.metogo@gmail.com', 'Jeanne', 'Metogo', ['ROLE_CLIENT'], 'password123', '+237691234517', true],
            ['kevin.assomo@gmail.com', 'Kévin', 'Assomo', ['ROLE_CLIENT'], 'password123', '+237691234518', true],
            ['laurence.beyala@one4all.cm', 'Laurence', 'Beyala', ['ROLE_CLIENT'], 'password123', '+237691234519', true],
            ['michel.tagny@yahoo.com', 'Michel', 'Tagny', ['ROLE_CLIENT'], 'password123', '+237691234520', true],
        ];
        $entities = [];
        foreach ($items as $i) {
            $u = new User();
            $u->setEmail($i[0]);
            $u->setFirstName($i[1]);
            $u->setLastName($i[2]);
            $u->setRoles($i[3]);
            $u->setPassword($this->passwordHasher->hashPassword($u, $i[4]));
            $u->setPhone($i[5]);
            $u->setIsVerified($i[6]);
            $u->setPreferredLanguage('fr');
            $m->persist($u);
            $entities[] = $u;
        }
        return $entities;
    }

    private function createTransportModes(ObjectManager $m): array
    {
        $bus = new TransportMode();
        $bus->setName('Bus Interurbain');
        $bus->setCode('INTERCITY_BUS');
        $bus->setDescription('Lignes régulières de bus entre les grandes villes');
        $bus->setIcon('bus');
        $bus->setSortOrder(1);
        $m->persist($bus);

        $carpool = new TransportMode();
        $carpool->setName('Covoiturage');
        $carpool->setCode('CARPOOL');
        $carpool->setDescription('Trajets partagés en voiture entre particuliers');
        $carpool->setIcon('car');
        $carpool->setSortOrder(2);
        $m->persist($carpool);

        return [$bus, $carpool];
    }

    private function createAgencies(ObjectManager $m, array $u): array
    {
        $items = [
            ['Buca Voyages', 'buca-voyages', 'Leader du transport interurbain au Cameroun', '+237233456101', 'contact@bucavoyages.cm', 'Mvan, Route de l\'Aéroport', 'Yaoundé', 3.8612, 11.5210, 4.2, 28, true, 2],
            ['Garantie Express', 'garantie-express', 'Référence du transport vers l\'Ouest', '+237233456102', 'info@garantieexpress.cm', 'Bessengue, Rue Principale', 'Douala', 4.0511, 9.7679, 3.8, 15, true, 3],
            ['Finex Voyages', 'finex-voyages', 'Transport de qualité vers l\'Est et l\'Adamaoua', '+237233456103', 'contact@finexvoyages.cm', 'Mokolo, Avenue Kennedy', 'Yaoundé', 3.8742, 11.5083, 4.5, 12, true, 4],
            ['Confort Plus', 'confort-plus', 'Agence premium, bus climatisés wifi et rafraîchissements', '+237233456104', 'voyage@confortplus.cm', 'Bonapriso, Rue Gallieni', 'Douala', 4.0435, 9.6872, 4.0, 8, false, 5],
            ['Général Transport', 'general-transport', 'Solutions abordables pour toutes les destinations du Grand Nord', '+237233456105', 'info@generaltransport.cm', 'Centre Ville, Rue du Commerce', 'Bafoussam', 5.4772, 10.4176, 2.5, 4, false, 3],
        ];
        $entities = [];
        foreach ($items as $i) {
            $a = new Agency();
            $a->setName($i[0]);
            $a->setSlug($i[1]);
            $a->setDescription($i[2]);
            $a->setPhone($i[3]);
            $a->setEmail($i[4]);
            $a->setAddress($i[5]);
            $a->setCity($i[6]);
            $a->setCountry('Cameroon');
            $a->setLatitude($i[7]);
            $a->setLongitude($i[8]);
            $a->setRating($i[9]);
            $a->setTotalReviews($i[10]);
            $a->setIsVerified($i[11]);
            $a->setIsActive(true);
            $a->setOwner($u[$i[12]]);
            $m->persist($a);
            $entities[] = $a;
        }
        return $entities;
    }

    private function createVehicles(ObjectManager $m, array $a, array $u): array
    {
        $items = [
            // [agency_idx, driver_idx, type, brand, model, plate, seats, amenities]
            [0, 6, 'bus', 'Mercedes-Benz', 'Sprinter 516', 'LT 4521 CM', 18, ['climatisation', 'wifi', 'prise_usb']],
            [0, 7, 'bus', 'Toyota', 'Hiace', 'LT 0829 CM', 15, ['climatisation', 'wifi', 'prise_usb']],
            [0, null, 'minibus', 'Toyota', 'Coaster', 'CE 1234 CM', 30, ['climatisation', 'wifi', 'prise_usb', 'television']],
            [1, 8, 'bus', 'Mercedes-Benz', 'Sprinter 515', 'LT 3344 CM', 18, ['climatisation', 'wifi', 'prise_usb']],
            [1, null, 'minibus', 'Toyota', 'Hiace', 'LT 5566 CM', 15, ['climatisation', 'prise_usb']],
            [1, null, 'bus', 'King Long', '6118', 'LT 7788 CM', 35, ['climatisation', 'wifi', 'television']],
            [2, 9, 'bus', 'Scania', 'Irizar i6', 'LT 9900 CM', 40, ['climatisation', 'wifi', 'prise_usb', 'toilettes', 'television', 'cafe']],
            [2, null, 'minibus', 'Toyota', 'Hiace', 'CE 2468 CM', 15, ['climatisation', 'wifi']],
            [2, null, 'bus', 'Mercedes-Benz', 'Tourismo', 'CE 1357 CM', 45, ['climatisation', 'wifi', 'prise_usb', 'toilettes', 'television']],
            [3, 10, 'minibus', 'Toyota', 'Hiace', 'LT 8642 CM', 12, ['climatisation', 'wifi', 'prise_usb']],
            [3, null, 'bus', 'Mercedes-Benz', 'Sprinter 516', 'LT 9753 CM', 18, ['climatisation', 'wifi', 'prise_usb', 'toilettes']],
            [4, null, 'bus', 'Toyota', 'Coaster', 'LT 1122 CM', 30, ['climatisation']],
        ];
        $entities = [];
        foreach ($items as $i) {
            $v = new Vehicle();
            $v->setAgency($a[$i[0]]);
            if ($i[1] !== null) $v->setDriver($u[$i[1]]);
            $v->setType($i[2]);
            $v->setBrand($i[3]);
            $v->setModel($i[4]);
            $v->setPlateNumber($i[5]);
            $v->setTotalSeats($i[6]);
            $v->setAmenities($i[7]);
            $v->setIsActive(true);
            $m->persist($v);
            $entities[] = $v;
        }
        return $entities;
    }

    private function createTrips(ObjectManager $m, array $a, array $v, TransportMode $busMode, TransportMode $carpoolMode): array
    {
        $now = new \DateTimeImmutable('now');
        $routes = [
            // [departure, arrival, distance_km, duration_min, price]
            ['Yaoundé', 'Douala', 250, 240, 3500],
            ['Douala', 'Yaoundé', 250, 240, 3500],
            ['Yaoundé', 'Bafoussam', 310, 270, 4000],
            ['Bafoussam', 'Yaoundé', 310, 270, 4000],
            ['Douala', 'Bafoussam', 190, 210, 4000],
            ['Bafoussam', 'Douala', 190, 210, 4000],
            ['Yaoundé', 'Bertoua', 350, 300, 5500],
            ['Bertoua', 'Yaoundé', 350, 300, 5500],
            ['Douala', 'Bamenda', 300, 300, 5000],
            ['Yaoundé', 'Ngaoundéré', 650, 600, 8500],
            ['Douala', 'Kribi', 160, 180, 4500],
            ['Bafoussam', 'Maroua', 800, 840, 12000],
        ];

        $entities = [];
        $tripIdx = 0;

        // Past completed trips (days: 14, 10, 7, 6, 5, 3, 2, 1)
        foreach ([14, 10, 7, 6, 5, 3, 2, 1] as $daysAgo) {
            $route = $routes[$tripIdx % count($routes)];
            $agency = $a[$tripIdx % 3];
            $vehicle = $v[$tripIdx % 9];
            $hour = 6 + ($tripIdx % 6);
            $departure = $now->modify("-{$daysAgo} days")->setTime($hour, 0, 0);
            $arrival = $departure->modify('+' . $route[3] . ' minutes');
            $t = new Trip();
            $t->setAgency($agency);
            $t->setVehicle($vehicle);
            $t->setTransportMode($busMode);
            $t->setDepartureCity($route[0]);
            $t->setArrivalCity($route[1]);
            $t->setDepartureAddress('Gare de ' . $route[0]);
            $t->setArrivalAddress('Gare de ' . $route[1]);
            $t->setDepartureTime($departure);
            $t->setArrivalTime($arrival);
            $t->setPrice((string) $route[4]);
            $t->setAvailableSeats(0);
            $t->setTotalSeats($vehicle->getTotalSeats());
            $t->setStatus('completed');
            $t->setDistance((float) $route[2]);
            $t->setDuration($route[3]);
            $m->persist($t);
            $entities[] = $t;
            $tripIdx++;
        }

        // Today's trips (in_progress)
        foreach ([6, 14] as $hour) {
            $route = $routes[$tripIdx % count($routes)];
            $agency = $a[$tripIdx % 3];
            $vehicle = $v[$tripIdx % 9];
            $departure = $now->setTime($hour, 0, 0);
            $arrival = $departure->modify('+' . $route[3] . ' minutes');
            $t = new Trip();
            $t->setAgency($agency);
            $t->setVehicle($vehicle);
            $t->setTransportMode($busMode);
            $t->setDepartureCity($route[0]);
            $t->setArrivalCity($route[1]);
            $t->setDepartureAddress('Gare de ' . $route[0]);
            $t->setArrivalAddress('Gare de ' . $route[1]);
            $t->setDepartureTime($departure);
            $t->setArrivalTime($arrival);
            $t->setPrice((string) $route[4]);
            $half = intdiv($vehicle->getTotalSeats(), 2);
            $t->setAvailableSeats(random_int(1, $half));
            $t->setTotalSeats($vehicle->getTotalSeats());
            $t->setStatus('in_progress');
            $t->setDistance((float) $route[2]);
            $t->setDuration($route[3]);
            $t->setCurrentLatitude(3.8 + mt_rand(-50, 50) / 100);
            $t->setCurrentLongitude(10.5 + mt_rand(-50, 50) / 100);
            $m->persist($t);
            $entities[] = $t;
            $tripIdx++;
        }

        // Future scheduled trips
        foreach ([1, 2, 3, 5, 7, 10] as $daysAhead) {
            for ($h = 6; $h <= 16; $h += 8) {
                $route = $routes[$tripIdx % count($routes)];
                $agency = $a[$tripIdx % 5];
                $vehicle = $v[$tripIdx % count($v)];
                $departure = $now->modify("+{$daysAhead} days")->setTime($h, 0, 0);
                $arrival = $departure->modify('+' . $route[3] . ' minutes');
                $t = new Trip();
                $t->setAgency($agency);
                $t->setVehicle($vehicle);
                $t->setTransportMode($busMode);
                $t->setDepartureCity($route[0]);
                $t->setArrivalCity($route[1]);
                $t->setDepartureAddress('Gare de ' . $route[0]);
                $t->setArrivalAddress('Gare de ' . $route[1]);
                $t->setDepartureTime($departure);
                $t->setArrivalTime($arrival);
                $t->setPrice((string) $route[4]);
                $seats = $vehicle->getTotalSeats();
                $t->setAvailableSeats(random_int(max(1, intdiv($seats, 4)), $seats));
                $t->setTotalSeats($seats);
                $t->setStatus('scheduled');
                $t->setDistance((float) $route[2]);
                $t->setDuration($route[3]);
                $m->persist($t);
                $entities[] = $t;
                $tripIdx++;
            }
        }

        // Carpool trips (future, using carpool mode)
        $carpoolRoutes = [
            ['Yaoundé', 'Douala', 250, 240, 2500],
            ['Douala', 'Bafoussam', 190, 210, 3000],
            ['Yaoundé', 'Bertoua', 350, 300, 4000],
            ['Douala', 'Yaoundé', 250, 240, 2500],
            ['Bafoussam', 'Douala', 190, 210, 3000],
            ['Yaoundé', 'Bafoussam', 310, 270, 3500],
        ];

        foreach ([1, 3, 5] as $daysAhead) {
            $cr = $carpoolRoutes[$tripIdx % count($carpoolRoutes)];
            $agency = $a[$tripIdx % 5];
            $vehicle = $v[$tripIdx % count($v)];
            $departure = $now->modify("+{$daysAhead} days")->setTime(7 + $daysAhead, 30, 0);
            $arrival = $departure->modify('+' . $cr[3] . ' minutes');
            $ct = new Trip();
            $ct->setAgency($agency);
            $ct->setVehicle($vehicle);
            $ct->setTransportMode($carpoolMode);
            $ct->setDepartureCity($cr[0]);
            $ct->setArrivalCity($cr[1]);
            $ct->setDepartureAddress('Rond-point ' . $cr[0]);
            $ct->setArrivalAddress('Marché ' . $cr[1]);
            $ct->setDepartureTime($departure);
            $ct->setArrivalTime($arrival);
            $ct->setPrice((string) $cr[4]);
            $ct->setAvailableSeats(random_int(1, 4));
            $ct->setTotalSeats(4);
            $ct->setStatus('scheduled');
            $ct->setDistance((float) $cr[2]);
            $ct->setDuration($cr[3]);
            $m->persist($ct);
            $entities[] = $ct;
            $tripIdx++;
        }

        return $entities;
    }

    private function createBookings(ObjectManager $m, array $u, array $trips, array $a): array
    {
        $completedTrips = array_filter($trips, fn($t) => $t->getStatus() === 'completed');
        $futureTrips = array_filter($trips, fn($t) => $t->getStatus() !== 'completed');
        $clientIds = [11, 12, 13, 14, 15, 16, 17, 18, 19];
        $entities = [];
        $refNum = 1;

        // Bookings on past completed trips
        foreach ($completedTrips as $i => $trip) {
            if ($i >= 18) break;
            $client = $u[$clientIds[$i % count($clientIds)]];
            $seats = random_int(1, 3);
            $b = new Booking();
            $b->setReference('BK-SEED-' . str_pad((string) $refNum, 3, '0', STR_PAD_LEFT));
            $b->setUser($client);
            $b->setTrip($trip);
            $b->setStatus(random_int(0, 3) > 0 ? 'completed' : 'cancelled');
            $b->setTotalPrice((string) ((int) $trip->getPrice() * $seats));
            $b->setNumberOfSeats($seats);
            $b->setSeatNumbers(array_map(fn($s) => 'A' . $s, range(1, $seats)));
            $b->setPassengers([['name' => $client->getFullName(), 'phone' => $client->getPhone()]]);
            if ($b->getStatus() === 'cancelled') $b->setCancelledAt(new \DateTimeImmutable());
            $m->persist($b);
            $entities[] = $b;
            $refNum++;
        }

        // Bookings on future/in-progress trips
        foreach ($futureTrips as $i => $trip) {
            if ($i >= 15) break;
            $client = $u[$clientIds[$i % count($clientIds)]];
            $seats = random_int(1, 2);
            $b = new Booking();
            $b->setReference('BK-SEED-' . str_pad((string) $refNum, 3, '0', STR_PAD_LEFT));
            $b->setUser($client);
            $b->setTrip($trip);
            $b->setStatus(random_int(0, 2) > 0 ? 'confirmed' : 'pending');
            $b->setTotalPrice((string) ((int) $trip->getPrice() * $seats));
            $b->setNumberOfSeats($seats);
            $b->setSeatNumbers(array_map(fn($s) => 'B' . $s, range(1, $seats)));
            $b->setPassengers([['name' => $client->getFullName(), 'phone' => $client->getPhone()]]);
            $m->persist($b);
            $entities[] = $b;
            $refNum++;
        }

        return $entities;
    }

    private function createPayments(ObjectManager $m, array $u, array $bookings): void
    {
        $methods = ['mtn_momo', 'orange_money'];
        $txnNum = 1;

        foreach ($bookings as $b) {
            if ($b->getStatus() === 'pending') continue;
            $method = $methods[$txnNum % 2];
            $ref = strtoupper(substr($method, 0, 3)) . '-SEED-' . $txnNum;
            $p = new Payment();
            $p->setBooking($b);
            $p->setUser($b->getUser());
            $p->setMethod($method);
            $p->setAmount($b->getTotalPrice());
            $p->setCurrency('XAF');
            $p->setStatus($b->getStatus() === 'cancelled' ? 'refunded' : 'completed');
            $p->setTransactionId('TXN-' . str_pad(dechex($txnNum * 9999), 16, '0', STR_PAD_LEFT));
            $p->setProviderReference($ref);
            if ($p->getStatus() === 'completed') $p->setCompletedAt(new \DateTimeImmutable());
            $m->persist($p);
            $txnNum++;
        }
    }

    private function createReviews(ObjectManager $m, array $u, array $agencies, array $bookings): void
    {
        $comments = [
            5 => ['Excellent service, très satisfait!', 'Trajet parfait, à l\'heure et confortable.', 'Je recommande vivement cette agence.'],
            4 => ['Très bon voyage, légèrement en retard.', 'Confortable et propre. Bon rapport qualité-prix.', 'Service agréable, chauffeur professionnel.'],
            3 => ['Voyage correct sans plus.', 'Peut mieux faire au niveau de la ponctualité.', 'Bus correct mais clim insuffisante.'],
            2 => ['Déçu du service, bus vétuste.', 'Retard important à l\'arrivée.', 'Passable, je m\'attendais à mieux.'],
            1 => ['Très mauvaise expérience, bus en panne.', 'Service médiocre, ne mérite pas une étoile.', 'À éviter, agence non professionnelle.'],
        ];

        $idx = 0;
        foreach ($bookings as $b) {
            if ($b->getStatus() === 'cancelled') continue;
            if ($idx >= 20) break;
            $rating = [5, 5, 4, 4, 3, 2, 5, 4, 1, 5][$idx % 10];
            $agency = $b->getTrip()->getAgency();
            $r = new Review();
            $r->setUser($b->getUser());
            $r->setAgency($agency);
            $r->setBooking($b);
            $r->setRating($rating);
            $r->setComment($comments[$rating][array_rand($comments[$rating])]);
            $r->setIsVerified($rating > 2);
            $m->persist($r);
            $idx++;
        }
    }

    private function createNotifications(ObjectManager $m, array $u, array $bookings): void
    {
        $idx = 0;
        $types = ['booking', 'payment', 'trip', 'system', 'promotion'];
        $titles = [
            'booking' => 'Réservation confirmée',
            'payment' => 'Paiement reçu',
            'trip' => 'Rappel de voyage',
            'system' => 'Information système',
            'promotion' => 'Offre spéciale',
        ];

        foreach ($bookings as $b) {
            if ($idx >= 25) break;
            $type = $types[$idx % count($types)];
            $n = new Notification();
            $n->setUser($b->getUser());
            $n->setType($type);
            $n->setTitle($titles[$type]);
            $n->setMessage("{$titles[$type]} — {$b->getReference()} ({$b->getTrip()->getDepartureCity()} → {$b->getTrip()->getArrivalCity()})");
            $n->setIsRead($idx % 3 > 0);
            $m->persist($n);
            $idx++;
        }

        // Admin notifications
        $admin = $u[0];
        foreach (['Nouvelle agence en attente de vérification', 'Rapport hebdomadaire disponible', 'Mise à jour système effectuée'] as $msg) {
            $n = new Notification();
            $n->setUser($admin);
            $n->setType('system');
            $n->setTitle('Administration');
            $n->setMessage($msg);
            $n->setIsRead(false);
            $m->persist($n);
        }
    }
}
