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
        Schema::create('rope_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->onDelete('cascade');
            $table->string('group_name');
            $table->integer('total_clicks')->default(0);
            $table->integer('members_count');
            $table->enum('status', ['voting', 'battle', 'eliminated', 'safe'])->default('voting');
            $table->foreignId('voted_out_player_id')->nullable()->constrained('players');
            $table->timestamps();

            $table->index(['game_id', 'status']);
        });

        Schema::create('group_player', function (Blueprint $table) {
            $table->foreignId('group_id')->constrained('rope_groups')->onDelete('cascade');
            $table->foreignId('player_id')->constrained('players')->onDelete('cascade');
            $table->integer('clicks_contributed')->default(0);
            $table->timestamps();

            $table->primary(['group_id', 'player_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_player');
        Schema::dropIfExists('rope_groups');
    }
};
