<?php

declare(strict_types=1);

namespace GeorgianTax\Domain\Goods;

/** The document that proves excise was paid on raw materials, without which there is no credit (189.3, 189.3¹). */
enum ExciseEvidence: string
{
    /** Bought from the raw material's producer, who charged the excise (189.3). */
    case TaxInvoice = '189.3';
    /** Imported by the producer, who paid the excise at customs (189.2–3). */
    case CustomsDeclaration = '189.2–3';
    /** Excise charged on a 190¹ sale (seized or pledged goods, privatisation) and shown in the excise declaration (189.3¹). */
    case ExciseDeclaration = '189.3¹';

    public function describe(): string
    {
        return match ($this) {
            self::TaxInvoice => 'tax invoice',
            self::CustomsDeclaration => 'customs declaration',
            self::ExciseDeclaration => 'excise declaration',
        };
    }
}
