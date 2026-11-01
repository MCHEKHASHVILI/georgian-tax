<?php

declare(strict_types=1);

namespace GeorgianTax\Excise;

use GeorgianTax\Domain\Goods\Commodity;

/** The Art. 188.1 table as configuration, matched by the longest HS prefix. */
final readonly class ExciseTable
{
    /** @param array<string, array{0: string, 1: float, 2: string}> $rows HS prefix => [unit, GEL per unit, description] */
    public function __construct(private array $rows) {}

    public static function standard(?string $path = null): self
    {
        return new self(require $path ?? __DIR__ . '/../../resources/law/excise.php');
    }

    /** @return array{unit: string, rate: float, description: string, prefix: string}|null */
    public function find(Commodity $commodity): ?array
    {
        $best = null;

        foreach ($this->rows as $prefix => [$unit, $rate, $description]) {
            $digits = preg_replace('/\D/', '', (string) $prefix);

            if ($commodity->startsWith($digits) && strlen($digits) > strlen($best['prefix'] ?? '')) {
                $best = ['unit' => $unit, 'rate' => (float) $rate, 'description' => $description, 'prefix' => $digits];
            }
        }

        return $best;
    }
}
