<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tts_usages', function (Blueprint $table) {
            $table->id();
            // create column now, add foreign key in a later migration after audio_tracks exists
            $table->unsignedBigInteger('audio_track_id')->nullable();
            $table->string('request_id')->nullable();
            $table->unsignedBigInteger('character_count')->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tts_usages');
    }
};
