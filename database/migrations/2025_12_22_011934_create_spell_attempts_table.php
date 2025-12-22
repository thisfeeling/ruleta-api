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
        Schema::create('spell_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spell_word_id')->constrained('spell_words')->onDelete('cascade');
            $table->string('submitted_spelling');
            $table->boolean('is_correct');
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->text('review_notes')->nullable();
            $table->integer('time_taken_ms');
            $table->timestamps();
            
            $table->index(['spell_word_id', 'is_correct']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spell_attempts');
    }
};
