<?php

declare(strict_types=1);

namespace GeorgianTax\Domain\Event;

use DateTimeImmutable;
use GeorgianTax\Domain\Income\IncomeKind;
use GeorgianTax\Domain\Income\Place;
use GeorgianTax\Domain\Party\Party;
use InvalidArgumentException;
use Mchekhashvili\Money\Money;

/**
 * A non-resident company without a Georgian PE closing a calendar year in which it was paid rent
 * for property by someone who is not a 154.1 tax agent — the event its profit tax looks at. That
 * rent is not withheld at source (97.4 sets 97.11 aside): the company is taxed on the year's gross
 * income from a Georgian source less the deductions connected with earning it (97.11), at 15% (98.1),
 * and declares by 1 April of the next year (153.1.გ).
 *
 * The figures are the caller's: the gross income and its deductions (Chapter XV) are not computed
 * here, nor is which of the year's Georgian-source income 97.11 reaches — rent withheld at source by
 * a tax agent under 134.1.ე is taxed there. `$lossesCarried` is an earlier year's excess of deductions
 * over gross income still within its 5-year term (121.4, a legal person's) or 10 years chosen under 122;
 * which years are still open is for the caller.
 *
 * It creates no income, so the other taxes pass it by.
 */
final readonly class CloseRentalYear implements TaxableEvent
{
    public function __construct(
        public Party $company,
        public int $year,
        /** The year's gross income from a Georgian source (97.11). */
        public Money $grossIncome,
        /** The deductions connected with earning it (97.11, Chapter XV). */
        public Money $deductions,
        /** Earlier years' losses still carried forward (121.4, 122). */
        public ?Money $lossesCarried = null,
    ) {
        foreach (['grossIncome' => $grossIncome, 'deductions' => $deductions, 'lossesCarried' => $lossesCarried] as $name => $amount) {
            if ($amount?->isNegative()) {
                throw new InvalidArgumentException("A year's $name cannot be negative.");
            }
        }
    }

    public function losses(): Money
    {
        return $this->lossesCarried ?? Money::zero($this->grossIncome->currency);
    }

    public function recipient(): Party { return $this->company; }
    public function payer(): ?Party { return null; }
    public function kind(): IncomeKind { return IncomeKind::Other; }
    public function income(): Money { return Money::GEL(0); }
    public function date(): DateTimeImmutable { return new DateTimeImmutable("{$this->year}-12-31"); }
    public function place(): Place { return Place::georgia(); }
}
