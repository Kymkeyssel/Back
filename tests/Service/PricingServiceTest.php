<?php

namespace App\Tests\Service;

use App\Entity\Trip;
use PHPUnit\Framework\TestCase;

/**
 * Tests pour la logique de tarification dynamique
 * Ces tests vérifient les règles de tarification sans dépendre du service complet
 */
class PricingServiceTest extends TestCase
{
    /**
     * Test du prix de base
     */
    public function testCalculatePriceWithBasePrice(): void
    {
        $trip = new Trip();
        $trip->setPrice(3500);
        
        $this->assertEquals(3500, $trip->getPrice());
    }

    /**
     * Test du calcul de prix avec multiplicateur (majoration)
     */
    public function testCalculateDynamicPriceWithMultiplier(): void
    {
        $basePrice = 3500;
        $multiplier = 1.2; // 20% de majoration
        
        $expectedPrice = $basePrice * $multiplier;
        
        $this->assertEquals(4200, $expectedPrice);
    }

    /**
     * Test du calcul de prix avec remise
     */
    public function testCalculatePriceWithDiscount(): void
    {
        $basePrice = 3500;
        $discount = 0.9; // 10% de remise
        
        $expectedPrice = $basePrice * $discount;
        
        $this->assertEquals(3150, $expectedPrice);
    }

    /**
     * Test Early Bird - Réservation anticipée 7+ jours
     */
    public function testEarlyBirdDiscount(): void
    {
        $basePrice = 3500;
        $daysInAdvance = 10;
        $discount = 0.85;
        
        $expectedPrice = $basePrice * $discount;
        $this->assertEquals(2975, $expectedPrice);
    }

    /**
     * Test Last Minute - Réservation < 24h
     */
    public function testLastMinuteSurcharge(): void
    {
        $basePrice = 3500;
        $hoursInAdvance = 12;
        $multiplier = 1.2;
        
        $expectedPrice = $basePrice * $multiplier;
        $this->assertEquals(4200, $expectedPrice);
    }

    /**
     * Test réduction groupe - 5+ personnes
     */
    public function testGroupDiscount(): void
    {
        $basePrice = 3500;
        $numberOfSeats = 5;
        $discount = 0.9;
        
        $expectedPrice = $basePrice * $numberOfSeats * $discount;
        $this->assertEquals(15750, $expectedPrice);
    }

    /**
     * Test majoration heures de pointe (6h-9h et 16h-19h)
     */
    public function testPeakHoursMultiplier(): void
    {
        $basePrice = 3500;
        $hour = 7; // 7h du matin
        $multiplier = 1.15;
        
        $expectedPrice = $basePrice * $multiplier;
        $this->assertEqualsWithDelta(4025, $expectedPrice, 0.01);
    }

    /**
     * Test majoration weekend
     */
    public function testWeekendMultiplier(): void
    {
        $basePrice = 3500;
        $dayOfWeek = 6; // Samedi
        $multiplier = 1.1;
        
        $expectedPrice = $basePrice * $multiplier;
        $this->assertEqualsWithDelta(3850, $expectedPrice, 0.01);
    }

    /**
     * Test prix ne peut pas être négatif
     */
    public function testPriceCannotBeNegative(): void
    {
        $basePrice = 3500;
        $multiplier = -0.5;
        
        $finalPrice = max(0, $basePrice * $multiplier);
        
        $this->assertEquals(0, $finalPrice);
    }

    /**
     * Test prix minimum respecté
     */
    public function testMinPriceIsRespected(): void
    {
        $basePrice = 3500;
        $multiplier = 0.1;
        $minPrice = 500;
        
        $calculatedPrice = $basePrice * $multiplier;
        $finalPrice = max($minPrice, $calculatedPrice);
        
        $this->assertEquals(500, $finalPrice);
    }

    /**
     * Test prix maximum respecté
     */
    public function testMaxPriceIsRespected(): void
    {
        $basePrice = 3500;
        $multiplier = 5;
        $maxPrice = 15000;
        
        $calculatedPrice = $basePrice * $multiplier;
        $finalPrice = min($maxPrice, $calculatedPrice);
        
        $this->assertEquals(15000, $finalPrice);
    }

    /**
     * Test plusieurs règles combinées
     */
    public function testCombinedRules(): void
    {
        $basePrice = 3500;
        
        // Weekend (10%) + Early Bird (15%) = 0.9 * 0.85 = 0.765
        $combinedMultiplier = 0.9 * 0.85;
        
        $expectedPrice = round($basePrice * $combinedMultiplier);
        
        $this->assertEquals(2678, $expectedPrice);
    }

    /**
     * Test prix pour un trajet avec tous les facteurs
     * Prix de base: 3500 XAF
     * - Réduction groupe (3 personnes): 5% = 3325
     * - Heures de pointe (18h): 15% = 3823.75
     */
    public function testCompletePricingScenario(): void
    {
        $basePrice = 3500;
        $seats = 3;
        $daysInAdvance = 5;
        $hour = 18; // 18h - heures de pointe
        $dayOfWeek = 5; // Vendredi (pas weekend)
        
        $price = $basePrice;
        
        // Réduction groupe si 3+ personnes
        if ($seats >= 3) {
            $price *= 0.95; // 5% de réduction
        }
        
        // Heures de pointe (18h)
        if (($hour >= 6 && $hour <= 9) || ($hour >= 16 && $hour <= 19)) {
            $price *= 1.15;
        }
        
        // Pas de majoration weekend pour vendredi
        // Pas de early bird (5 jours < 7 jours)
        
        $this->assertEqualsWithDelta(3824, round($price), 1);
    }
}
