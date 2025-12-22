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
        Schema::create('rope_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('rope_groups')->onDelete('cascade');
            $table->foreignId('voter_id')->constrained('players')->onDelete('cascade');
            $table->foreignId('voted_for_id')->constrained('players')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['group_id', 'voter_id']);
            $table->index(['group_id', 'voted_for_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rope_votes');
    }
};
