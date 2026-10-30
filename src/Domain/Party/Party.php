<?php

declare(strict_types=1);

namespace GeorgianTax\Domain\Party;

use DateTimeImmutable;

/**
 * Anyone who pays or receives: a person, an entrepreneur, a company, an organization or the state.
 * Residency is per tax year (Art. 34.8) — resolve it with ResidencyResolver before building the Party.
 */
final readonly class Party
{
    /**
     * @param list<Status> $statuses
     * @param array<string, DateTimeImmutable> $statusSince  keyed by Status name, for time-limited statuses
     * @param list<Relief> $reliefs
     */
    public function __construct(
        public string $id,
        public PartyKind $kind,
        public Residency $residency = Residency::Resident,
        public string $country = 'GE',
        public string $citizenship = 'GE',
        public Regime $regime = Regime::General,
        public bool $hasGeorgianPE = false,
        public array $statuses = [],
        public array $statusSince = [],
        public array $reliefs = [],
    ) {}

    public static function person(string $id, Status|Relief ...$tags): self
    {
        return new self($id, PartyKind::Individual, ...self::split($tags));
    }

    public static function company(string $id, Status ...$statuses): self
    {
        return new self($id, PartyKind::Company, statuses: $statuses);
    }

    public static function foreignCompany(string $id, string $country, Status ...$statuses): self
    {
        return new self($id, PartyKind::Company, Residency::NonResident, $country, $country, statuses: $statuses);
    }

    public static function foreigner(string $id, string $country): self
    {
        return new self($id, PartyKind::Individual, Residency::NonResident, $country, $country);
    }

    public static function state(): self
    {
        return new self('state', PartyKind::State);
    }

    public function is(Status ...$statuses): bool
    {
        return array_any($statuses, fn (Status $s) => in_array($s, $this->statuses, true));
    }

    public function isResident(): bool
    {
        return $this->residency === Residency::Resident;
    }

    public function isIndividual(): bool
    {
        return $this->kind->isIndividual();
    }

    /** Non-resident whose income does not belong to a Georgian permanent establishment — Art. 134.1. */
    public function isNonResidentWithoutPE(): bool
    {
        return !$this->isResident() && !$this->hasGeorgianPE;
    }

    public function since(Status $status): ?DateTimeImmutable
    {
        return $this->statusSince[$status->name] ?? null;
    }

    public function with(Regime $regime): self
    {
        return new self(
            $this->id, $this->kind, $this->residency, $this->country, $this->citizenship,
            $regime, $this->hasGeorgianPE, $this->statuses, $this->statusSince, $this->reliefs,
        );
    }

    public function withKind(PartyKind $kind): self
    {
        return new self(
            $this->id, $kind, $this->residency, $this->country, $this->citizenship,
            $this->regime, $this->hasGeorgianPE, $this->statuses, $this->statusSince, $this->reliefs,
        );
    }

    /** @param list<Status|Relief> $tags */
    private static function split(array $tags): array
    {
        return [
            'statuses' => array_values(array_filter($tags, fn ($t) => $t instanceof Status)),
            'reliefs' => array_values(array_filter($tags, fn ($t) => $t instanceof Relief)),
        ];
    }
}
