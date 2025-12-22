<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('show_id')->nullable()->constrained('shows')->onDelete('set null');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('event_type', [
                'game_start',
                'game_end',
                'player_eliminated',
                'answer_submitted',
                'vote_cast',
                'word_found',
                'achievement_unlocked',
                'supervisor_action',
                'system_event'
            ]);
            $table->string('entity_type')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('payload');
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('s3_backup_key')->nullable();
            $table->timestamp('created_at')->useCurrent();
            
            $table->index(['show_id', 'created_at']);
            $table->index(['event_type', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
        });
        
        // Note: Partitioning would be set up here for production
        // $this->createPartitions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
