<?php

declare(strict_types=1);

namespace GeorgianTax\Vat\Exemption;

use GeorgianTax\Domain\Event\Gift;
use GeorgianTax\Domain\Party\PartyKind;
use GeorgianTax\Domain\Party\Status;
use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\Law\Citation;
use GeorgianTax\Vat\Supply;

/**
 * Exemptions decided by who the parties are:
 *   172.3      supplies to foreign diplomatic missions and their members — with credit;
 *   172.4.გ    free transfers to the state or a municipality — with credit;
 *   171.1.რ–ს  one free-industrial-zone company supplying another — without credit.
 */
final class PartyExemptions implements Exemption
{
    public function exempts(Supply $s): ?Outcome
    {
        $customer = $s->customer();

        if ($customer === null || $s->isImport()) {
            return null;
        }

        return match (true) {
            $customer->is(Status::DiplomaticMission)
                => $s->exemptWithCredit(new Citation('172.3'), 'Supply to a foreign diplomatic mission — no VAT, input credit kept.'),
            $s->event instanceof Gift && $customer->kind === PartyKind::State
                => $s->exemptWithCredit(new Citation('172.4.გ'), 'Free transfer to the state — no VAT, input credit kept.'),
            $customer->is(Status::FreeIndustrialZone) && $s->supplier()->is(Status::FreeIndustrialZone)
                => $s->exempt(new Citation('171.1.რ–ს'), 'Between free-industrial-zone companies — no VAT and no input credit.'),
            default => null,
        };
    }
}
