<?php

declare(strict_types=1);

namespace GeorgianTax\Domain\Goods;

/**
 * Physical goods as excise and import tax see them: an HS code (the national nomenclature, SES ESN)
 * and the measurements their rates are set in. Fill in only what the goods have — a car needs
 * engine size and year; a spirit needs litres and alcohol strength.
 */
final readonly class Commodity
{
    public function __construct(
        /** HS code, spaces optional: '8703 23', '2208 30', '2402 20'. */
        public string $hsCode,
        /** Quantity in the unit the rate uses: litres, kg, tonnes, cubic metres, sticks, minutes or pieces. */
        public float $quantity = 1.0,
        /** Alcohol by volume, in percent. */
        public ?float $alcoholPercent = null,
        /** Engine displacement in cm³, for cars and motorcycles. */
        public ?int $engineCc = null,
        /** Year of manufacture, for cars and motorcycles. */
        public ?int $yearMade = null,
        public bool $hybrid = false,
        public bool $electric = false,
        public bool $rightHandDrive = false,
        public bool $sportsCar = false,
        public bool $classicCar = false,
        /** Excise 188.1¹ — retail price per 20 sticks, for cigarettes, cigarillos and heated tobacco. */
        public ?float $retailPricePer20 = null,
        /** Excise 188.11 — locally made cigarettes within the 35-million-pack annual quota. */
        public bool $localQuotaCigarettes = false,
    ) {}

    /** The code as bare digits, for prefix matching. */
    public function digits(): string
    {
        return preg_replace('/\D/', '', $this->hsCode);
    }

    public function startsWith(string $prefix): bool
    {
        return str_starts_with($this->digits(), preg_replace('/\D/', '', $prefix));
    }

    /** Age as excise and import tax count it: event year minus year made (188.18). */
    public function ageIn(int $year): ?int
    {
        return $this->yearMade === null ? null : max(0, $year - $this->yearMade);
    }

    public function isPassengerCar(): bool
    {
        return $this->startsWith('8703');
    }

    public function isMotorcycle(): bool
    {
        return $this->startsWith('8711');
    }

    /** Litres of pure alcohol. */
    public function pureAlcoholLitres(): float
    {
        return $this->quantity * ($this->alcoholPercent ?? 0) / 100;
    }
}
