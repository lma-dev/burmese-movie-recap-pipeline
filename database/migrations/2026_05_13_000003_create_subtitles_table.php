<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subtitles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('segment_id')->constrained()->cascadeOnDelete();

            $table->unsignedInteger('line_number');    // 1-based, matches SRT index

            $table->unsignedInteger('start_ms');       // ms within the segment
            $table->unsignedInteger('end_ms');

            $table->text('source_text')->nullable();   // original transcription (en/zh/...)
            $table->text('burmese_text');              // editable Burmese line

            $table->timestamps();

            $table->unique(['segment_id', 'line_number']);
            $table->index('segment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subtitles');
    }
};
