<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('georgian-tax.database.connection');
    }

    public function up(): void
    {
        $t = fn (string $name) => config('georgian-tax.database.table_prefix', 'georgian_tax_') . $name;

        // Money columns hold tetri (integer minor units of GEL).

        Schema::create($t('income_entries'), function (Blueprint $table) {
            $table->id();
            $table->string('recipient')->index();
            $table->string('payer')->nullable()->index();
            $table->string('kind', 40);
            $table->unsignedSmallInteger('year');
            $table->date('occurred_on');
            $table->bigInteger('income');
            $table->string('heir', 10)->nullable();
            $table->bigInteger('taxable_base')->default(0);
            $table->timestamps();
            $table->index(['recipient', 'year', 'kind']);
        });

        Schema::create($t('turnover_entries'), function (Blueprint $table) {
            $table->id();
            $table->string('party');
            $table->string('tax', 20);
            $table->date('occurred_on');
            $table->bigInteger('amount');
            $table->timestamps();
            $table->index(['party', 'tax', 'occurred_on']);
        });

        Schema::create($t('spending_entries'), function (Blueprint $table) {
            $table->id();
            $table->string('company');
            $table->unsignedSmallInteger('year');
            $table->string('category', 40);
            $table->bigInteger('amount');
            $table->timestamps();
            $table->index(['company', 'year', 'category']);
        });

        Schema::create($t('company_years'), function (Blueprint $table) {
            $table->id();
            $table->string('company');
            $table->unsignedSmallInteger('year');
            $table->bigInteger('revenue')->nullable();
            $table->bigInteger('net_profit')->nullable();
            $table->unsignedSmallInteger('founded_in')->nullable();
            $table->timestamps();
            $table->unique(['company', 'year']);
        });
    }

    public function down(): void
    {
        $t = fn (string $name) => config('georgian-tax.database.table_prefix', 'georgian_tax_') . $name;

        foreach (['company_years', 'spending_entries', 'turnover_entries', 'income_entries'] as $name) {
            Schema::dropIfExists($t($name));
        }
    }
};
