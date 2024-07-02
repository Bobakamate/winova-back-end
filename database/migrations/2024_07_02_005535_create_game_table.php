<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('game', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('name');
            $table->string('image')->nullable();
            $table->dateTime('startTime');
            $table->dateTime('endTime');
            $table->boolean('premium');
            $table->string('url')->nullable();
            $table->integer('cashPrise')->nullable()->default(100);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game');
    }
};
