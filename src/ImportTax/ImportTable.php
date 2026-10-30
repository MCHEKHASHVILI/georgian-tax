<?php

declare(strict_types=1);

namespace GeorgianTax\ImportTax;

use GeorgianTax\Domain\Goods\Commodity;

/** The Art. 197 lists and 199.პ exemptions as configuration, matched by HS prefix. */
final readonly class ImportTable
{
    /**
     * @param list<string> $twelve
     * @param list<string> $five
     * @param array<string, array{0: float, 1: string, 2: string}> $alcohol HS prefix => [euro, 'per_litre'|'per_vol_hl', description]
     * @param list<string> $exempt
     * @param list<string> $exemptExcept
     */
    public function __construct(
        private array $twelve,
        private array $five,
        private array $alcohol,
        private array $exempt,
        private array $exemptExcept,
    ) {}

    public static function standard(): self
    {
        $c = require __DIR__ . '/../../config/import.php';

        return new self($c['12'], $c['5'], $c['alcohol'], $c['exempt'], $c['exempt_except']);
    }

    /** 12, 5, or 0 — the percentage list the goods are on. */
    public function percentFor(Commodity $c): int
    {
        return match (true) {
            self::listed($c, $this->twelve) => 12,
            self::listed($c, $this->five) => 5,
            default => 0,
        };
    }

    /** @return array{euro: float, measure: string, description: string}|null */
    public function alcoholFor(Commodity $c): ?array
    {
        $best = null;

        foreach ($this->alcohol as $prefix => [$euro, $measure, $description]) {
            $digits = preg_replace('/\D/', '', (string) $prefix);

            if ($c->startsWith($digits) && strlen($digits) > strlen($best['prefix'] ?? '')) {
                $best = ['euro' => (float) $euro, 'measure' => $measure, 'description' => $description, 'prefix' => $digits];
            }
        }

        return $best;
    }

    public function exemptByCode(Commodity $c): bool
    {
        return self::listed($c, $this->exempt) && !self::listed($c, $this->exemptExcept);
    }

    /** @param list<string> $codes */
    private static function listed(Commodity $c, array $codes): bool
    {
        return array_any($codes, fn (string $code) => $c->startsWith($code));
    }
}
