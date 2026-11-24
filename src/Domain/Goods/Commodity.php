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
        /**
         * Excise 188¹.1, note ბ.ბ — a drink of 2205 or 2206 over 10% and up to 15% whose alcohol
         * all comes from fermentation is an "other fermented drink" (row 2), otherwise an
         * intermediate drink (row 4). Null when unknown.
         */
        public ?bool $fullyFermented = null,
        /**
         * A side-car (ეტლი) presented on its own. Heading 8711 names them ("ეტლით ან მის გარეშე;
         * ეტლები") but no subheading gives them a code of their own, so the caller says so. Excise
         * 188.1 row 4¹ leaves them out of motorcycles ("გარდა 8711 სასაქონლო პოზიციით
         * გათვალისწინებული ეტლისა"). A motorcycle fitted with one is still a motorcycle.
         */
        public bool $sideCar = false,
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

    /** Heading 8711 but a side-car on its own (188.1 row 4¹). */
    public function isMotorcycle(): bool
    {
        return $this->startsWith('8711') && !$this->sideCar;
    }

    /** A side-car of heading 8711 presented on its own. */
    public function isSideCar(): bool
    {
        return $this->startsWith('8711') && $this->sideCar;
    }

    /** Litres of pure alcohol. */
    public function pureAlcoholLitres(): float
    {
        return $this->quantity * ($this->alcoholPercent ?? 0) / 100;
    }
}
