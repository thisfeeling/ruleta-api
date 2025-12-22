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
        Schema::create('spell_words', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->onDelete('cascade');
            $table->foreignId('player_id')->constrained('players')->onDelete('cascade');
            $table->string('word');
            $table->enum('difficulty', ['easy', 'medium', 'hard']);
            $table->integer('time_limit_seconds');
            $table->string('audio_recording_url')->nullable();
            $table->enum('status', ['pending', 'recording', 'reviewing', 'completed'])->default('pending');
            $table->timestamps();

            $table->index(['game_id', 'player_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spell_words');
    }
};
