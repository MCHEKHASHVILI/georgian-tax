<?php

declare(strict_types=1);

namespace GeorgianTax\Vat\Exemption;

use GeorgianTax\Domain\Income\SupplyCategory as C;
use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\Law\Citation;
use GeorgianTax\Vat\Supply;

/**
 * Art. 170–172 as a table: each supply category, its article, whether credit survives, and whether
 * it still counts toward the 100,000 GEL threshold (only 172.1–172.2, 165.7.გ). Financial and land
 * supplies may be taxed by choice (169.5) — leave the category off the event to do that.
 */
final class ExemptCategories implements Exemption
{
    private const WITH_CREDIT = true;
    private const WITHOUT_CREDIT = false;

    /** @var list<array{0: C, 1: string, 2: string, 3: bool, 4?: bool}> category, article, reason, credit, counts toward threshold */
    private const TABLE = [
        // Art. 172 — exempt with credit
        [C::ShipOrAircraftSupplies, '172.1.ა–ზ', 'Supplies for ships and aircraft', self::WITH_CREDIT, true],
        [C::ExportTransport, '172.1.თ', 'Transport of goods in export, re-export or transit', self::WITH_CREDIT, true],
        [C::InternationalPassengerTransport, '172.1.ი', 'International passenger transport', self::WITH_CREDIT, true],
        [C::IntermediaryInExemptOrForeign, '172.2', 'Intermediary in exempt-with-credit or foreign operations', self::WITH_CREDIT, true],
        [C::GasForPowerPlants, '172.4.ა', 'Natural gas for thermal power plants', self::WITH_CREDIT],
        [C::TreatyProjectGoods, '172.4.დ', 'Goods of a ratified-treaty project', self::WITH_CREDIT],
        [C::InboundTransport, '172.4.ვ–ზ', 'Transport of goods before customs clearance', self::WITH_CREDIT],
        [C::DomesticAviation, '172.4.თ, ც', 'Domestic aviation fuel and services', self::WITH_CREDIT],
        [C::EmptyContainerServices, '172.4.ი', 'Services for empty containers and wagons', self::WITH_CREDIT],
        [C::WagonUseAcrossBorder, '172.4.კ–ლ', 'Use of wagons and containers across the border', self::WITH_CREDIT],
        [C::SupplyToOccupiedTerritories, '172.4.მ', "A special enterprise's supply into the occupied territories", self::WITH_CREDIT],
        [C::DutyFree, '172.4.ნ', 'Duty-free shop', self::WITH_CREDIT],
        [C::GoldToNationalBank, '172.4.ო', 'Gold for the National Bank', self::WITH_CREDIT],
        [C::InvestmentGold, '172.4.ო¹', 'Investment gold', self::WITH_CREDIT],
        [C::InboundTourism, '172.4.პ', 'Inbound tourism by a tour operator', self::WITH_CREDIT],
        [C::ShipServicesOnImport, '172.4.ჟ', 'Port and pilot services for ships bringing cargo', self::WITH_CREDIT],
        [C::CustomsWarehouseGoods, '172.4.რ', 'Foreign goods in a customs warehouse', self::WITH_CREDIT],
        [C::StateLottery, '172.4.ს', 'State-majority lottery', self::WITH_CREDIT],
        [C::PropertySeizedForBudget, '172.4.ტ', 'Property transferred or sold for the budget', self::WITH_CREDIT],
        [C::PrimaryAgriculturalProduce, '172.4.უ', 'Georgian agricultural produce before processing', self::WITH_CREDIT],
        [C::GeorgianMeatCheeseHazelnuts, '172.4.ფ', 'Georgian meat, cheese and shelled hazelnuts', self::WITH_CREDIT],
        [C::HotelLeaseback, '172.4.ქ', 'Hotel assets supplied for leaseback', self::WITH_CREDIT],
        [C::FreeHotelStay, '172.4.ღ', 'Free hotel stay of up to 60 days for an owner', self::WITH_CREDIT],
        [C::IncomingInternationalCalls, '172.4.ყ', 'Terminating incoming international calls', self::WITH_CREDIT],
        [C::InwardProcessing, '172.4.შ', 'Inward processing services', self::WITH_CREDIT],
        [C::WholesaleElectricity, '172.4.ჩ', 'Electricity not sold to an end consumer for own use', self::WITH_CREDIT],
        [C::ScrapMetal, '172.4.ძ', 'Metal scrap — the buyer is deemed to have credited', self::WITH_CREDIT],
        [C::Books, '172.4.წ', 'Books and e-books', self::WITH_CREDIT],
        [C::ElectricBus, '172.4.ჭ', 'Electric buses', self::WITH_CREDIT],
        [C::ImportRelatedServices, '172.4.ხ', "Services already in an import's taxable amount", self::WITH_CREDIT],
        [C::GeorgianMedicalGoods, '172.4.ჯ', 'Georgian-made medical goods and pharmaceuticals', self::WITH_CREDIT],
        [C::FoodDonation, '172.4.ჰ', 'Food donated to a charity or food bank', self::WITH_CREDIT],
        [C::UnderTreaty, '172.5', 'Exempt under a ratified international treaty', self::WITH_CREDIT],

        // Art. 170 — exempt without credit
        [C::MedicalCare, '170.1.ა–გ', 'Medical and dental care', self::WITHOUT_CREDIT],
        [C::PatientTransport, '170.1.დ', 'Patient transport', self::WITHOUT_CREDIT],
        [C::Education, '170.1.ე', 'Education by an educational institution', self::WITHOUT_CREDIT],
        [C::PrivateTutoring, '170.1.ვ', 'Private school or university tutoring', self::WITHOUT_CREDIT],
        [C::ArtsSportsForMinors, '170.1.ზ', 'Arts and sports teaching for under-18s', self::WITHOUT_CREDIT],
        [C::SocialCare, '170.1.თ', 'Social care', self::WITHOUT_CREDIT],
        [C::ChildProtection, '170.1.ი', 'Child protection by a guardianship body', self::WITHOUT_CREDIT],
        [C::MembershipServices, '170.1.კ', "An organization's services to members for membership fees", self::WITHOUT_CREDIT],
        [C::HeritageRestoration, '170.1.ლ', 'Restoration of listed heritage monuments', self::WITHOUT_CREDIT],
        [C::UniversalPostal, '170.1.ო', 'Universal postal service', self::WITHOUT_CREDIT],
        [C::PostageStamps, '170.1.პ', 'Postage stamps at face value', self::WITHOUT_CREDIT],
        [C::Funeral, '170.1.ჟ', 'Funeral services', self::WITHOUT_CREDIT],
        [C::DiabeticBread, '170.1.რ', 'Diabetic bread', self::WITHOUT_CREDIT],
        [C::PatriarchateReligiousItems, '170.1.ს', 'Religious items from the Patriarchate', self::WITHOUT_CREDIT],
        [C::ChurchRestoration, '170.1.ტ', 'Church restoration and painting', self::WITHOUT_CREDIT],

        // Art. 171 — exempt without credit
        [C::Financial, '171.1.ა', 'Financial operations and services', self::WITHOUT_CREDIT],
        [C::Gambling, '171.1.ბ', 'Lotteries and gambling', self::WITHOUT_CREDIT],
        [C::Land, '171.1.გ', 'Land', self::WITHOUT_CREDIT],
        [C::SeizedHomeReturn, '171.1.დ', 'Returning a seized home to the individual it came from', self::WITHOUT_CREDIT],
        [C::EducationalMedia, '171.1.ე', 'Educational lecture courses on disc', self::WITHOUT_CREDIT],
        [C::Periodicals, '171.1.ვ', 'Selling and printing periodicals', self::WITHOUT_CREDIT],
        [C::StateProperty, '171.1.ზ', 'State property', self::WITHOUT_CREDIT],
        [C::Privatization, '171.1.თ', 'Privatization', self::WITHOUT_CREDIT],
        [C::PartnershipShare, '171.1.ი', 'Partnership share without allocated property', self::WITHOUT_CREDIT],
        [C::ExemptGoodsLeasing, '171.1.კ', 'Leasing goods exempt without credit', self::WITHOUT_CREDIT],
        [C::RegulatedCityTransport, '171.1.ლ', 'Regulated city bus routes', self::WITHOUT_CREDIT],
        [C::MunicipalWaste, '171.1.მ', 'Municipal cleaning and waste', self::WITHOUT_CREDIT],
        [C::ExciseMarking, '171.1.ნ', 'Mandatory excise marking', self::WITHOUT_CREDIT],
        [C::HotelRoomTransfer, '171.1.ო', 'Hotel room changing hands within a tourist-enterprise contract', self::WITHOUT_CREDIT],
        [C::PartnershipDistribution, '171.1.პ', 'Distribution by an individuals-only partnership', self::WITHOUT_CREDIT],
        [C::OccupiedTerritoryGoods, '171.1.ტ', 'Goods from the occupied territories to a special enterprise', self::WITHOUT_CREDIT],
        [C::DisasterRelief, '171.1.ფ', 'Foreign-funded disaster relief', self::WITHOUT_CREDIT],
        [C::OilGasOperations, '171.1.ქ', 'Oil and gas operations', self::WITHOUT_CREDIT],
        [C::FreeServitude, '171.1.ყ', 'Free servitude', self::WITHOUT_CREDIT],
    ];

    public function exempts(Supply $s): ?Outcome
    {
        if ($s->isImport()) {
            return null;
        }

        foreach (self::TABLE as $row) {
            [$category, $article, $reason, $withCredit] = $row;

            if (!$s->is($category)) {
                continue;
            }

            return $withCredit
                ? $s->exemptWithCredit(new Citation($article), "$reason — no VAT, input credit kept.", $row[4] ?? false)
                : $s->exempt(new Citation($article), "$reason — no VAT and no input credit.");
        }

        return null;
    }
}
