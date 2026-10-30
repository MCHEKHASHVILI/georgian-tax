<?php

declare(strict_types=1);

namespace GeorgianTax\Domain\Event;

/** What kind of expense profit tax is looking at — each case names its article. */
enum ExpenseNature: string
{
    // Not related to economic activity — 98².1
    case Undocumented = '98².1.ა';
    case NotForProfit = '98².1.ბ';
    case FromMicroBusiness = '98².1.გ';
    case FromFixedTaxPayer = '98².1.დ';
    case ExcessInterest = '98².1.ე';
    case SpecialTradingCompanyMarkup = '98².1.ვ';

    // Deemed taxable payments — 98².3
    case BondsOfPreferentialOrExempt = '98².3.ა';
    case PenaltyToPreferentialOrExempt = '98².3.ბ';
    case AdvanceToPreferentialOrExempt = '98².3.გ';
    case LoanToPreferentialOrExempt = '98².3.დ';
    case ClaimLossWithPreferentialOrExempt = '98².3.ე';
    case EquityInNonResidentOrExempt = '98².3.ვ';
    case LoanToIndividualOrNonResident = '98².3.ზ';
    case CashCollateralForPartnerLoan = '98².3.ზ¹';
    case LenderLoanToPartner = '98².3.თ';

    // Representation — 98⁴, taxed only above the yearly limit
    case Representation = '98⁴';

    /** 98².3.ზ, ზ¹ — loans by banks and licensed lenders are carved out. */
    public function excludedForLenders(): bool
    {
        return in_array($this, [self::LoanToIndividualOrNonResident, self::CashCollateralForPartnerLoan, self::LoanToPreferentialOrExempt], true);
    }
}
