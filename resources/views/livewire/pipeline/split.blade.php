@php
    use App\Enums\StepStatus;

    $status   = $project->split_status;
    $segments = $project->segments;
    $done     = $segments->filter(fn ($s) => $s->video_path)->count();
    $total    = $segments->count() ?: max(1, ceil(($project->source_duration_sec ?? 0) / $segmentSeconds));
    $estParts = (int) ceil(($project->source_duration_sec ?? 0) / $segmentSeconds);
    $lastSec  = ($project->source_duration_sec ?? 0) % $segmentSeconds;
@endphp

<div wire:poll.3s>
    <livewire:step-bar :current="$step" :project="$project" />

    <div class="body-wrap">
        <div class="body-head">
            <div class="title">
                <span class="label-eyebrow">Step 01</span>
                <h1>Split into segments</h1>
                <p class="sub">Cut the source into evenly-sized parts. Each segment is transcribed, dubbed, and rendered independently.</p>
            </div>
            <div class="actions">
                <button class="btn" wire:click="back">Back</button>
                <button class="btn primary"
                        wire:click="continueToTranslate"
                        @disabled($status !== StepStatus::Done)>
                    Continue<svg class="ic"><use href="#i-arrow-r"/></svg>
                </button>
            </div>
        </div>

        <div class="cols two">
            {{-- Left column: slider + ffmpeg preview + start --}}
            <div class="col">
                <div class="card card-pad" style="display:flex; flex-direction:column; gap: 18px;">
                    <h3 class="h-sub">Segment duration</h3>

                    <div style="display:flex; flex-direction:column; gap: 8px;">
                        <div class="mono" style="display:flex; justify-content:space-between; font-size: 12px; color: var(--ink-3);">
                            <span>1 min</span>
                            <span style="color: var(--blue); font-weight: 500;">{{ intdiv($segmentSeconds, 60) }}:{{ str_pad($segmentSeconds % 60, 2, '0', STR_PAD_LEFT) }} min</span>
                            <span>5 min</span>
                        </div>
                        <input
                            type="range"
                            min="60" max="300" step="30"
                            wire:model.live="segmentSeconds"
                            style="width:100%"
                        />
                    </div>

                    <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap: 8px;">
                        <div class="card-2" style="padding: 12px;">
                            <div class="label-eyebrow">Source duration</div>
                            <div class="mono" style="font-size: 16px; margin-top: 4px;">
                                {{ sprintf('%02d:%02d', intdiv($project->source_duration_sec ?? 0, 60), ($project->source_duration_sec ?? 0) % 60) }}
                            </div>
                        </div>
                        <div class="card-2" style="padding: 12px;">
                            <div class="label-eyebrow">Estimated parts</div>
                            <div class="mono" style="font-size: 16px; margin-top: 4px;">{{ $estParts }} segments</div>
                        </div>
                        <div class="card-2" style="padding: 12px;">
                            <div class="label-eyebrow">Last part</div>
                            <div class="mono" style="font-size: 16px; margin-top: 4px;">
                                {{ sprintf('%02d:%02d', intdiv($lastSec, 60), $lastSec % 60) }}
                            </div>
                        </div>
                    </div>

                    <div>
                        <div class="label-eyebrow" style="margin-bottom: 8px;">FFmpeg preview</div>
                        <div class="mono" style="background: #18181a; color: #d4d4d0; padding: 14px 16px; border-radius: var(--radius); font-size: 12px; line-height: 1.55; overflow-x:auto;">
                            <span style="color: #6ea3ff;">$</span> ffmpeg -i <span style="color:#e6c07b;">source.mp4</span> -c copy \<br/>
                            &nbsp;&nbsp;-map 0 -segment_time <span style="color:#a3d9a5;">{{ sprintf('00:%02d:%02d', intdiv($segmentSeconds, 60), $segmentSeconds % 60) }}</span> \<br/>
                            &nbsp;&nbsp;-f segment -reset_timestamps 1 \<br/>
                            &nbsp;&nbsp;-segment_format mp4 <span style="color:#e6c07b;">part_%03d.mp4</span>
                        </div>
                    </div>

                    <button class="btn primary" style="width: 100%; height: 44px;"
                            wire:click="startSplit"
                            @disabled($status === StepStatus::Running)>
                        <svg class="ic"><use href="#i-scissors"/></svg>
                        {{ $status === StepStatus::Running ? 'Splitting…' : 'Start split' }}
                    </button>

                    @if ($status === StepStatus::Running || $status === StepStatus::Done)
                        <div style="display:flex; flex-direction:column; gap: 6px;">
                            <div style="display:flex; justify-content:space-between; font-size: 12px;">
                                <span style="color: var(--ink-2); font-weight:500;">
                                    {{ $status === StepStatus::Done ? 'Split complete' : 'Splitting · part '.($done + 1).' of '.$total }}
                                </span>
                                <span class="mono muted">{{ $total > 0 ? (int) round($done * 100 / $total) : 0 }}%</span>
                            </div>
                            <div class="progress {{ $status === StepStatus::Done ? 'green' : '' }}">
                                <i style="width: {{ $total > 0 ? round($done * 100 / $total) : 0 }}%;"></i>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Right column: segment grid --}}
            <div class="col">
                <div class="card card-pad" style="flex:1; display:flex; flex-direction:column; gap: 12px;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <h3 class="h-sub">Segments</h3>
                        <span class="mono muted" style="font-size:12px;">{{ $done }} / {{ $total }} complete</span>
                    </div>

                    <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap: 10px;">
                        @forelse ($segments as $seg)
                            <div class="seg done">
                                <div class="h">
                                    <span class="num">{{ $seg->label() }}</span>
                                    <svg class="ic ic-sm"><use href="#i-check"/></svg>
                                </div>
                                <div class="mono">{{ $seg->range() }}</div>
                            </div>
                        @empty
                            @for ($i = 1; $i <= $estParts; $i++)
                                <div class="seg queued">
                                    <div class="h">
                                        <span class="num">Part {{ str_pad($i, 2, '0', STR_PAD_LEFT) }}</span>
                                        <svg class="ic ic-sm"><use href="#i-clock"/></svg>
                                    </div>
                                    <div class="mono">
                                        {{ sprintf('%02d:%02d', intdiv(($i-1)*$segmentSeconds,60), (($i-1)*$segmentSeconds)%60) }}
                                        →
                                        {{ sprintf('%02d:%02d', intdiv(min($i*$segmentSeconds, $project->source_duration_sec ?? $i*$segmentSeconds),60), min($i*$segmentSeconds, $project->source_duration_sec ?? $i*$segmentSeconds)%60) }}
                                    </div>
                                </div>
                            @endfor
                        @endforelse
                    </div>

                    <div style="margin-top: auto; border-top: 0.5px solid var(--border); padding-top: 14px; display:flex; flex-direction:column; gap: 10px;">
                        <div class="label-eyebrow">Output directory</div>
                        <div style="display:flex; align-items:center; gap: 10px; padding: 10px 12px; background: var(--surface-2); border-radius: var(--radius);">
                            <svg class="ic" style="color: var(--ink-3);"><use href="#i-folder"/></svg>
                            <span class="mono" style="font-size: 12px;">/{{ config('pipeline.storage_root') }}/{{ $project->id }}/segments/</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="footer-row">
            <span>Splitting · <span class="mono ink-2">{{ $done }} done, {{ max(0, $total - $done) }} queued</span></span>
            <span>Step 2 of 6 · Split</span>
        </div>
    </div>
</div>
