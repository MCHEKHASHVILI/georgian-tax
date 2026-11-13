<?php

declare(strict_types=1);

namespace GeorgianTax\Domain\Event;

use DateTimeImmutable;
use GeorgianTax\Domain\Goods\Commodity;
use GeorgianTax\Domain\Income\IncomeKind;
use GeorgianTax\Domain\Income\Place;
use GeorgianTax\Domain\Party\Party;
use InvalidArgumentException;
use Mchekhashvili\Money\Money;

/**
 * Excise goods imported or bought to be matured (დასავარგებელი) — spirit laid down to age, say.
 * Whoever imports or buys them may credit the excise paid or payable on them (189.5): on the
 * acquisition itself, not on a later supply, and without 189.1's cap at the excise on the goods
 * made from them. Excise answers with that credit, a negative `Levied`; no other tax is charged.
 *
 * The acquisition's own excise is charged as usual — the importer's at customs (an `Import`), the
 * seller's on its supply (a `Pay`) — and `$excise` is that amount, paid or payable. Once credited
 * here, the same goods are not an `ExciseInput` of a later supply again; that is for the caller.
 */
final readonly class AcquireForMaturing implements TaxableEvent
{
    public function __construct(
        public Party $acquirer,
        public Commodity $commodity,
        /** The excise paid or payable on the goods acquired (189.5). */
        public Money $excise,
        public DateTimeImmutable $on,
        /** Imported by the acquirer (true) or bought in Georgia (false); 189.5 credits both. */
        public bool $imported = false,
        public string $description = 'excise goods for maturing',
    ) {
        if ($excise->isNegative()) {
            throw new InvalidArgumentException('The excise on goods acquired for maturing cannot be negative.');
        }
    }

    public function recipient(): Party { return $this->acquirer; }
    public function payer(): ?Party { return null; }
    public function kind(): IncomeKind { return IncomeKind::Other; }
    public function income(): Money { return Money::GEL(0); }
    public function date(): DateTimeImmutable { return $this->on; }
    public function place(): Place { return Place::georgia(); }
}
