<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Art. 98⁴.2 — a year's costs, for the representation limit when they exceed its revenue. */
return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('georgian-tax.database.connection');
    }

    public function up(): void
    {
        Schema::table($this->table(), function (Blueprint $table) {
            // Tetri, like the other money columns.
            $table->bigInteger('costs')->nullable()->after('net_profit');
        });
    }

    public function down(): void
    {
        Schema::table($this->table(), function (Blueprint $table) {
            $table->dropColumn('costs');
        });
    }

    private function table(): string
    {
        return config('georgian-tax.database.table_prefix', 'georgian_tax_') . 'company_years';
    }
};
