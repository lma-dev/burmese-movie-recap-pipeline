@php
    use App\Enums\StepStatus;
    $segments    = $project->segments;
    $selectedKey = $framePreset;
    $selected    = $presets[$selectedKey] ?? null;
@endphp

<div wire:poll.3s>
    <livewire:step-bar :current="$step" :project="$project" />

    <div class="body-wrap">
        <div class="body-head">
            <div class="title">
                <span class="label-eyebrow">Step 05</span>
                <h1>Render &amp; download</h1>
                <p class="sub">Runs automatically. Each segment is rendered with the Burmese narrator and burned-in subtitles. Play any finished part inline.</p>
            </div>
            <div class="actions">
                <button class="btn" wire:click="back">Back</button>
                <button class="btn primary" wire:click="downloadZip"
                        @disabled($doneCount === 0)>
                    <svg class="ic"><use href="#i-zip"/></svg>Download all as ZIP
                </button>
            </div>
        </div>

        {{-- =====================================================
             FRAME-SIZE PICKER — picks the output frame size used by
             render_video.py. Reels (Facebook / TikTok / YouTube
             Shorts) are all 9:16 vertical 1080×1920.
             ===================================================== --}}
        <div class="card card-pad" style="display:flex; flex-direction:column; gap: 14px;">
            <div style="display:flex; justify-content:space-between; align-items:baseline;">
                <h3 class="h-sub">Output frame size</h3>
                <span class="mono muted" style="font-size: 12px;">
                    @if ($selected)
                        {{ $selected['width'] }}×{{ $selected['height'] }} · {{ $selected['aspect'] }} · {{ $selected['fps'] }}fps
                    @endif
                </span>
            </div>
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 10px;">
                @foreach ($presets as $key => $preset)
                    @php $checked = $key === $selectedKey; @endphp
                    <label style="display:flex; flex-direction:column; gap: 8px; padding: 14px 16px;
                                  border: {{ $checked ? '1.5px solid var(--blue)' : '0.5px solid var(--border)' }};
                                  background: {{ $checked ? 'var(--blue-bg)' : 'var(--surface-2)' }};
                                  border-radius: var(--radius); cursor: pointer;">
                        <input type="radio" wire:model.live="framePreset" value="{{ $key }}" style="display:none;" />
                        <div style="display:flex; align-items:center; gap: 10px;">
                            <span class="radio {{ $checked ? 'checked' : '' }}"></span>
                            <span style="font-weight: 500; font-size: 14px;">{{ $preset['label'] }}</span>
                            <span class="pill" style="margin-left:auto; height: 22px; font-size: 11px;">{{ $preset['platform'] }}</span>
                        </div>
                        <div class="mono" style="font-size: 11px; color: var(--ink-3);">
                            {{ $preset['width'] }}×{{ $preset['height'] }} · {{ $preset['aspect'] }} · {{ $preset['fps'] }}fps · {{ $preset['bitrate'] }}
                        </div>
                        <div style="font-size: 12px; color: var(--ink-2); line-height: 1.4;">
                            {{ $preset['description'] }}
                        </div>
                    </label>
                @endforeach
            </div>
        </div>

        <div style="flex:1; display:flex; justify-content:center; align-items:flex-start;">
            <div style="width: 1080px; display:flex; flex-direction:column; gap: 14px;">

                <div class="card" style="display:flex; flex-direction:column; overflow:hidden;">
                    <div style="padding: 16px 24px; border-bottom: 0.5px solid var(--border); display:flex; flex-direction:column; gap: 10px;">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <h3 class="h-sub">Overall render progress</h3>
                            <span class="mono muted" style="font-size: 12px;">{{ $doneCount }} of {{ $segments->count() }} segments rendered</span>
                        </div>
                        <div class="progress"><i style="width: {{ $progress }}%;"></i></div>
                    </div>

                    @foreach ($segments as $seg)
                        @php
                            $isOpen   = $openPlayerSegmentId === $seg->id;
                            $isActive = $seg->render_status === StepStatus::Running;
                            $rowBg    = $isActive ? 'var(--blue-bg)' : 'transparent';
                            $progressClass = $seg->render_status === StepStatus::Done ? 'green' : '';
                            $progressPct = match (true) {
                                $seg->render_status === StepStatus::Done    => 100,
                                $seg->render_status === StepStatus::Running => max(10, $seg->render_progress ?: 35),
                                default => 0,
                            };
                        @endphp
                        <div style="border-bottom: 0.5px solid var(--border);">
                            <div style="display:grid; grid-template-columns: 100px 1fr 130px 220px; padding: 14px 24px; align-items:center; gap: 16px; font-size: 13px; background: {{ $rowBg }};">
                                <div class="mono" style="font-weight:500; color: {{ $seg->render_status === StepStatus::Pending ? 'var(--ink-3)' : ($isActive ? 'var(--blue)' : 'inherit') }};">
                                    {{ $seg->label() }}
                                </div>
                                <div class="progress {{ $progressClass }}" @if ($seg->render_status === StepStatus::Pending) style="opacity:0.4" @endif>
                                    <i style="width: {{ $progressPct }}%;"></i>
                                </div>
                                <span class="{{ $seg->render_status->pillClass() }}" style="height: 22px; font-size: 11px;">
                                    @switch($seg->render_status)
                                        @case(StepStatus::Done)    <svg class="ic ic-sm"><use href="#i-check"/></svg> Done @break
                                        @case(StepStatus::Running) <svg class="ic ic-sm"><use href="#i-loader"/></svg> Rendering @break
                                        @case(StepStatus::Failed)  <svg class="ic ic-sm"><use href="#i-alert"/></svg> Failed @break
                                        @default                   <svg class="ic ic-sm"><use href="#i-clock"/></svg> Pending
                                    @endswitch
                                </span>
                                <div style="display:flex; gap: 6px; justify-content:flex-end;">
                                    @if ($seg->render_status === StepStatus::Done)
                                        <button class="btn sm {{ ! $isOpen ? 'ghost' : '' }}" wire:click="togglePlayer({{ $seg->id }})">
                                            <svg class="ic ic-sm"><use href="{{ $isOpen ? '#i-pause' : '#i-play' }}"/></svg>
                                            {{ $isOpen ? 'Playing' : 'Play' }}
                                        </button>
                                        <button class="btn sm" wire:click="downloadSegment({{ $seg->id }})">
                                            <svg class="ic ic-sm"><use href="#i-download"/></svg>Download
                                        </button>
                                    @endif
                                </div>
                            </div>

                            @if ($isOpen && $seg->render_status === StepStatus::Done)
                                <div style="padding: 0 24px 14px;">
                                    <div style="aspect-ratio: {{ $selected['width'] }}/{{ $selected['height'] }}; max-width: 460px; background: #1a1a18; border-radius: var(--radius); position: relative; overflow: hidden;">
                                        <video src="{{ route('segments.stream', $seg) }}" controls
                                               style="width: 100%; height: 100%; display:block;"></video>
                                        <div style="position:absolute; left: 14px; top: 14px; pointer-events:none;">
                                            <span class="pill" style="background: rgba(255,255,255,0.15); border-color: transparent; color: #fff; height: 24px;">
                                                မြန်မာ · {{ $seg->label() }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="footer-row">
            <span>Rendering automatically · play and download finished parts inline</span>
            <span>Step 6 of 6 · Render</span>
        </div>
    </div>
</div>
