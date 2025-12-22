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
        Schema::create('word_search_finds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grid_id')->constrained('word_search_grids')->onDelete('cascade');
            $table->foreignId('player_id')->constrained('players')->onDelete('cascade');
            $table->string('word');
            $table->integer('find_order');
            $table->integer('time_elapsed_ms');
            $table->timestamps();

            $table->unique(['grid_id', 'player_id', 'word']);
            $table->index(['grid_id', 'find_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('word_search_finds');
    }
};
