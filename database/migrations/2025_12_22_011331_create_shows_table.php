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
        Schema::create('shows', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('status', [
                'scheduled',
                'lobby',
                'in_progress',
                'paused',
                'completed',
                'cancelled'
            ])->default('scheduled');
            $table->enum('current_phase', [
                'lobby',
                'millionaire_1',
                'spell_1',
                'rope',
                'millionaire_2',
                'spell_2',
                'roulette',
                'word_search',
                'flappy',
                'results'
            ])->nullable();
            $table->integer('max_players')->default(50);
            $table->integer('current_player_count')->default(0);
            $table->foreignId('winner_id')->nullable()->constrained('users');
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'scheduled_at']);
            $table->index('current_phase');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shows');
    }
};
