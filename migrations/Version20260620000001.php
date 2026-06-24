<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260620000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add boarding_platform to trips and seat_layout to vehicles';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE trips ADD boarding_platform VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE vehicles ADD seat_layout JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE trips DROP boarding_platform');
        $this->addSql('ALTER TABLE vehicles DROP seat_layout');
    }
}
