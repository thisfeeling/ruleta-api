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
        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name_es');
            $table->string('name_en');
            $table->text('description_es');
            $table->text('description_en');
            $table->string('icon')->nullable();
            $table->enum('rarity', ['common', 'rare', 'epic', 'legendary'])->default('common');
            $table->integer('points')->default(0);
            $table->boolean('is_secret')->default(false);
            $table->json('unlock_criteria')->nullable();
            $table->timestamps();

            $table->index('key');
            $table->index('rarity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('achievements');
    }
};
