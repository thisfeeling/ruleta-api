<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audio_plays', function (Blueprint $table) {
            $table->boolean('approved')->nullable()->default(null);
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
        });
    }

    public function down(): void
    {
        Schema::table('audio_plays', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
            $table->dropColumn(['approved', 'reviewed_by']);
        });
    }
};
