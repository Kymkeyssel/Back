<?php

namespace App\Domain;

/**
 * Périmètre produit : uniquement voyages interurbains en bus (ligne / autocar / minibus)
 * et covoiturage entre villes. Aucun autre mode (taxi, fret, ferry, etc.).
 */
final class TransportScope
{
    public const INTERCITY_BUS = 'INTERCITY_BUS';

    public const CARPOOL = 'CARPOOL';

    /** @var list<string> */
    public const OFFER_TYPE_CODES = [self::INTERCITY_BUS, self::CARPOOL];

    /** Types de véhicule autorisés pour le bus interurbain (ligne). */
    public const INTERCITY_VEHICLE_TYPES = ['bus', 'minibus'];

    /** Type de véhicule attendu pour le covoiturage (véhicule particulier). */
    public const CARPOOL_VEHICLE_TYPE = 'car';

    /** @var list<string> */
    public const ALL_VEHICLE_TYPES = ['bus', 'minibus', self::CARPOOL_VEHICLE_TYPE];
}
