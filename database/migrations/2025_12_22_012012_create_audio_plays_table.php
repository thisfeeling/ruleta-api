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
        Schema::create('audio_plays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('track_id')->constrained('audio_tracks')->onDelete('cascade');
            $table->foreignId('show_id')->nullable()->constrained('shows')->onDelete('cascade');
            $table->foreignId('played_by')->nullable()->constrained('users');
            $table->dateTime('played_at');
            $table->string('context')->nullable();
            $table->timestamps();
            
            $table->index(['track_id', 'played_at']);
            $table->index(['show_id', 'played_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audio_plays');
    }
};
