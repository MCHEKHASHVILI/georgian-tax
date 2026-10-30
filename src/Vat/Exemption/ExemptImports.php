<?php

declare(strict_types=1);

namespace GeorgianTax\Vat\Exemption;

use GeorgianTax\Domain\Income\SupplyCategory as C;
use GeorgianTax\Engine\Outcome\Outcome;
use GeorgianTax\Law\Citation;
use GeorgianTax\Vat\Supply;

/** Art. 173 — imports exempt from VAT. Most rows are HS-code lists; the category names the list. */
final class ExemptImports implements Exemption
{
    /** @var array<string, array{0: string, 1: string}> category name => article, reason */
    private const TABLE = [
        'ListedMedicalImport' => ['173.ა', 'Listed medical goods, disability aids, baby food and hygiene'],
        'Pharmaceuticals' => ['173.ბ', 'Pharmaceuticals (HS 30)'],
        'MedicalEquipment' => ['173.გ', 'Registered radiopharmaceuticals and medical equipment'],
        'Paper' => ['173.დ', 'Listed paper'],
        'PharmaRawMaterials' => ['173.ე', 'Listed pharmaceutical raw materials'],
        'AgriculturalInputs' => ['173.ვ', 'Breeding animals, seedlings and fertilizers'],
        'PassengerCarOrMotorcycle' => ['173.ზ', 'Passenger cars and motorcycles (HS 8703, 8711)'],
        'Boat' => ['173.თ', 'Boats (HS 8903)'],
        'ExciseStamps' => ['173.ი', 'Excise stamps'],
        'Treasure' => ['173.კ', 'Treasure and state-owned movables'],
        'DiabeticBread' => ['173.ლ', 'Diabetic bread'],
        'Periodicals' => ['173.მ', 'Periodicals'],
        'MoneyOrSecurities' => ['173.ო', 'Money and securities'],
        'TractorParts' => ['173.პ', 'Tractor chassis and parts'],
        'GasForPowerPlants' => ['173.ჟ', 'Gas for power plants'],
        'Agrochemicals' => ['173.რ', 'Listed agrochemicals and seed material'],
        'FuelInVehicleTank' => ['173.ს', "Fuel in a vehicle's standard tank"],
        'CivilAircraft' => ['173.ტ', 'Civil aircraft and their goods'],
        'GoldToNationalBank' => ['173.უ', 'Gold for the National Bank'],
        'InvestmentGold' => ['173.უ¹', 'Investment gold'],
        'ReturnedGoods' => ['173.ფ', 'Returned goods and outward-processing products'],
        'HumanitarianAid' => ['173.ქ', 'Disaster and humanitarian aid'],
        'DiplomaticGoods' => ['173.ღ, ყ, წ', 'Goods for diplomatic missions'],
        'GrantGoods' => ['173.ც', 'Grant-funded goods'],
        'OilGasOperations' => ['173.ჭ', 'Goods for oil and gas operations'],
        'Books' => ['173.ჰ¹', 'Books'],
        'ElectricBus' => ['173.ჰ²', 'Electric buses'],
    ];

    public function exempts(Supply $s): ?Outcome
    {
        $category = $s->category();

        if (!$s->isImport() || $category === null || !isset(self::TABLE[$category->name])) {
            return null;
        }

        [$article, $reason] = self::TABLE[$category->name];

        return $s->exempt(new Citation($article), "$reason — import exempt from VAT.");
    }
}
