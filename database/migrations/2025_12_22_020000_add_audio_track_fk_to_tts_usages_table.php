<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tts_usages', function (Blueprint $table) {
            // Add foreign key now that audio_tracks table should exist
            $table->foreign('audio_track_id')->references('id')->on('audio_tracks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tts_usages', function (Blueprint $table) {
            $table->dropForeign(['audio_track_id']);
        });
    }
};
