<?php

declare(strict_types=1);

namespace GeorgianTax\Domain\Income;

/** Where work, a service or a supply happens — feeds the Art. 104 source rules. */
final readonly class Place
{
    public function __construct(
        public string $performedIn = 'GE',
        public bool $tiedToPropertyInGeorgia = false,   // 104.1.გ.ბ–გ.გ, ლ, მ
        public bool $providerProvedForeignPE = false,   // escape from 104.1.გ.ზ
        public ?string $shippedTo = null,               // goods transported to this country — export (VAT 172.4.ე)
        public bool $tiedToPropertyAbroad = false,      // VAT 162¹.4 — service on immovable property outside Georgia
    ) {}

    public static function georgia(): self
    {
        return new self('GE');
    }

    public static function abroad(string $country, bool $providerProvedForeignPE = false): self
    {
        return new self($country, providerProvedForeignPE: $providerProvedForeignPE);
    }

    /** Goods leaving Georgia: supplied here, transported abroad. */
    public static function exportTo(string $country): self
    {
        return new self('GE', shippedTo: $country);
    }

    public function isExport(): bool
    {
        return $this->isGeorgia() && $this->shippedTo !== null && $this->shippedTo !== 'GE';
    }

    public function isGeorgia(): bool
    {
        return $this->performedIn === 'GE';
    }
}
