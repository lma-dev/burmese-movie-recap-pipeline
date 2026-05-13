<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();

            $table->unsignedSmallInteger('part_number');     // 1-based "Part 01"
            $table->unsignedInteger('start_sec');
            $table->unsignedInteger('end_sec');

            // File paths produced by the python scripts (relative to PIPELINE_STORAGE_ROOT)
            $table->string('video_path')->nullable();    // split mp4
            $table->string('srt_path')->nullable();      // burmese SRT
            $table->string('voice_path')->nullable();    // narrator wav/mp3
            $table->string('rendered_path')->nullable(); // final mp4 with subs + narrator

            // Per-segment status for each downstream step
            $table->string('transcribe_status')->default('pending');
            $table->string('translate_status')->default('pending');
            $table->string('voice_status')->default('pending');
            $table->string('render_status')->default('pending');

            $table->unsignedTinyInteger('render_progress')->default(0); // 0..100
            $table->unsignedTinyInteger('voice_progress')->default(0);

            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'part_number']);
            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('segments');
    }
};
