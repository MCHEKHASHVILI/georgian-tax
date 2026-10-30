<?php

declare(strict_types=1);

namespace GeorgianTax\IncomeTax\Exemption;

use GeorgianTax\Domain\Asset\AssetType;
use GeorgianTax\Domain\Event\Sell;
use GeorgianTax\Domain\Income\IncomeKind;
use GeorgianTax\Engine\Outcome\Exempt;
use GeorgianTax\IncomeTax\Assessment;
use GeorgianTax\Law\Citation;

/**
 * Securities the state wants traded:
 *   82.1.ტ  interest on state, NBG, Deposit Insurance Agency or IFI bonds;
 *   82.1.უ  gain on those bonds;
 *   82.1.უ¹ gain on listed Georgian bonds;
 *   82.1.უ² interest on listed Georgian bonds, until 1 January 2028;
 *   82.1.უ³ gain on listed Georgian shares;
 *   131.8   interest on a Georgian company's bonds listed on a recognized foreign exchange.
 */
final class ListedSecurities implements Exemption
{
    public function exempts(Assessment $a): ?Exempt
    {
        if ($a->event instanceof Sell) {
            return match ($a->event->asset->type) {
                AssetType::StateOrIfiBonds => $a->exempt(new Citation('82.1.უ'), 'Gain on state or IFI bonds is exempt.'),
                AssetType::ListedGeorgianBonds => $a->exempt(new Citation('82.1.უ¹'), 'Gain on listed Georgian bonds is exempt.'),
                AssetType::ListedGeorgianShares => $a->exempt(new Citation('82.1.უ³'), 'Gain on listed Georgian shares is exempt.'),
                default => null,
            };
        }

        $payment = $a->payment();

        if ($payment === null || !$a->is(IncomeKind::Interest)) {
            return null;
        }

        return match (true) {
            $payment->instrument === AssetType::StateOrIfiBonds
                => $a->exempt(new Citation('82.1.ტ'), 'Interest on state or IFI bonds is exempt.'),
            $payment->instrument === AssetType::ListedGeorgianBonds && $payment->listedAbroad
                => $a->exempt(new Citation('131.8'), 'Interest on Georgian bonds listed abroad is exempt.'),
            $payment->instrument === AssetType::ListedGeorgianBonds && $a->before('2028-01-01')
                => $a->exempt(new Citation('82.1.უ²'), 'Interest on listed Georgian bonds is exempt until 2028.'),
            default => null,
        };
    }
}
