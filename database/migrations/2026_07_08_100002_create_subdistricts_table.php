<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subdistricts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('district_id');
            $table->unsignedMediumInteger('city_id');
            $table->unsignedSmallInteger('province_id');
            $table->string('code', 20);
            $table->unsignedMediumInteger('zip')->nullable();
            $table->string('type', 20);
            $table->string('name', 100);
            $table->string('district_name', 100);
            $table->string('city_type', 20);
            $table->string('city_name', 100);
            $table->string('province_name', 100);
            $table->timestamps();

            $table->index('name');
            $table->index('district_id');
            $table->index('city_id');
            $table->index('province_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subdistricts');
    }
};
