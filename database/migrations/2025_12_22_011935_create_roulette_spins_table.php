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
        Schema::create('roulette_spins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->onDelete('cascade');
            $table->foreignId('player_id')->constrained('players')->onDelete('cascade');
            $table->integer('round_number');
            $table->integer('points_won');
            $table->boolean('eliminated')->default(false);
            $table->integer('cumulative_score');
            $table->float('spin_duration_seconds');
            $table->timestamps();
            
            $table->index(['game_id', 'player_id']);
            $table->index(['game_id', 'round_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roulette_spins');
    }
};
