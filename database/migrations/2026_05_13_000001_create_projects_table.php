<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();

            // -----------------------------------------------------
            // Source step
            // -----------------------------------------------------
            $table->string('source_url');                 // pasted URL
            $table->string('source_title')->nullable();   // resolved video title
            $table->integer('source_duration_sec')->nullable();
            $table->string('source_thumbnail_url')->nullable();
            $table->string('source_local_path')->nullable(); // downloaded file path on disk

            // -----------------------------------------------------
            // Split step
            // -----------------------------------------------------
            $table->integer('segment_seconds')->default(120);
            $table->integer('segment_count')->nullable();

            // -----------------------------------------------------
            // Render step — frame size preset id (key from config/pipeline.php)
            // -----------------------------------------------------
            $table->string('frame_preset')->default('reel_9x16');

            // -----------------------------------------------------
            // Voice step
            // -----------------------------------------------------
            $table->string('audio_mix_mode')->default('duck'); // duck | replace
            $table->string('voice_tone')->default('neutral');  // VoiceTone enum value

            // -----------------------------------------------------
            // Pipeline state
            // -----------------------------------------------------
            $table->string('current_step')->default('source');     // PipelineStep enum value
            $table->string('source_status')->default('pending');   // StepStatus
            $table->string('split_status')->default('pending');
            $table->string('translate_status')->default('pending');
            $table->string('edit_srt_status')->default('pending');
            $table->string('voice_status')->default('pending');
            $table->string('render_status')->default('pending');

            $table->text('last_error')->nullable();

            $table->timestamps();

            $table->index('current_step');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
