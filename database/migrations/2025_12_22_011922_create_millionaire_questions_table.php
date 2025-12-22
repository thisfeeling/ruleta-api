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
        Schema::create('millionaire_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->onDelete('cascade');
            $table->integer('question_number');
            $table->text('question_text_es');
            $table->text('question_text_en');
            $table->string('option_a_es');
            $table->string('option_a_en');
            $table->string('option_b_es');
            $table->string('option_b_en');
            $table->string('option_c_es');
            $table->string('option_c_en');
            $table->string('option_d_es');
            $table->string('option_d_en');
            $table->enum('correct_answer', ['A', 'B', 'C', 'D']);
            $table->integer('time_limit_seconds')->default(15);
            $table->string('audio_question_url')->nullable();
            $table->timestamps();

            $table->index(['game_id', 'question_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('millionaire_questions');
    }
};
