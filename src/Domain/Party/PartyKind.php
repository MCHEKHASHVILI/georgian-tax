<?php

declare(strict_types=1);

namespace GeorgianTax\Domain\Party;

enum PartyKind
{
    case Individual;              // natural person, not registered as an entrepreneur
    case IndividualEntrepreneur;  // Art. 36
    case Company;                 // enterprise, Art. 21
    case Organization;            // non-profit, budget, international, Art. 30
    case State;

    public function isIndividual(): bool
    {
        return $this === self::Individual || $this === self::IndividualEntrepreneur;
    }

    /** Art. 154.1 — a tax agent is a legal entity, enterprise, organization or individual entrepreneur. */
    public function canWithhold(): bool
    {
        return $this !== self::Individual;
    }
}
