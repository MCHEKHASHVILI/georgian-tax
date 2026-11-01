<?php

declare(strict_types=1);

namespace GeorgianTax\Ledger\Eloquent;

use DateTimeImmutable;
use GeorgianTax\Domain\Event\TaxableEvent;
use GeorgianTax\Domain\Income\HeirOrder;
use GeorgianTax\Domain\Income\IncomeKind;
use GeorgianTax\Domain\Party\Party;
use GeorgianTax\Engine\TaxReport;
use GeorgianTax\Ledger\Eloquent\Models\IncomeEntry;
use GeorgianTax\Ledger\Eloquent\Models\SpendingEntry;
use GeorgianTax\Ledger\Eloquent\Models\TurnoverEntry;
use GeorgianTax\Ledger\LedgerEntries;
use GeorgianTax\Ledger\YearToDate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\ConnectionResolverInterface;
use Mchekhashvili\Money\Money;

/** The year-to-date ledger in your database — the default in a Laravel app. */
final readonly class EloquentLedger implements YearToDate
{
    public function __construct(private ConnectionResolverInterface $db) {}

    public function received(Party $recipient, int $year, IncomeKind ...$kinds): Money
    {
        return $this->income(IncomeEntry::query()->where('recipient', $recipient->id)->where('year', $year)->ofKinds(...$kinds));
    }

    public function receivedFrom(Party $recipient, Party $payer, int $year, IncomeKind ...$kinds): Money
    {
        return $this->income(IncomeEntry::query()
            ->where('recipient', $recipient->id)->where('payer', $payer->id)->where('year', $year)->ofKinds(...$kinds));
    }

    public function receivedFromHeirs(Party $recipient, int $year, HeirOrder ...$orders): Money
    {
        return $this->income(IncomeEntry::query()->where('recipient', $recipient->id)->where('year', $year)->fromHeirs(...$orders));
    }

    public function paid(Party $payer, int $year, IncomeKind ...$kinds): Money
    {
        return $this->income(IncomeEntry::query()->where('payer', $payer->id)->where('year', $year)->ofKinds(...$kinds));
    }

    public function taxableIncome(Party $taxpayer, int $year): Money
    {
        return self::gel(IncomeEntry::query()->where('recipient', $taxpayer->id)->where('year', $year)->sum('taxable_base'));
    }

    public function turnover(Party $party, string $tax, DateTimeImmutable $since): Money
    {
        return self::gel(TurnoverEntry::query()
            ->where('party', $party->id)->where('tax', $tax)->where('occurred_on', '>=', $since->format('Y-m-d'))->sum('amount'));
    }

    public function spent(Party $company, int $year, string $category): Money
    {
        return self::gel(SpendingEntry::query()->where('company', $company->id)->where('year', $year)->where('category', $category)->sum('amount'));
    }

    public function record(TaxableEvent $event, TaxReport $report): void
    {
        $entries = LedgerEntries::for($event, $report);
        $connection = new IncomeEntry()->getConnectionName();

        $this->db->connection($connection)->transaction(function () use ($entries) {
            if (($i = $entries->income) !== null) {
                IncomeEntry::query()->create([
                    'recipient' => $i['recipient'], 'payer' => $i['payer'], 'kind' => $i['kind']->name,
                    'year' => $i['year'], 'occurred_on' => $i['on'], 'income' => $i['income'],
                    'heir' => $i['heir']?->name, 'taxable_base' => $i['taxableBase'],
                ]);
            }

            foreach ($entries->turnover as $t) {
                TurnoverEntry::query()->create(['party' => $t['party'], 'tax' => $t['tax'], 'occurred_on' => $t['on'], 'amount' => $t['amount']]);
            }

            if (($s = $entries->spending) !== null) {
                SpendingEntry::query()->create($s);
            }
        });
    }

    /** History from before the engine: income received in a year. */
    public function seed(Party $recipient, ?Party $payer, IncomeKind $kind, int $year, Money $income): void
    {
        IncomeEntry::query()->create([
            'recipient' => $recipient->id, 'payer' => $payer?->id, 'kind' => $kind->name, 'year' => $year,
            'occurred_on' => "$year-01-01", 'income' => $income, 'taxable_base' => Money::GEL(0),
        ]);
    }

    /** History from before the engine: turnover a tax counted. */
    public function seedTurnover(Party $party, string $tax, DateTimeImmutable $on, Money $amount): void
    {
        TurnoverEntry::query()->create(['party' => $party->id, 'tax' => $tax, 'occurred_on' => $on, 'amount' => $amount]);
    }

    /** @param Builder<IncomeEntry> $query */
    private function income(Builder $query): Money
    {
        return self::gel($query->sum('income'));
    }

    private static function gel(mixed $sum): Money
    {
        return Money::ofMinor((int) $sum, 'GEL');
    }
}
