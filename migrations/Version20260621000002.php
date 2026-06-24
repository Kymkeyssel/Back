<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260621000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed a demo booking for the test client on an in_progress trip (trip 111) for GPS testing';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            INSERT INTO bookings (id, reference, user_id, trip_id, status, total_price, number_of_seats, seat_numbers, passengers, created_at, updated_at)
            VALUES
                (131, 'BK-DEMO-GPS-001', 122, 111, 'confirmed', 3500.00, 1, '[1]', '[{\"name\":\"Client Test\",\"phone\":\"+237600000002\"}]', NOW(), NOW()),
                (132, 'BK-DEMO-GPS-002', 122, 111, 'confirmed', 3500.00, 2, '[2,3]', '[{\"name\":\"Client Test\",\"phone\":\"+237600000002\"},{\"name\":\"Ami Test\",\"phone\":\"+237699999999\"}]', NOW() - INTERVAL '1 day', NOW())
            ON CONFLICT (id) DO NOTHING;
        ");

        $this->addSql("
            INSERT INTO payments (id, booking_id, user_id, method, amount, currency, status, transaction_id, metadata, created_at, updated_at, completed_at)
            VALUES
                (120, 131, 122, 'mtn_momo', 3500.00, 'XAF', 'completed', 'TXN-DEMO-GPS-001', '{\"network\":\"mtn\",\"phone\":\"+237600000002\"}', NOW(), NOW(), NOW()),
                (121, 132, 122, 'orange_money', 3500.00, 'XAF', 'completed', 'TXN-DEMO-GPS-002', '{\"network\":\"orange\",\"phone\":\"+237600000002\"}', NOW() - INTERVAL '1 day', NOW(), NOW() - INTERVAL '1 day' + INTERVAL '5 minutes')
            ON CONFLICT (id) DO NOTHING;
        ");

        $this->addSql("SELECT setval('bookings_id_seq', GREATEST(COALESCE((SELECT MAX(id) FROM bookings), 1), 132))");
        $this->addSql("SELECT setval('payments_id_seq', GREATEST(COALESCE((SELECT MAX(id) FROM payments), 1), 121))");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM payments WHERE id BETWEEN 120 AND 121");
        $this->addSql("DELETE FROM bookings WHERE id BETWEEN 131 AND 132");
    }
}
