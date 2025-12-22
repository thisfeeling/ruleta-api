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
        Schema::create('player_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained('players')->onDelete('cascade');
            $table->foreignId('game_id')->nullable()->constrained('games')->nullOnDelete();
            $table->enum('game_type', [
                'millionaire',
                'rope',
                'spell',
                'roulette',
                'word_search',
                'flappy',
                'achievement'
            ]);
            $table->integer('raw_score')->comment('Score specific to game type');
            $table->integer('normalized_score')->comment('0-1000 normalized');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['player_id', 'game_type']);
            $table->index(['game_id', 'normalized_score']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_scores');
    }
};
