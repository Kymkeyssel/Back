<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260618000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed comprehensive demo data for dashboards (users, agencies, vehicles, trips, bookings, payments, reviews, notifications)';
    }

    public function up(Schema $schema): void
    {
        // ────────────────────────────────────────────────────
        // 1. TRANSPORT MODES
        // ────────────────────────────────────────────────────
        $this->addSql("
            INSERT INTO transport_modes (id, name, code, description, icon, is_active, sort_order, created_at, updated_at)
            VALUES
                (1, 'Bus Interurbain', 'INTERCITY_BUS', 'Lignes régulières de bus entre les grandes villes', 'bus', true, 1, NOW(), NOW()),
                (2, 'Covoiturage', 'CARPOOL', 'Trajets partagés en voiture entre particuliers', 'car', true, 2, NOW(), NOW())
            ON CONFLICT (id) DO NOTHING;
        ");

        // ────────────────────────────────────────────────────
        // 2. USERS
        // ────────────────────────────────────────────────────
        $this->addSql("
            INSERT INTO users (id, email, roles, password, first_name, last_name, phone, orange_money_phone, mtn_mo_mo_phone, preferred_language, is_verified, created_at, updated_at)
            VALUES
                (101, 'admin@one4all.cm', '[\"ROLE_SUPER_ADMIN\",\"ROLE_ADMIN\"]', '\$2y\$13\$kFeR98cM3pin5/1o3zEm3utw0KsVyZ76Kl/q/F1GSp/JklnbqJ33G', 'Jean-Claude', 'Biya', '+237691234501', '+237691234501', '+237671234501', 'fr', true, NOW() - INTERVAL '90 days', NOW()),
                (102, 'moderator@one4all.cm', '[\"ROLE_ADMIN\"]', '\$2y\$13\$kFeR98cM3pin5/1o3zEm3utw0KsVyZ76Kl/q/F1GSp/JklnbqJ33G', 'Marie', 'Nkolo', '+237691234502', NULL, '+237671234502', 'fr', true, NOW() - INTERVAL '85 days', NOW()),
                (103, 'paul@bucavoyages.cm', '[\"ROLE_AGENCY\"]', '\$2y\$13\$kFeR98cM3pin5/1o3zEm3utw0KsVyZ76Kl/q/F1GSp/JklnbqJ33G', 'Paul', 'Biya Jr', '+237691234503', '+237691234503', '+237671234503', 'fr', true, NOW() - INTERVAL '80 days', NOW()),
                (104, 'bruno@garantieexpress.cm', '[\"ROLE_AGENCY\"]', '\$2y\$13\$kFeR98cM3pin5/1o3zEm3utw0KsVyZ76Kl/q/F1GSp/JklnbqJ33G', 'Bruno', 'Foche', '+237691234504', NULL, '+237671234504', 'fr', true, NOW() - INTERVAL '75 days', NOW()),
                (105, 'carole@finex.cm', '[\"ROLE_AGENCY\"]', '\$2y\$13\$kFeR98cM3pin5/1o3zEm3utw0KsVyZ76Kl/q/F1GSp/JklnbqJ33G', 'Carole', 'Bilong', '+237691234505', '+237691234505', NULL, 'fr', true, NOW() - INTERVAL '70 days', NOW()),
                (106, 'samuel.etoo@bucavoyages.cm', '[\"ROLE_USER\"]', '\$2y\$13\$kFeR98cM3pin5/1o3zEm3utw0KsVyZ76Kl/q/F1GSp/JklnbqJ33G', 'Samuel', 'Eto''o', '+237691234506', NULL, '+237671234506', 'fr', true, NOW() - INTERVAL '65 days', NOW()),
                (107, 'roger.milla@bucavoyages.cm', '[\"ROLE_USER\"]', '\$2y\$13\$kFeR98cM3pin5/1o3zEm3utw0KsVyZ76Kl/q/F1GSp/JklnbqJ33G', 'Roger', 'Milla', '+237691234507', NULL, NULL, 'fr', true, NOW() - INTERVAL '60 days', NOW()),
                (108, 'patrick.mboma@garantie.cm', '[\"ROLE_USER\"]', '\$2y\$13\$kFeR98cM3pin5/1o3zEm3utw0KsVyZ76Kl/q/F1GSp/JklnbqJ33G', 'Patrick', 'Mboma', '+237691234508', NULL, '+237671234508', 'fr', true, NOW() - INTERVAL '55 days', NOW()),
                (109, 'rigobert.song@finex.cm', '[\"ROLE_USER\"]', '\$2y\$13\$kFeR98cM3pin5/1o3zEm3utw0KsVyZ76Kl/q/F1GSp/JklnbqJ33G', 'Rigobert', 'Song', '+237691234509', '+237691234509', NULL, 'fr', true, NOW() - INTERVAL '50 days', NOW()),
                (110, 'vincent.aboubakar@one4all.cm', '[\"ROLE_USER\"]', '\$2y\$13\$kFeR98cM3pin5/1o3zEm3utw0KsVyZ76Kl/q/F1GSp/JklnbqJ33G', 'Vincent', 'Aboubakar', '+237691234510', NULL, '+237671234510', 'fr', true, NOW() - INTERVAL '45 days', NOW()),
                (111, 'alice.ngo@gmail.com', '[\"ROLE_USER\"]', '\$2y\$13\$kFeR98cM3pin5/1o3zEm3utw0KsVyZ76Kl/q/F1GSp/JklnbqJ33G', 'Alice', 'Ngo', '+237691234511', '+237691234511', '+237671234511', 'fr', true, NOW() - INTERVAL '40 days', NOW()),
                (112, 'david.mvogo@yahoo.fr', '[\"ROLE_USER\"]', '\$2y\$13\$kFeR98cM3pin5/1o3zEm3utw0KsVyZ76Kl/q/F1GSp/JklnbqJ33G', 'David', 'Mvogo', '+237691234512', NULL, '+237671234512', 'fr', true, NOW() - INTERVAL '35 days', NOW()),
                (113, 'esther.mbah@outlook.com', '[\"ROLE_USER\"]', '\$2y\$13\$kFeR98cM3pin5/1o3zEm3utw0KsVyZ76Kl/q/F1GSp/JklnbqJ33G', 'Esther', 'Mbah', '+237691234513', '+237691234513', NULL, 'fr', true, NOW() - INTERVAL '30 days', NOW()),
                (114, 'francois.kamga@gmail.com', '[\"ROLE_USER\"]', '\$2y\$13\$kFeR98cM3pin5/1o3zEm3utw0KsVyZ76Kl/q/F1GSp/JklnbqJ33G', 'François', 'Kamga', '+237691234514', NULL, '+237671234514', 'fr', true, NOW() - INTERVAL '25 days', NOW()),
                (115, 'grace.nganou@gmail.com', '[\"ROLE_USER\"]', '\$2y\$13\$kFeR98cM3pin5/1o3zEm3utw0KsVyZ76Kl/q/F1GSp/JklnbqJ33G', 'Grâce', 'Nganou', '+237691234515', '+237691234515', '+237671234515', 'fr', true, NOW() - INTERVAL '20 days', NOW()),
                (116, 'herve.tchamba@one4all.cm', '[\"ROLE_AGENCY\"]', '\$2y\$13\$kFeR98cM3pin5/1o3zEm3utw0KsVyZ76Kl/q/F1GSp/JklnbqJ33G', 'Hervé', 'Tchamba', '+237691234516', NULL, '+237671234516', 'fr', true, NOW() - INTERVAL '15 days', NOW()),
                (117, 'jeanne.metogo@gmail.com', '[\"ROLE_USER\"]', '\$2y\$13\$kFeR98cM3pin5/1o3zEm3utw0KsVyZ76Kl/q/F1GSp/JklnbqJ33G', 'Jeanne', 'Metogo', '+237691234517', '+237691234517', NULL, 'fr', true, NOW() - INTERVAL '10 days', NOW()),
                (118, 'kevin.assomo@gmail.com', '[\"ROLE_USER\"]', '\$2y\$13\$kFeR98cM3pin5/1o3zEm3utw0KsVyZ76Kl/q/F1GSp/JklnbqJ33G', 'Kévin', 'Assomo', '+237691234518', NULL, '+237671234518', 'fr', true, NOW() - INTERVAL '7 days', NOW()),
                (119, 'laurence.beyala@one4all.cm', '[\"ROLE_USER\"]', '\$2y\$13\$kFeR98cM3pin5/1o3zEm3utw0KsVyZ76Kl/q/F1GSp/JklnbqJ33G', 'Laurence', 'Beyala', '+237691234519', '+237691234519', NULL, 'fr', true, NOW() - INTERVAL '5 days', NOW()),
                (120, 'michel.tagny@yahoo.com', '[\"ROLE_USER\"]', '\$2y\$13\$kFeR98cM3pin5/1o3zEm3utw0KsVyZ76Kl/q/F1GSp/JklnbqJ33G', 'Michel', 'Tagny', '+237691234520', NULL, '+237671234520', 'fr', true, NOW() - INTERVAL '3 days', NOW()),
                (121, 'superadmin@one4all.cm', '[\"ROLE_SUPER_ADMIN\",\"ROLE_ADMIN\"]', '\$2y\$13\$ZbDQojw/3VcLl0bvAXdV5enrq7ON0TAeNHbklOxOwfKKd5c0od2eO', 'Super', 'Admin', '+237600000001', NULL, NULL, 'fr', true, NOW(), NOW()),
                (122, 'client@one4all.cm', '[\"ROLE_USER\"]', '\$2y\$13\$0hFwBzwa9ZkNRxThOPADk.EvMKGYDpfkv00Tq5aF9TAKc6cPqLqC2', 'Client', 'Test', '+237600000002', NULL, NULL, 'fr', true, NOW(), NOW()),
                (123, 'agency@one4all.cm', '[\"ROLE_AGENCY\"]', '\$2y\$13\$Q5h8mf1Yxe0UdNCgWdo3Y.ep3S4iU3W4nZy9N3CVfXQ29DwQV5iSS', 'Agence', 'Test', '+237600000003', NULL, NULL, 'fr', true, NOW(), NOW())
            ON CONFLICT (id) DO NOTHING;
        ");

        // ────────────────────────────────────────────────────
        // 3. AGENCIES
        // ────────────────────────────────────────────────────
        $this->addSql("
            INSERT INTO agencies (id, name, slug, description, phone, email, address, city, country, latitude, longitude, rating, total_reviews, is_verified, is_active, owner_id, created_at, updated_at)
            VALUES
                (101, 'Buca Voyages', 'buca-voyages', 'Leader du transport interurbain au Cameroun. Liaisons Yaoundé-Douala-Bafoussam-Bertoua.', '+237233456101', 'contact@bucavoyages.cm', 'Mvan, Route de l''Aéroport', 'Yaoundé', 'Cameroon', 3.8612, 11.5210, 4.2, 28, true, true, 103, NOW() - INTERVAL '80 days', NOW()),
                (102, 'Garantie Express', 'garantie-express', 'Voyagez en toute sérénité avec Garantie Express, la référence du transport vers l''Ouest du Cameroun.', '+237233456102', 'info@garantieexpress.cm', 'Bessengue, Rue Principale', 'Douala', 'Cameroon', 4.0511, 9.7679, 3.8, 15, true, true, 104, NOW() - INTERVAL '75 days', NOW()),
                (103, 'Finex Voyages', 'finex-voyages', 'Transport de qualité vers les régions de l''Est et de l''Adamaoua. Confort et ponctualité.', '+237233456103', 'contact@finexvoyages.cm', 'Mokolo, Avenue Kennedy', 'Yaoundé', 'Cameroon', 3.8742, 11.5083, 4.5, 12, true, true, 105, NOW() - INTERVAL '70 days', NOW()),
                (104, 'Confort Plus', 'confort-plus', 'Agence premium offrant des bus climatisés avec wifi et rafraîchissements à bord.', '+237233456104', 'voyage@confortplus.cm', 'Bonapriso, Rue Gallieni', 'Douala', 'Cameroon', 4.0435, 9.6872, 4.0, 8, false, true, 116, NOW() - INTERVAL '15 days', NOW()),
                (105, 'Général Transport', 'general-transport', 'Solutions de transport abordables pour toutes les destinations du Grand Nord.', '+237233456105', 'info@generaltransport.cm', 'Centre Ville, Rue du Commerce', 'Bafoussam', 'Cameroon', 5.4772, 10.4176, 2.5, 4, false, true, 104, NOW() - INTERVAL '60 days', NOW())
            ON CONFLICT (id) DO NOTHING;
        ");

        // ────────────────────────────────────────────────────
        // 4. VEHICLES
        // ────────────────────────────────────────────────────
        $this->addSql("
            INSERT INTO vehicles (id, agency_id, driver_id, type, brand, model, plate_number, total_seats, amenities, is_electric, is_active, last_maintenance_at, created_at, updated_at)
            VALUES
                (101, 101, 106, 'bus', 'Mercedes-Benz', 'Sprinter 516', 'LT 4521 CM', 18, '[\"climatisation\",\"wifi\",\"prise_usb\",\"toilettes\"]', false, true, NOW() - INTERVAL '15 days', NOW() - INTERVAL '80 days', NOW()),
                (102, 101, 107, 'bus', 'Toyota', 'Hiace', 'LT 0829 CM', 15, '[\"climatisation\",\"wifi\",\"prise_usb\"]', false, true, NOW() - INTERVAL '30 days', NOW() - INTERVAL '78 days', NOW()),
                (103, 101, 106, 'minibus', 'Toyota', 'Coaster', 'CE 1234 CM', 30, '[\"climatisation\",\"wifi\",\"prise_usb\",\"toilettes\",\"television\"]', false, true, NOW() - INTERVAL '45 days', NOW() - INTERVAL '76 days', NOW()),
                (104, 102, 108, 'bus', 'Mercedes-Benz', 'Sprinter 515', 'LT 3344 CM', 18, '[\"climatisation\",\"wifi\",\"prise_usb\"]', false, true, NOW() - INTERVAL '20 days', NOW() - INTERVAL '74 days', NOW()),
                (105, 102, 108, 'minibus', 'Toyota', 'Hiace', 'LT 5566 CM', 15, '[\"climatisation\",\"prise_usb\"]', false, true, NOW() - INTERVAL '60 days', NOW() - INTERVAL '72 days', NOW()),
                (106, 102, NULL, 'bus', 'King Long', '6118', 'LT 7788 CM', 35, '[\"climatisation\",\"wifi\",\"television\"]', false, false, NOW() - INTERVAL '90 days', NOW() - INTERVAL '70 days', NOW()),
                (107, 103, 109, 'bus', 'Scania', 'Irizar i6', 'LT 9900 CM', 40, '[\"climatisation\",\"wifi\",\"prise_usb\",\"toilettes\",\"television\",\"cafe\"]', false, true, NOW() - INTERVAL '10 days', NOW() - INTERVAL '68 days', NOW()),
                (108, 103, 109, 'minibus', 'Toyota', 'Hiace', 'CE 2468 CM', 15, '[\"climatisation\",\"wifi\"]', false, true, NOW() - INTERVAL '25 days', NOW() - INTERVAL '66 days', NOW()),
                (109, 103, NULL, 'bus', 'Mercedes-Benz', 'Tourismo', 'CE 1357 CM', 45, '[\"climatisation\",\"wifi\",\"prise_usb\",\"toilettes\",\"television\"]', false, true, NOW() - INTERVAL '5 days', NOW() - INTERVAL '64 days', NOW()),
                (110, 104, 110, 'minibus', 'Toyota', 'Hiace', 'LT 8642 CM', 12, '[\"climatisation\",\"wifi\",\"prise_usb\"]', false, true, NOW() - INTERVAL '12 days', NOW() - INTERVAL '14 days', NOW()),
                (111, 104, 110, 'bus', 'Mercedes-Benz', 'Sprinter 516', 'LT 9753 CM', 18, '[\"climatisation\",\"wifi\",\"prise_usb\",\"toilettes\"]', true, true, NULL, NOW() - INTERVAL '12 days', NOW()),
                (112, 105, NULL, 'bus', 'Toyota', 'Coaster', 'LT 1122 CM', 30, '[\"climatisation\"]', false, true, NOW() - INTERVAL '50 days', NOW() - INTERVAL '58 days', NOW())
            ON CONFLICT (id) DO NOTHING;
        ");

        // ────────────────────────────────────────────────────
        // 5. TRIPS
        // ────────────────────────────────────────────────────
        $this->addSql("
            INSERT INTO trips (id, agency_id, vehicle_id, transport_mode_id, departure_city, arrival_city, departure_address, arrival_address, departure_time, arrival_time, price, available_seats, total_seats, status, distance, duration, current_latitude, current_longitude, created_at, updated_at)
            VALUES
                -- Buca Voyages trips (agency 101)
                (101, 101, 101, 1, 'Yaoundé', 'Douala', 'Gare Routière de Mvan', 'Gare de Bessengue', NOW() - INTERVAL '2 days' + TIME '06:00:00', NOW() - INTERVAL '2 days' + TIME '10:00:00', 3500.00, 0, 18, 'completed', 250.5, 240, 4.1200, 9.8800, NOW() - INTERVAL '3 days', NOW()),
                (102, 101, 102, 1, 'Douala', 'Yaoundé', 'Gare de Bessengue', 'Gare Routière de Mvan', NOW() - INTERVAL '1 day' + TIME '07:00:00', NOW() - INTERVAL '1 day' + TIME '11:00:00', 3500.00, 1, 15, 'completed', 250.5, 240, 3.9800, 11.5200, NOW() - INTERVAL '2 days', NOW()),
                (103, 101, 101, 1, 'Yaoundé', 'Bafoussam', 'Gare Routière de Mvan', 'Gare de Bafoussam', NOW() + TIME '06:00:00', NOW() + TIME '10:30:00', 4000.00, 6, 18, 'scheduled', 310.0, 270, NULL, NULL, NOW() - INTERVAL '1 day', NOW()),
                (104, 101, 102, 1, 'Yaoundé', 'Douala', 'Gare Routière de Mvan', 'Gare de Bessengue', NOW() + TIME '14:00:00', NOW() + TIME '18:00:00', 3500.00, 10, 15, 'scheduled', 250.5, 240, NULL, NULL, NOW() - INTERVAL '1 day', NOW()),
                (105, 101, 101, 1, 'Douala', 'Yaoundé', 'Gare de Bessengue', 'Gare Routière de Mvan', NOW() + INTERVAL '1 day' + TIME '06:00:00', NOW() + INTERVAL '1 day' + TIME '10:00:00', 3500.00, 18, 18, 'scheduled', 250.5, 240, NULL, NULL, NOW(), NOW()),
                (106, 101, 103, 1, 'Yaoundé', 'Bertoua', 'Gare Routière de Mvan', 'Gare de Bertoua', NOW() + INTERVAL '2 days' + TIME '05:00:00', NOW() + INTERVAL '2 days' + TIME '10:00:00', 5500.00, 30, 30, 'scheduled', 350.0, 300, NULL, NULL, NOW(), NOW()),
                (107, 101, 102, 1, 'Douala', 'Bafoussam', 'Gare de Bessengue', 'Gare de Bafoussam', NOW() - INTERVAL '1 day' + TIME '08:00:00', NOW() - INTERVAL '1 day' + TIME '11:30:00', 4000.00, 0, 15, 'completed', 190.0, 210, 5.2000, 10.4000, NOW() - INTERVAL '2 days', NOW()),
                (108, 101, 101, 1, 'Bafoussam', 'Yaoundé', 'Gare de Bafoussam', 'Gare Routière de Mvan', NOW() - INTERVAL '3 days' + TIME '06:30:00', NOW() - INTERVAL '3 days' + TIME '11:00:00', 4000.00, 0, 18, 'completed', 310.0, 270, NULL, NULL, NOW() - INTERVAL '4 days', NOW()),

                -- Garantie Express trips (agency 102)
                (109, 102, 104, 1, 'Douala', 'Bafoussam', 'Gare de Bessengue', 'Gare de Bafoussam', NOW() - INTERVAL '1 day' + TIME '06:00:00', NOW() - INTERVAL '1 day' + TIME '09:30:00', 4000.00, 2, 18, 'completed', 190.0, 210, 4.8000, 10.1000, NOW() - INTERVAL '2 days', NOW()),
                (110, 102, 105, 1, 'Bafoussam', 'Douala', 'Gare de Bafoussam', 'Gare de Bessengue', NOW() - INTERVAL '2 days' + TIME '14:00:00', NOW() - INTERVAL '2 days' + TIME '17:30:00', 4000.00, 5, 15, 'completed', 190.0, 210, NULL, NULL, NOW() - INTERVAL '3 days', NOW()),
                (111, 102, 104, 1, 'Douala', 'Yaoundé', 'Gare de Bessengue', 'Gare Routière de Mvan', NOW() + TIME '05:00:00', NOW() + TIME '09:00:00', 3500.00, 4, 18, 'in_progress', 250.5, 240, 4.0500, 10.5000, NOW() - INTERVAL '1 day', NOW()),
                (112, 102, 105, 1, 'Douala', 'Bamenda', 'Gare de Bessengue', 'Gare de Bamenda', NOW() + INTERVAL '1 day' + TIME '07:00:00', NOW() + INTERVAL '1 day' + TIME '12:00:00', 5000.00, 15, 15, 'scheduled', 300.0, 300, NULL, NULL, NOW(), NOW()),
                (113, 102, 106, 1, 'Yaoundé', 'Douala', 'Gare Routière de Mvan', 'Gare de Bessengue', NOW() + INTERVAL '2 days' + TIME '08:00:00', NOW() + INTERVAL '2 days' + TIME '12:00:00', 3500.00, 35, 35, 'scheduled', 250.5, 240, NULL, NULL, NOW(), NOW()),
                (114, 102, 104, 1, 'Bafoussam', 'Douala', 'Gare de Bafoussam', 'Gare de Bessengue', NOW() - INTERVAL '4 days' + TIME '06:00:00', NOW() - INTERVAL '4 days' + TIME '09:30:00', 4000.00, 0, 18, 'completed', 190.0, 210, NULL, NULL, NOW() - INTERVAL '5 days', NOW()),

                -- Finex Voyages trips (agency 103)
                (115, 103, 107, 1, 'Yaoundé', 'Bertoua', 'Gare de Mokolo', 'Gare de Bertoua', NOW() - INTERVAL '3 days' + TIME '05:00:00', NOW() - INTERVAL '3 days' + TIME '10:00:00', 5500.00, 5, 40, 'completed', 350.0, 300, 4.5000, 13.5000, NOW() - INTERVAL '4 days', NOW()),
                (116, 103, 108, 1, 'Bertoua', 'Yaoundé', 'Gare de Bertoua', 'Gare de Mokolo', NOW() - INTERVAL '2 days' + TIME '06:00:00', NOW() - INTERVAL '2 days' + TIME '11:00:00', 5500.00, 3, 15, 'completed', 350.0, 300, NULL, NULL, NOW() - INTERVAL '3 days', NOW()),
                (117, 103, 107, 1, 'Yaoundé', 'Ngaoundéré', 'Gare de Mokolo', 'Gare de Ngaoundéré', NOW() + INTERVAL '1 day' + TIME '04:00:00', NOW() + INTERVAL '1 day' + TIME '14:00:00', 8500.00, 40, 40, 'scheduled', 650.0, 600, NULL, NULL, NOW(), NOW()),
                (118, 103, 109, 1, 'Yaoundé', 'Douala', 'Gare de Mokolo', 'Gare de Bessengue', NOW() + TIME '07:00:00', NOW() + TIME '11:00:00', 3500.00, 8, 45, 'in_progress', 250.5, 240, 3.9000, 11.3000, NOW() - INTERVAL '1 day', NOW()),
                (119, 103, 108, 1, 'Douala', 'Bertoua', 'Gare de Bessengue', 'Gare de Bertoua', NOW() + INTERVAL '2 days' + TIME '05:30:00', NOW() + INTERVAL '2 days' + TIME '11:30:00', 6000.00, 15, 15, 'scheduled', 420.0, 360, NULL, NULL, NOW(), NOW()),

                -- Confort Plus trips (agency 104)
                (120, 104, 110, 1, 'Douala', 'Yaoundé', 'Gare de Bonapriso', 'Gare de Mvan', NOW() + TIME '08:00:00', NOW() + TIME '11:30:00', 5000.00, 12, 12, 'scheduled', 250.5, 210, NULL, NULL, NOW() - INTERVAL '1 day', NOW()),
                (121, 104, 111, 1, 'Yaoundé', 'Douala', 'Gare de Mvan', 'Gare de Bonapriso', NOW() + INTERVAL '1 day' + TIME '09:00:00', NOW() + INTERVAL '1 day' + TIME '12:30:00', 5500.00, 18, 18, 'scheduled', 250.5, 210, NULL, NULL, NOW(), NOW()),
                (122, 104, 110, 1, 'Douala', 'Kribi', 'Gare de Bonapriso', 'Gare de Kribi', NOW() + INTERVAL '3 days' + TIME '06:00:00', NOW() + INTERVAL '3 days' + TIME '09:00:00', 4500.00, 12, 12, 'scheduled', 160.0, 180, NULL, NULL, NOW(), NOW()),

                -- Général Transport trips (agency 105)
                (123, 105, 112, 1, 'Bafoussam', 'Maroua', 'Gare de Bafoussam', 'Gare de Maroua', NOW() + INTERVAL '4 days' + TIME '04:00:00', NOW() + INTERVAL '4 days' + TIME '18:00:00', 12000.00, 30, 30, 'scheduled', 800.0, 840, NULL, NULL, NOW(), NOW()),
                (124, 105, 112, 1, 'Maroua', 'Garoua', 'Gare de Maroua', 'Gare de Garoua', NOW() + INTERVAL '6 days' + TIME '08:00:00', NOW() + INTERVAL '6 days' + TIME '12:00:00', 3500.00, 30, 30, 'scheduled', 250.0, 240, NULL, NULL, NOW(), NOW()),

                -- Past trips with various statuses
                (125, 101, 103, 1, 'Douala', 'Yaoundé', 'Gare de Bessengue', 'Gare de Mvan', NOW() - INTERVAL '7 days' + TIME '06:00:00', NOW() - INTERVAL '7 days' + TIME '10:00:00', 3500.00, 0, 30, 'completed', 250.5, 240, NULL, NULL, NOW() - INTERVAL '8 days', NOW()),
                (126, 102, 105, 1, 'Yaoundé', 'Bafoussam', 'Gare de Mvan', 'Gare de Bafoussam', NOW() - INTERVAL '6 days' + TIME '07:00:00', NOW() - INTERVAL '6 days' + TIME '11:30:00', 4000.00, 0, 15, 'completed', 310.0, 270, NULL, NULL, NOW() - INTERVAL '7 days', NOW()),
                (127, 103, 108, 1, 'Bertoua', 'Douala', 'Gare de Bertoua', 'Gare de Bessengue', NOW() - INTERVAL '5 days' + TIME '06:00:00', NOW() - INTERVAL '5 days' + TIME '12:00:00', 6000.00, 0, 15, 'completed', 420.0, 360, NULL, NULL, NOW() - INTERVAL '6 days', NOW()),
                (128, 101, 102, 1, 'Yaoundé', 'Douala', 'Gare de Mvan', 'Gare de Bessengue', NOW() - INTERVAL '14 days' + TIME '06:00:00', NOW() - INTERVAL '14 days' + TIME '10:00:00', 3500.00, 0, 15, 'completed', 250.5, 240, NULL, NULL, NOW() - INTERVAL '15 days', NOW()),
                (129, 101, 101, 1, 'Yaoundé', 'Douala', 'Gare de Mvan', 'Gare de Bessengue', NOW() - INTERVAL '10 days' + TIME '14:00:00', NOW() - INTERVAL '10 days' + TIME '18:00:00', 3500.00, 0, 18, 'cancelled', 250.5, 240, NULL, NULL, NOW() - INTERVAL '11 days', NOW()),
                (130, 102, 104, 1, 'Douala', 'Bafoussam', 'Gare de Bessengue', 'Gare de Bafoussam', NOW() - INTERVAL '21 days' + TIME '06:00:00', NOW() - INTERVAL '21 days' + TIME '09:30:00', 4000.00, 0, 18, 'completed', 190.0, 210, NULL, NULL, NOW() - INTERVAL '22 days', NOW())
            ON CONFLICT (id) DO NOTHING;
        ");

        // ────────────────────────────────────────────────────
        // 6. BOOKINGS
        // ────────────────────────────────────────────────────
        $this->addSql("
            INSERT INTO bookings (id, reference, user_id, trip_id, status, total_price, number_of_seats, seat_numbers, passengers, special_requests, created_at, updated_at)
            VALUES
                -- Completed bookings for Alice Ngo (111)
                (101, 'BK-2406-001', 111, 101, 'completed', 3500.00, 1, '[1]', '[{\"name\":\"Alice Ngo\",\"phone\":\"+237691234511\"}]', NULL, NOW() - INTERVAL '2 days', NOW()),
                (102, 'BK-2406-002', 111, 107, 'completed', 8000.00, 2, '[3,4]', '[{\"name\":\"Alice Ngo\",\"phone\":\"+237691234511\"},{\"name\":\"Pierre Ngo\",\"phone\":\"+237699876543\"}]', NULL, NOW() - INTERVAL '1 day', NOW()),
                (103, 'BK-2406-003', 111, 125, 'completed', 3500.00, 1, '[2]', '[{\"name\":\"Alice Ngo\",\"phone\":\"+237691234511\"}]', NULL, NOW() - INTERVAL '7 days', NOW()),

                -- Bookings for David Mvogo (112)
                (104, 'BK-2406-004', 112, 102, 'completed', 3500.00, 1, '[5]', '[{\"name\":\"David Mvogo\",\"phone\":\"+237691234512\"}]', NULL, NOW() - INTERVAL '1 day', NOW()),
                (105, 'BK-2406-005', 112, 109, 'completed', 4000.00, 1, '[2]', '[{\"name\":\"David Mvogo\",\"phone\":\"+237691234512\"}]', NULL, NOW() - INTERVAL '1 day', NOW()),
                (106, 'BK-2406-006', 112, 111, 'confirmed', 3500.00, 2, '[6,7]', '[{\"name\":\"David Mvogo\",\"phone\":\"+237691234512\"},{\"name\":\"Sophie Mvogo\",\"phone\":\"+237699877654\"}]', 'Fenêtre côté couloir si possible', NOW(), NOW()),

                -- Bookings for Esther Mbah (113)
                (107, 'BK-2406-007', 113, 115, 'completed', 5500.00, 1, '[10]', '[{\"name\":\"Esther Mbah\",\"phone\":\"+237691234513\"}]', NULL, NOW() - INTERVAL '3 days', NOW()),
                (108, 'BK-2406-008', 113, 116, 'completed', 5500.00, 1, '[5]', '[{\"name\":\"Esther Mbah\",\"phone\":\"+237691234513\"}]', NULL, NOW() - INTERVAL '2 days', NOW()),
                (109, 'BK-2406-009', 113, 118, 'confirmed', 3500.00, 1, '[12]', '[{\"name\":\"Esther Mbah\",\"phone\":\"+237691234513\"}]', NULL, NOW(), NOW()),

                -- Bookings for François Kamga (114)
                (110, 'BK-2406-010', 114, 101, 'completed', 7000.00, 2, '[8,9]', '[{\"name\":\"François Kamga\",\"phone\":\"+237691234514\"},{\"name\":\"Mireille Kamga\",\"phone\":\"+237699878654\"}]', NULL, NOW() - INTERVAL '2 days', NOW()),
                (111, 'BK-2406-011', 114, 103, 'pending', 4000.00, 1, '[3]', '[{\"name\":\"François Kamga\",\"phone\":\"+237691234514\"}]', NULL, NOW(), NOW()),
                (112, 'BK-2406-012', 114, 112, 'confirmed', 10000.00, 2, '[1,2]', '[{\"name\":\"François Kamga\",\"phone\":\"+237691234514\"},{\"name\":\"Mireille Kamga\",\"phone\":\"+237699878654\"}]', 'Voyage avec enfant en bas âge', NOW(), NOW()),

                -- Bookings for Grâce Nganou (115)
                (113, 'BK-2406-013', 115, 104, 'confirmed', 7000.00, 2, '[11,12]', '[{\"name\":\"Grâce Nganou\",\"phone\":\"+237691234515\"},{\"name\":\"Luc Nganou\",\"phone\":\"+237699879654\"}]', NULL, NOW(), NOW()),
                (114, 'BK-2406-014', 115, 117, 'pending', 8500.00, 1, '[5]', '[{\"name\":\"Grâce Nganou\",\"phone\":\"+237691234515\"}]', NULL, NOW(), NOW()),
                (115, 'BK-2406-015', 115, 125, 'completed', 3500.00, 1, '[15]', '[{\"name\":\"Grâce Nganou\",\"phone\":\"+237691234515\"}]', NULL, NOW() - INTERVAL '7 days', NOW()),

                -- Bookings for Jeanne Metogo (117)
                (116, 'BK-2406-016', 117, 107, 'completed', 8000.00, 2, '[7,8]', '[{\"name\":\"Jeanne Metogo\",\"phone\":\"+237691234517\"},{\"name\":\"Robert Metogo\",\"phone\":\"+237699870654\"}]', NULL, NOW() - INTERVAL '1 day', NOW()),
                (117, 'BK-2406-017', 117, 111, 'confirmed', 3500.00, 1, '[9]', '[{\"name\":\"Jeanne Metogo\",\"phone\":\"+237691234517\"}]', NULL, NOW(), NOW()),

                -- Bookings for Kévin Assomo (118)
                (118, 'BK-2406-018', 118, 118, 'confirmed', 3500.00, 1, '[15]', '[{\"name\":\"Kévin Assomo\",\"phone\":\"+237691234518\"}]', NULL, NOW(), NOW()),

                -- Bookings for Laurence Beyala (119)
                (119, 'BK-2406-019', 119, 103, 'pending', 4000.00, 1, '[8]', '[{\"name\":\"Laurence Beyala\",\"phone\":\"+237691234519\"}]', NULL, NOW(), NOW()),

                -- Bookings for Michel Tagny (120)
                (120, 'BK-2406-020', 120, 120, 'confirmed', 5000.00, 1, '[1]', '[{\"name\":\"Michel Tagny\",\"phone\":\"+237691234520\"}]', NULL, NOW(), NOW()),

                -- Additional historical bookings for dashboard chart data
                (121, 'BK-2406-021', 111, 128, 'completed', 3500.00, 1, '[4]', '[{\"name\":\"Alice Ngo\",\"phone\":\"+237691234511\"}]', NULL, NOW() - INTERVAL '14 days', NOW()),
                (122, 'BK-2406-022', 112, 128, 'completed', 3500.00, 1, '[6]', '[{\"name\":\"David Mvogo\",\"phone\":\"+237691234512\"}]', NULL, NOW() - INTERVAL '14 days', NOW()),
                (123, 'BK-2406-023', 114, 126, 'completed', 8000.00, 2, '[2,3]', '[{\"name\":\"François Kamga\",\"phone\":\"+237691234514\"},{\"name\":\"Mireille Kamga\",\"phone\":\"+237699878654\"}]', NULL, NOW() - INTERVAL '6 days', NOW()),
                (124, 'BK-2406-024', 117, 115, 'completed', 5500.00, 1, '[20]', '[{\"name\":\"Jeanne Metogo\",\"phone\":\"+237691234517\"}]', NULL, NOW() - INTERVAL '3 days', NOW()),

                -- Cancelled booking
                (125, 'BK-2406-025', 113, 129, 'cancelled', 3500.00, 1, '[5]', '[{\"name\":\"Esther Mbah\",\"phone\":\"+237691234513\"}]', NULL, NOW() - INTERVAL '10 days', NOW()),

                -- More bookings for agency owner data
                (126, 'BK-2406-026', 115, 103, 'confirmed', 8000.00, 2, '[1,2]', '[{\"name\":\"Grâce Nganou\",\"phone\":\"+237691234515\"},{\"name\":\"Paul Nganou\",\"phone\":\"+237699871654\"}]', NULL, NOW(), NOW()),
                (127, 'BK-2406-027', 118, 104, 'pending', 7000.00, 2, '[13,14]', '[{\"name\":\"Kévin Assomo\",\"phone\":\"+237691234518\"},{\"name\":\"Amina Assomo\",\"phone\":\"+237699872654\"}]', 'Sièges avant de préférence', NOW(), NOW()),
                (128, 'BK-2406-028', 119, 117, 'confirmed', 8500.00, 1, '[10]', '[{\"name\":\"Laurence Beyala\",\"phone\":\"+237691234519\"}]', NULL, NOW(), NOW()),
                (129, 'BK-2406-029', 120, 121, 'pending', 5500.00, 1, '[3]', '[{\"name\":\"Michel Tagny\",\"phone\":\"+237691234520\"}]', NULL, NOW(), NOW()),
                (130, 'BK-2406-030', 112, 122, 'pending', 4500.00, 1, '[5]', '[{\"name\":\"David Mvogo\",\"phone\":\"+237691234512\"}]', 'Départ à Kribi plage', NOW(), NOW())
            ON CONFLICT (id) DO NOTHING;
        ");

        // ────────────────────────────────────────────────────
        // 7. PAYMENTS
        // ────────────────────────────────────────────────────
        $this->addSql("
            INSERT INTO payments (id, booking_id, user_id, method, amount, currency, status, transaction_id, provider_reference, metadata, created_at, updated_at, completed_at)
            VALUES
                -- Completed payments for Alice Ngo
                (101, 101, 111, 'mtn_momo', 3500.00, 'XAF', 'completed', 'TXN-A1B2C3D4E5F67890', 'MTN-REF-2406-001', '{\"network\":\"mtn\",\"phone\":\"+237691234511\"}', NOW() - INTERVAL '2 days', NOW(), NOW() - INTERVAL '2 days' + INTERVAL '5 minutes'),
                (102, 102, 111, 'orange_money', 8000.00, 'XAF', 'completed', 'TXN-B2C3D4E5F67890A1', 'OM-REF-2406-001', '{\"network\":\"orange\",\"phone\":\"+237691234511\"}', NOW() - INTERVAL '1 day', NOW(), NOW() - INTERVAL '1 day' + INTERVAL '3 minutes'),

                -- Completed payments for David Mvogo
                (103, 104, 112, 'mtn_momo', 3500.00, 'XAF', 'completed', 'TXN-C3D4E5F67890A1B2', 'MTN-REF-2406-002', '{\"network\":\"mtn\",\"phone\":\"+237691234512\"}', NOW() - INTERVAL '1 day', NOW(), NOW() - INTERVAL '1 day' + INTERVAL '4 minutes'),
                (104, 105, 112, 'orange_money', 4000.00, 'XAF', 'completed', 'TXN-D4E5F67890A1B2C3', 'OM-REF-2406-002', '{\"network\":\"orange\",\"phone\":\"+237691234512\"}', NOW() - INTERVAL '1 day', NOW(), NOW() - INTERVAL '1 day' + INTERVAL '2 minutes'),
                (105, 106, 112, 'mtn_momo', 3500.00, 'XAF', 'processing', 'TXN-E5F67890A1B2C3D4', NULL, '{\"network\":\"mtn\",\"phone\":\"+237691234512\"}', NOW(), NOW(), NULL),

                -- Completed payments for Esther Mbah
                (106, 107, 113, 'orange_money', 5500.00, 'XAF', 'completed', 'TXN-F67890A1B2C3D4E5', 'OM-REF-2406-003', '{\"network\":\"orange\",\"phone\":\"+237691234513\"}', NOW() - INTERVAL '3 days', NOW(), NOW() - INTERVAL '3 days' + INTERVAL '3 minutes'),
                (107, 108, 113, 'mtn_momo', 5500.00, 'XAF', 'completed', 'TXN-7890A1B2C3D4E5F6', 'MTN-REF-2406-003', '{\"network\":\"mtn\",\"phone\":\"+237691234513\"}', NOW() - INTERVAL '2 days', NOW(), NOW() - INTERVAL '2 days' + INTERVAL '5 minutes'),
                (108, 109, 113, 'orange_money', 3500.00, 'XAF', 'pending', 'TXN-890A1B2C3D4E5F67', NULL, '{\"network\":\"orange\",\"phone\":\"+237691234513\"}', NOW(), NOW(), NULL),

                -- Completed payments for François Kamga
                (109, 110, 114, 'mtn_momo', 7000.00, 'XAF', 'completed', 'TXN-901A2B3C4D5E6F78', 'MTN-REF-2406-004', '{\"network\":\"mtn\",\"phone\":\"+237691234514\"}', NOW() - INTERVAL '2 days', NOW(), NOW() - INTERVAL '2 days' + INTERVAL '4 minutes'),
                (110, 111, 114, 'orange_money', 4000.00, 'XAF', 'pending', 'TXN-012A3B4C5D6E7F89', NULL, '{\"network\":\"orange\",\"phone\":\"+237691234514\"}', NOW(), NOW(), NULL),

                -- Completed payments for Grâce Nganou
                (111, 113, 115, 'mtn_momo', 7000.00, 'XAF', 'completed', 'TXN-AB1C2D3E4F567890', 'MTN-REF-2406-005', '{\"network\":\"mtn\",\"phone\":\"+237691234515\"}', NOW(), NOW(), NOW() + INTERVAL '3 minutes'),
                (112, 113, 115, 'orange_money', 7000.00, 'XAF', 'failed', 'TXN-BC1D2E3F45678901', NULL, '{\"network\":\"orange\",\"phone\":\"+237691234515\"}', NOW(), NOW(), NULL),

                -- Alice Ngo additional payments
                (113, 103, 111, 'mtn_momo', 3500.00, 'XAF', 'completed', 'TXN-CD1E2F3456789012', 'MTN-REF-2406-006', '{\"network\":\"mtn\",\"phone\":\"+237691234511\"}', NOW() - INTERVAL '7 days', NOW(), NOW() - INTERVAL '7 days' + INTERVAL '3 minutes'),
                (114, 121, 111, 'orange_money', 3500.00, 'XAF', 'completed', 'TXN-DE1F234567890123', 'OM-REF-2406-004', '{\"network\":\"orange\",\"phone\":\"+237691234511\"}', NOW() - INTERVAL '14 days', NOW(), NOW() - INTERVAL '14 days' + INTERVAL '5 minutes'),
                (115, 124, 117, 'mtn_momo', 5500.00, 'XAF', 'completed', 'TXN-EF12345678901234', 'MTN-REF-2406-007', '{\"network\":\"mtn\",\"phone\":\"+237691234517\"}', NOW() - INTERVAL '3 days', NOW(), NOW() - INTERVAL '3 days' + INTERVAL '4 minutes'),

                -- More payments for dashboard revenue
                (116, 122, 112, 'mtn_momo', 3500.00, 'XAF', 'completed', 'TXN-F123456789012345', 'MTN-REF-2406-008', '{\"network\":\"mtn\",\"phone\":\"+237691234512\"}', NOW() - INTERVAL '14 days', NOW(), NOW() - INTERVAL '14 days' + INTERVAL '3 minutes'),
                (117, 123, 114, 'orange_money', 8000.00, 'XAF', 'completed', 'TXN-1234567890123456', 'OM-REF-2406-005', '{\"network\":\"orange\",\"phone\":\"+237691234514\"}', NOW() - INTERVAL '6 days', NOW(), NOW() - INTERVAL '6 days' + INTERVAL '4 minutes'),

                -- Failed / refunded payments
                (118, 125, 113, 'mtn_momo', 3500.00, 'XAF', 'refunded', 'TXN-2345678901234567', 'MTN-REF-2406-009', '{\"network\":\"mtn\",\"phone\":\"+237691234513\"}', NOW() - INTERVAL '10 days', NOW(), NOW() - INTERVAL '9 days'),
                (119, 125, 113, 'mtn_momo', 3500.00, 'XAF', 'failed', 'TXN-3456789012345678', NULL, '{\"network\":\"mtn\",\"phone\":\"+237691234513\"}', NOW() - INTERVAL '10 days', NOW(), NULL)
            ON CONFLICT (id) DO NOTHING;
        ");

        // ────────────────────────────────────────────────────
        // 8. REVIEWS
        // ────────────────────────────────────────────────────
        $this->addSql("
            INSERT INTO reviews (id, user_id, agency_id, booking_id, rating, comment, is_verified, created_at, updated_at)
            VALUES
                -- Reviews for Buca Voyages (agency 101)
                (101, 111, 101, 101, 5, 'Très bon service, bus propre et confortable. Arrivée à l''heure.', true, NOW() - INTERVAL '2 days', NOW()),
                (102, 111, 101, 102, 4, 'Bon voyage mais la climatisation était trop forte.', true, NOW() - INTERVAL '1 day', NOW()),
                (103, 112, 101, 104, 3, 'Voyage correct, un peu de retard à l''arrivée.', true, NOW() - INTERVAL '1 day', NOW()),
                (104, 114, 101, 110, 5, 'Excellent service, chauffeur très professionnel.', true, NOW() - INTERVAL '2 days', NOW()),
                (105, 117, 101, 116, 4, 'Très bien, sièges confortables et wifi fonctionnel.', true, NOW() - INTERVAL '1 day', NOW()),
                (106, 111, 101, 103, 5, 'Trajet Yaoundé-Douala parfait, je recommande Buca Voyages!', true, NOW() - INTERVAL '7 days', NOW()),

                -- Reviews for Garantie Express (agency 102)
                (107, 112, 102, 105, 4, 'Bon rapport qualité-prix pour Douala-Bafoussam.', true, NOW() - INTERVAL '1 day', NOW()),
                (108, 113, 102, 108, 5, 'Très satisfaite du voyage, bus climatisé et ponctuel.', false, NOW() - INTERVAL '2 days', NOW()),
                (109, 114, 102, 109, 3, 'Voyage correct mais peut mieux faire au niveau de la ponctualité.', true, NOW() - INTERVAL '2 days', NOW()),
                (110, 112, 102, 106, 2, 'Bus vétuste et clim en panne, déçu du service.', false, NOW(), NOW()),

                -- Reviews for Finex Voyages (agency 103)
                (111, 113, 103, 107, 5, 'Finex Voyages au top! Bus grand confort pour Bertoua.', true, NOW() - INTERVAL '3 days', NOW()),
                (112, 113, 103, 108, 4, 'Retour Bertoua-Yaoundé parfait. Bon chauffeur.', true, NOW() - INTERVAL '2 days', NOW()),
                (113, 117, 103, 124, 5, 'Excellent trajet Yaoundé-Bertoua avec Finex.', true, NOW() - INTERVAL '3 days', NOW()),
                (114, 113, 103, 125, 2, 'Annulation de dernière minute sans explication.', true, NOW() - INTERVAL '10 days', NOW()),

                -- Reviews for Confort Plus (agency 104)
                (115, 115, 104, 109, 4, 'Premier voyage avec Confort Plus, très bonne impression.', false, NOW(), NOW()),

                -- Reviews for Général Transport (agency 105)
                (116, 113, 105, 110, 1, 'Voyage très inconfortable, bus en mauvais état.', false, NOW() - INTERVAL '6 days', NOW()),
                (117, 111, 105, 111, 2, 'Service médiocre, beaucoup de retard.', false, NOW() - INTERVAL '5 days', NOW())
            ON CONFLICT (id) DO NOTHING;
        ");

        // ────────────────────────────────────────────────────
        // 9. NOTIFICATIONS
        // ────────────────────────────────────────────────────
        $this->addSql("
            INSERT INTO notifications (id, user_id, type, title, message, data, is_read, created_at)
            VALUES
                -- Notifications for Alice Ngo (111)
                (101, 111, 'booking', 'Réservation confirmée', 'Votre réservation BK-2406-001 Yaoundé→Douala est confirmée.', '{\"booking_id\":101,\"reference\":\"BK-2406-001\"}', true, NOW() - INTERVAL '2 days'),
                (102, 111, 'payment', 'Paiement reçu', 'Votre paiement de 3 500 FCFA pour BK-2406-001 a été reçu.', '{\"payment_id\":101,\"amount\":3500}', true, NOW() - INTERVAL '2 days'),
                (103, 111, 'trip', 'Trajet terminé', 'Votre trajet Yaoundé→Douala du 16/06 est terminé. Laissez un avis!', '{\"trip_id\":101}', false, NOW() - INTERVAL '1 day'),
                (104, 111, 'booking', 'Réservation confirmée', 'Votre réservation BK-2406-002 Douala→Bafoussam est confirmée.', '{\"booking_id\":102,\"reference\":\"BK-2406-002\"}', true, NOW() - INTERVAL '1 day'),

                -- Notifications for David Mvogo (112)
                (105, 112, 'booking', 'Réservation confirmée', 'Votre réservation BK-2406-004 Douala→Yaoundé est confirmée.', '{\"booking_id\":104,\"reference\":\"BK-2406-004\"}', true, NOW() - INTERVAL '1 day'),
                (106, 112, 'payment', 'Paiement reçu', 'Votre paiement de 3 500 FCFA a été reçu avec succès.', '{\"payment_id\":103,\"amount\":3500}', true, NOW() - INTERVAL '1 day'),
                (107, 112, 'system', 'Rappel de voyage', 'Votre départ Douala→Yaoundé est dans 1 heure. Présentez-vous à la gare.', '{\"booking_id\":106}', false, NOW()),

                -- Notifications for Esther Mbah (113)
                (108, 113, 'booking', 'Réservation confirmée', 'BK-2406-007 Yaoundé→Bertoua confirmé. Bon voyage!', '{\"booking_id\":107,\"reference\":\"BK-2406-007\"}', true, NOW() - INTERVAL '3 days'),
                (109, 113, 'payment', 'Paiement reçu', 'Votre paiement de 5 500 FCFA a été reçu.', '{\"payment_id\":106,\"amount\":5500}', true, NOW() - INTERVAL '3 days'),
                (110, 113, 'promotion', 'Promo spéciale', 'Profitez de -20% sur votre prochain trajet vers Douala avec Buca Voyages!', '{\"code\":\"PROMO-20-BUCA\"}', false, NOW()),

                -- Notifications for François Kamga (114)
                (111, 114, 'booking', 'Paiement en attente', 'Votre paiement pour BK-2406-011 est en attente. Veuillez procéder au règlement.', '{\"booking_id\":111}', false, NOW()),
                (112, 114, 'booking', 'Réservation confirmée', 'BK-2406-010 Yaoundé→Douala validé. Bon voyage!', '{\"booking_id\":110,\"reference\":\"BK-2406-010\"}', true, NOW() - INTERVAL '2 days'),

                -- Admin notifications
                (113, 101, 'system', 'Nouvelle agence en attente', 'Confort Plus demande sa vérification. Vérifiez les documents.', '{\"agency_id\":104}', false, NOW() - INTERVAL '1 day'),
                (114, 101, 'system', 'Rapport hebdomadaire', 'Le rapport de la semaine est prêt. 28 trajets effectués, 45 réservations.', '{}', false, NOW()),

                -- Agency owner notifications
                (115, 103, 'booking', 'Nouvelle réservation', 'Un client a réservé 2 places sur Yaoundé→Douala de 14h00.', '{\"trip_id\":104}', false, NOW()),
                (116, 103, 'review', 'Nouvel avis', 'Un client a laissé un avis 5 étoiles pour vos services.', '{\"review_id\":101}', true, NOW() - INTERVAL '2 days'),

                -- Driver notifications
                (117, 106, 'trip', 'Changement de départ', 'Votre trajet Yaoundé→Douala de 06h00 est confirmé avec 18 passagers.', '{\"trip_id\":103}', true, NOW()),
                (118, 106, 'system', 'Rappel maintenance', 'Le véhicule LT 4521 CM nécessite une révision dans 5 jours.', '{\"vehicle_id\":101}', false, NOW()),

                -- More system notifications
                (119, 101, 'system', 'Mise à jour système', 'La plateforme a été mise à jour avec de nouvelles fonctionnalités de suivi GPS.', '{}', false, NOW()),
                (120, 103, 'system', 'Objectif mensuel', 'Félicitations! Buca Voyages a atteint 85% de son objectif mensuel de réservations.', '{\"progress\":85}', false, NOW())
            ON CONFLICT (id) DO NOTHING;
        ");

        // ────────────────────────────────────────────────────
        // 10. UPDATE SEQUENCES
        // ────────────────────────────────────────────────────
        $this->addSql("SELECT setval('transport_modes_id_seq', GREATEST(COALESCE((SELECT MAX(id) FROM transport_modes), 1), 2))");
        $this->addSql("SELECT setval('users_id_seq', GREATEST(COALESCE((SELECT MAX(id) FROM users), 1), 123))");
        $this->addSql("SELECT setval('agencies_id_seq', GREATEST(COALESCE((SELECT MAX(id) FROM agencies), 1), 105))");
        $this->addSql("SELECT setval('vehicles_id_seq', GREATEST(COALESCE((SELECT MAX(id) FROM vehicles), 1), 112))");
        $this->addSql("SELECT setval('trips_id_seq', GREATEST(COALESCE((SELECT MAX(id) FROM trips), 1), 130))");
        $this->addSql("SELECT setval('bookings_id_seq', GREATEST(COALESCE((SELECT MAX(id) FROM bookings), 1), 130))");
        $this->addSql("SELECT setval('payments_id_seq', GREATEST(COALESCE((SELECT MAX(id) FROM payments), 1), 119))");
        $this->addSql("SELECT setval('reviews_id_seq', GREATEST(COALESCE((SELECT MAX(id) FROM reviews), 1), 117))");
        $this->addSql("SELECT setval('notifications_id_seq', GREATEST(COALESCE((SELECT MAX(id) FROM notifications), 1), 120))");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM notifications WHERE id >= 101');
        $this->addSql('DELETE FROM reviews WHERE id >= 101');
        $this->addSql('DELETE FROM payments WHERE id >= 101');
        $this->addSql('DELETE FROM bookings WHERE id >= 101');
        $this->addSql('DELETE FROM trips WHERE id >= 101');
        $this->addSql('DELETE FROM vehicles WHERE id >= 101');
        $this->addSql('DELETE FROM agencies WHERE id >= 101');
        $this->addSql('DELETE FROM users WHERE id >= 101');
        $this->addSql('DELETE FROM transport_modes WHERE id >= 3');
    }
}
