<?php

declare(strict_types=1);

namespace GeorgianTax\IncomeTax\Exemption;

use GeorgianTax\Domain\Income\IncomeKind;
use GeorgianTax\Domain\Party\Status;
use Mchekhashvili\TaxLegislation\Engine\Outcome\Exempt;
use GeorgianTax\IncomeTax\Assessment;
use Mchekhashvili\TaxLegislation\Law\Citation;

/**
 * Income exempt because of what it is — a table rather than thirty classes, since each row is
 * "this kind of income, optionally from this kind of payer, optionally until a date".
 * The caller asserts the conditions the IncomeKind name carries (e.g. LiquidationRealEstate means
 * the share was held more than 2 years).
 */
final class ExemptByNature implements Exemption
{
    /** @var list<array{0: IncomeKind, 1: string, 2: string, 3?: ?Status, 4?: ?string}> kind, article, reason, required payer status, valid until */
    private const TABLE = [
        [IncomeKind::Grant, '82.1.ბ', 'Grant'],
        [IncomeKind::StatePension, '82.1.ბ', 'State pension'],
        [IncomeKind::StateCompensation, '82.1.ბ', 'State compensation'],
        [IncomeKind::StateScholarship, '82.1.ბ', 'State scholarship'],
        [IncomeKind::BudgetAid, '82.1.ბ', 'Aid or one-off payment from budget allocations'],
        [IncomeKind::CharityFromStateNonProfit, '82.1.ბ¹', 'Charity from a state-founded non-profit', Status::StateFoundedNonProfit],
        [IncomeKind::CharityForMedicalTreatment, '82.1.ბ²', 'Charity to fund medical treatment', Status::CharityOrganization],
        [IncomeKind::AccumulativePensionPayout, '82.1.ბ³', 'Accumulative pension contribution, return or payout'],
        [IncomeKind::PrivatePensionPayout, '82.1.ბ⁴.გ', 'Private pension paid at retirement age, early retirement or disability'],
        [IncomeKind::SportsAward, '82.1.გ', 'Olympic or championship award'],
        [IncomeKind::Alimony, '82.1.დ', 'Alimony'],
        [IncomeKind::DivorceSettlement, '82.1.ე', 'Property received on divorce'],
        [IncomeKind::BloodDonation, '82.1.კ¹', 'Payment for donating blood', null, '2030-04-01'],
        [IncomeKind::PrivatizationBenefit, '82.1.მ', 'Benefit from privatization or disaster replacement housing'],
        [IncomeKind::LandOwnershipRecognition, '82.1.მ¹', 'Benefit from recognition of land ownership'],
        [IncomeKind::DisplacedPersonCompensation, '82.1.ნ', 'Compensation or property for refugees and displaced persons'],
        [IncomeKind::ServiceInjuryAid, '82.1.ქ', 'Aid for a service member injured or killed on duty'],
        [IncomeKind::PartnershipDistribution, '82.1.ყ', 'Distribution from an individuals-only partnership'],
        [IncomeKind::LiquidationRealEstate, '82.1.ჩ', 'Real estate received on liquidation for a share held over 2 years'],
        [IncomeKind::CharityToVulnerable, '82.1.წ', 'Charity to a vulnerable family or a war-disabled person', Status::CharityOrganization],
        [IncomeKind::RealEstateFromAdministrativeEmployer, '82.1.ჭ', 'Real estate transferred by an administrative-body employer', Status::AdministrativeBody],
        [IncomeKind::FuelFromAdministrativeBody, '82.1.ჭ¹', 'Fuel for a personal car used on duty', Status::AdministrativeBody],
        [IncomeKind::FreeGuaranteeOrServitude, '82.1.ხ', 'Free guarantee or servitude'],
        [IncomeKind::FreeHotelStay, '82.1.ჯ', 'Free hotel stay of up to 60 days for a room owner', Status::TouristEnterprise],
        [IncomeKind::NotaryChamberAid, '82.1.ჯ¹', 'Chamber of Notaries aid in an underserved settlement'],
        [IncomeKind::ExpropriationCompensation, '82.1.ჰ²', 'Compensation for expropriated property'],
        [IncomeKind::MicroPowerSurplus, '82.1.ჰ⁴', 'Surplus electricity from a micro power plant sold to the grid'],
        [IncomeKind::InsuranceIndemnity, '103.1.ბ', 'Health insurance indemnity, or indemnity not exceeding the damage'],
        [IncomeKind::PartnerContribution, '103.1.ა', 'Partner contribution to capital'],
    ];

    public function exempts(Assessment $a): ?Exempt
    {
        foreach (self::TABLE as $row) {
            [$kind, $article, $reason] = $row;
            $payerStatus = $row[3] ?? null;
            $until = $row[4] ?? null;

            if (!$a->is($kind)) {
                continue;
            }

            if ($payerStatus !== null && !$a->payerIs($payerStatus)) {
                continue;
            }

            if ($until !== null && !$a->before($until)) {
                continue;
            }

            return $a->exempt(new Citation($article), "$reason is exempt.");
        }

        return null;
    }
}
