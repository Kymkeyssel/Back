<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260620000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add departure/arrival GPS coordinates to trips and update AGENCY roles';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE trips ADD departure_latitude DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE trips ADD departure_longitude DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE trips ADD arrival_latitude DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE trips ADD arrival_longitude DOUBLE PRECISION DEFAULT NULL');

        $this->addSql("
            UPDATE users
            SET roles = REPLACE(roles::text, 'ROLE_AGENCY_OWNER', 'ROLE_AGENCY')::jsonb
            WHERE roles::text LIKE '%ROLE_AGENCY_OWNER%'
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE trips DROP departure_latitude');
        $this->addSql('ALTER TABLE trips DROP departure_longitude');
        $this->addSql('ALTER TABLE trips DROP arrival_latitude');
        $this->addSql('ALTER TABLE trips DROP arrival_longitude');
    }
}
