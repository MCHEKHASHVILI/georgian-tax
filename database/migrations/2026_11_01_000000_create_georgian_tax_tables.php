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

        Schema::create($t('assessments'), function (Blueprint $table) {
            $table->id();
            $table->string('event_type');
            $table->longText('payload');
            $table->date('occurred_on')->index();
            $table->bigInteger('total_tax');
            $table->timestamps();
        });

        Schema::create($t('outcomes'), function (Blueprint $table) use ($t) {
            $table->id();
            $table->foreignId('assessment_id')->constrained($t('assessments'))->cascadeOnDelete();
            $table->string('tax', 20);
            $table->string('outcome', 20);
            $table->string('article', 40);
            $table->string('title')->nullable();
            $table->string('taxpayer')->nullable()->index();
            $table->string('agent')->nullable()->index();
            $table->bigInteger('base')->nullable();
            $table->decimal('rate', 8, 4)->nullable();
            $table->decimal('quantity', 18, 4)->nullable();
            $table->string('unit', 30)->nullable();
            $table->bigInteger('amount')->nullable();
            $table->string('collection', 30)->nullable();
            $table->date('due_on')->nullable()->index();
            $table->text('summary');
            $table->json('notes')->nullable();
            $table->timestamps();
            $table->index(['tax', 'outcome']);
        });
    }

    public function down(): void
    {
        $t = fn (string $name) => config('georgian-tax.database.table_prefix', 'georgian_tax_') . $name;

        foreach (['outcomes', 'assessments', 'company_years', 'spending_entries', 'turnover_entries', 'income_entries'] as $name) {
            Schema::dropIfExists($t($name));
        }
    }
};
