<?php

declare(strict_types=1);

namespace GeorgianTax\IncomeTax\Stage;

use Closure;
use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\IncomeTax\Assessment;
use GeorgianTax\IncomeTax\Source\SourceRule;
use GeorgianTax\Law\Article;
use GeorgianTax\Law\Citation;

/**
 * Decides whether the income is from a Georgian source (Art. 104). The first rule with an opinion wins.
 * Foreign-source income is exempt for residents (82.1.ფ) and outside the tax for non-residents (79.ბ).
 */
#[Article('104', 'Income from a Georgian source')]
final readonly class ResolveSource implements Stage
{
    /** @param list<SourceRule> $rules */
    public function __construct(private array $rules) {}

    public function __invoke(Assessment $a, Closure $next): Outcome
    {
        $georgian = $this->decide($a);

        if ($georgian) {
            return $next($a->withGeorgianSource(true));
        }

        return $a->taxpayer()->isResident()
            ? $a->exempt(new Citation('82.1.ფ'), "A resident individual's foreign-source income is exempt.")
            : $a->notTaxable(new Citation('79.ბ'), 'A non-resident is taxed only on Georgian-source income.');
    }

    private function decide(Assessment $a): bool
    {
        foreach ($this->rules as $rule) {
            $decision = $rule->decide($a);

            if ($decision !== null) {
                return $decision;
            }
        }

        return false;
    }
}
