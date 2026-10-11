<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create((string) config('kajodo-guard.table', 'kajodo_visitors'), function (Blueprint $table): void {
            $table->string('ip', 45);
            $table->string('decision', 16)->default('deny');
            $table->string('reason')->nullable();
            $table->string('verdict', 32)->nullable();
            $table->string('country_code', 2)->nullable();
            $table->string('located_country_code', 2)->nullable();
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->timestamps();

            // Explicit statement (not fluent ->primary()): DigitalOcean
            // managed MySQL sets sql_require_primary_key and rejects
            // tables whose CREATE carries no primary key.
            $table->primary('ip');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists((string) config('kajodo-guard.table', 'kajodo_visitors'));
    }
};
