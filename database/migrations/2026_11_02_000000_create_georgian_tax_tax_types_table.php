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

    private function table(): string
    {
        return config('georgian-tax.database.table_prefix', 'georgian_tax_') . 'tax_types';
    }

    public function up(): void
    {
        Schema::create($this->table(), function (Blueprint $table) {
            $table->id();
            $table->string('code', 40);                 // vat, income, social, turnover…
            $table->string('name_ka');
            $table->string('name_en');
            $table->string('level', 20);                // national | local
            $table->date('in_force_from')->nullable();  // null: since before the recorded history
            $table->date('abolished_on')->nullable();   // first day no longer in force; null: in force
            $table->foreignId('replaces_id')->nullable()->constrained($this->table())->nullOnDelete();
            $table->string('engine')->nullable();       // GeorgianTax\Engine\Tax class
            $table->string('legal_basis')->nullable();
            $table->string('introduced_by')->nullable();
            $table->string('abolished_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['code', 'in_force_from']);
            $table->index(['in_force_from', 'abolished_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table());
    }
};
