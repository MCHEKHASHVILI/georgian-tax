<?php

declare(strict_types=1);

namespace GeorgianTax\Excise;

use GeorgianTax\Engine\Deadline;
use GeorgianTax\Domain\Event\Import;
use GeorgianTax\Domain\Event\TaxableEvent;
use GeorgianTax\Domain\Goods\Commodity;
use Mchekhashvili\Money\Money;
use GeorgianTax\Domain\Party\Party;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Collection;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Exempt;
use Mchekhashvili\TaxLegislation\Engine\Outcome\ExemptWithCredit;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Levied;
use Mchekhashvili\TaxLegislation\Engine\Outcome\NotTaxable;
use Mchekhashvili\TaxLegislation\Law\Citation;

/** The subject of the excise pipeline: excise goods, who is liable, and which operation. */
final readonly class Duty
{
    public const TAX = 'excise';

    public function __construct(
        public TaxableEvent $event,
        public ExciseTable $table,
        public ?Commodity $commodity = null,
        public ?Party $taxpayer = null,
        public ?Operation $operation = null,
    ) {}

    public function as(Commodity $commodity, Party $taxpayer, Operation $operation): self
    {
        return new self($this->event, $this->table, $commodity, $taxpayer, $operation);
    }

    public function year(): int
    {
        return (int) $this->event->date()->format('Y');
    }

    /**
     * Excise of $ratePerUnit per $unit on $quantity, plus any $extra (an ad-valorem part).
     * Paid with import duties at customs (190.3), otherwise by the 15th of next month (190.2).
     *
     * @param list<string> $notes
     */
    public function levied(object $basis, float $quantity, string $unit, float $ratePerUnit, ?Money $extra = null, array $notes = []): Levied
    {
        $collection = $this->event instanceof Import ? Collection::AtCustoms : Collection::MonthlyDeclaration;
        $amount = Money::GEL($quantity * $ratePerUnit)->plus($extra ?? Money::GEL(0));

        return new Levied(
            self::TAX, self::cite($basis), $this->taxpayer, $quantity, $unit, Money::GEL($ratePerUnit), $amount,
            $collection, Deadline::of($collection, $this->event->date()), $notes,
        );
    }

    public function exempt(object $basis, string $reason): Exempt
    {
        return new Exempt(self::TAX, self::cite($basis), $reason);
    }

    public function exemptWithCredit(object $basis, string $reason): ExemptWithCredit
    {
        return new ExemptWithCredit(self::TAX, self::cite($basis), $reason, $this->taxpayer, Money::GEL(0));
    }

    public function notTaxable(object $basis, string $reason): NotTaxable
    {
        return new NotTaxable(self::TAX, self::cite($basis), $reason);
    }

    private static function cite(object $basis): Citation
    {
        return $basis instanceof Citation ? $basis : Citation::of($basis);
    }
}
