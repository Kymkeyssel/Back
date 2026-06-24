<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260621000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed carpool trips (transport_mode_id=2) for the covoiturage section';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            INSERT INTO trips (id, agency_id, vehicle_id, transport_mode_id, departure_city, arrival_city, departure_address, arrival_address, departure_time, arrival_time, price, available_seats, total_seats, status, distance, duration, created_at, updated_at)
            VALUES
                (131, 101, 102, 2, 'Yaoundé', 'Douala', 'Mvan, Rue Principale', 'Bessengue, Carrefour', NOW() + INTERVAL '1 day' + TIME '06:00:00', NOW() + INTERVAL '1 day' + TIME '09:00:00', 2500.00, 3, 4, 'scheduled', 250.5, 180, NOW(), NOW()),
                (132, 101, 102, 2, 'Douala', 'Yaoundé', 'Bessengue, Carrefour', 'Mvan, Rue Principale', NOW() + INTERVAL '1 day' + TIME '14:00:00', NOW() + INTERVAL '1 day' + TIME '17:00:00', 2500.00, 2, 4, 'scheduled', 250.5, 180, NOW(), NOW()),
                (133, 102, 104, 2, 'Yaoundé', 'Bafoussam', 'Mvan, Gare Routière', 'Marché Central, Rue du Commerce', NOW() + INTERVAL '2 days' + TIME '08:00:00', NOW() + INTERVAL '2 days' + TIME '12:00:00', 3500.00, 3, 4, 'scheduled', 310.0, 240, NOW(), NOW()),
                (134, 102, 104, 2, 'Douala', 'Bafoussam', 'Bessengue, Rue Principale', 'Marché Central, Rue du Commerce', NOW() + INTERVAL '2 days' + TIME '06:30:00', NOW() + INTERVAL '2 days' + TIME '10:00:00', 3000.00, 4, 4, 'scheduled', 190.0, 210, NOW(), NOW()),
                (135, 103, 108, 2, 'Yaoundé', 'Bertoua', 'Mokolo Avenue Kennedy', 'Gare de Bertoua', NOW() + INTERVAL '3 days' + TIME '05:00:00', NOW() + INTERVAL '3 days' + TIME '10:00:00', 4500.00, 2, 4, 'scheduled', 350.0, 300, NOW(), NOW()),
                (136, 103, 108, 2, 'Douala', 'Bertoua', 'Bonapriso, Rue Gallieni', 'Gare de Bertoua', NOW() + INTERVAL '3 days' + TIME '07:00:00', NOW() + INTERVAL '3 days' + TIME '13:00:00', 5000.00, 3, 4, 'scheduled', 420.0, 360, NOW(), NOW()),
                (137, 101, 102, 2, 'Bafoussam', 'Yaoundé', 'Marché Bafoussam', 'Mvan, Rue Principale', NOW() + INTERVAL '1 day' + TIME '16:00:00', NOW() + INTERVAL '1 day' + TIME '20:00:00', 3500.00, 1, 4, 'scheduled', 310.0, 240, NOW(), NOW()),
                (138, 104, 110, 2, 'Douala', 'Kribi', 'Bonapriso', 'Gare de Kribi', NOW() + INTERVAL '4 days' + TIME '06:00:00', NOW() + INTERVAL '4 days' + TIME '09:00:00', 3000.00, 3, 4, 'scheduled', 160.0, 180, NOW(), NOW())
            ON CONFLICT (id) DO NOTHING;
        ");

        $this->addSql("SELECT setval('trips_id_seq', GREATEST(COALESCE((SELECT MAX(id) FROM trips), 1), 138))");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM trips WHERE id BETWEEN 131 AND 138");
    }
}
