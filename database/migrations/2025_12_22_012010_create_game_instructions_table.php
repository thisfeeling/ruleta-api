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
        Schema::create('game_instructions', function (Blueprint $table) {
            $table->id();
            $table->enum('game_type', [
                'millionaire',
                'rope',
                'spell',
                'roulette',
                'word_search',
                'flappy'
            ]);
            $table->text('content_es');
            $table->text('content_en');
            $table->string('audio_es_url')->nullable();
            $table->string('audio_en_url')->nullable();
            $table->integer('estimated_duration_seconds');
            $table->timestamps();

            $table->unique('game_type');
        });

        Schema::create('instruction_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->onDelete('cascade');
            $table->foreignId('player_id')->constrained('players')->onDelete('cascade');
            $table->foreignId('instruction_id')->constrained('game_instructions')->onDelete('cascade');
            $table->boolean('completed')->default(false);
            $table->dateTime('started_at');
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['game_id', 'player_id']);
            $table->index(['instruction_id', 'completed']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('instruction_reads');
        Schema::dropIfExists('game_instructions');
    }
};
