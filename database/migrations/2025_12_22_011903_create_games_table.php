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
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('show_id')->constrained('shows')->onDelete('cascade');
            $table->enum('type', [
                'millionaire',
                'rope',
                'spell',
                'roulette',
                'word_search',
                'flappy'
            ]);
            $table->integer('round_number')->comment('1 for first occurrence, 2 for second');
            $table->enum('status', [
                'pending',
                'instructions',
                'active',
                'completed',
                'cancelled'
            ])->default('pending');
            $table->boolean('is_bonus')->default(false);
            $table->integer('players_at_start')->nullable();
            $table->integer('players_eliminated')->default(0);
            $table->json('config')->nullable();
            $table->json('state')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->index(['show_id', 'type', 'round_number']);
            $table->index(['status', 'started_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
