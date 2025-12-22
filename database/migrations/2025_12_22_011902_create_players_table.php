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
        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('show_id')->constrained('shows')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->integer('player_number')->comment('Display number 1-50');
            $table->string('pin', 4)->unique()->comment('Reconnection PIN');
            $table->enum('status', [
                'active',
                'eliminated',
                'disconnected',
                'winner'
            ])->default('active');
            $table->integer('elimination_order')->nullable();
            $table->string('eliminated_by_game')->nullable();
            $table->integer('total_score')->default(0);
            $table->json('stats')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['show_id', 'player_number']);
            $table->unique(['show_id', 'user_id']);
            $table->index(['show_id', 'status']);
            $table->index('pin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};
