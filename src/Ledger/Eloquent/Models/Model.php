<?php

declare(strict_types=1);

namespace GeorgianTax\Ledger\Eloquent\Models;

use Illuminate\Database\Eloquent\Model as Eloquent;

/** Every georgian-tax table: prefixed and on the connection config('georgian-tax.database') names. */
abstract class Model extends Eloquent
{
    protected $guarded = [];

    /** The table name without the prefix. */
    abstract protected function baseTable(): string;

    public function getTable(): string
    {
        return config('georgian-tax.database.table_prefix', 'georgian_tax_') . $this->baseTable();
    }

    public function getConnectionName(): ?string
    {
        return config('georgian-tax.database.connection') ?? parent::getConnectionName();
    }
}
