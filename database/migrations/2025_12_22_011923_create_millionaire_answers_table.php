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
        Schema::create('millionaire_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('millionaire_questions')->onDelete('cascade');
            $table->foreignId('player_id')->constrained('players')->onDelete('cascade');
            $table->enum('selected_answer', ['A', 'B', 'C', 'D'])->nullable();
            $table->boolean('is_correct')->nullable();
            $table->integer('time_taken_ms')->nullable();
            $table->boolean('timed_out')->default(false);
            $table->timestamps();
            
            $table->unique(['question_id', 'player_id']);
            $table->index(['player_id', 'is_correct']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('millionaire_answers');
    }
};
