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
        Schema::table('payement_game', function (Blueprint $table) {
            $table->foreign(['gameId'], 'payement_game_ibfk_1')->references(['id'])->on('game')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payement_game', function (Blueprint $table) {
            $table->dropForeign('payement_game_ibfk_1');
        });
    }
};
