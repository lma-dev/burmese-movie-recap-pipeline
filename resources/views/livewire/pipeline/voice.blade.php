@php
    use App\Enums\StepStatus;
    use App\Enums\AudioMixMode;
    $segments = $project->segments;
@endphp

<div wire:poll.3s>
    <livewire:step-bar :current="$step" :project="$project" />

    <div class="body-wrap">
        <div class="body-head">
            <div class="title">
                <span class="label-eyebrow">Step 04</span>
                <h1>Generate narrator voice</h1>
                <p class="sub">Runs automatically. A Burmese narrator track is generated for each segment, then mixed with the original audio.</p>
            </div>
            <div class="actions">
                <button class="btn" wire:click="back">Back</button>
                <button class="btn primary" wire:click="continueToRender"
                        @disabled($project->voice_status !== StepStatus::Done)>
                    Continue<svg class="ic"><use href="#i-arrow-r"/></svg>
                </button>
            </div>
        </div>

        <div style="flex:1; display:flex; justify-content:center; align-items:flex-start;">
            <div style="width: 980px; display:flex; flex-direction:column; gap: 16px;">

                <div class="card" style="display:flex; flex-direction:column; overflow:hidden;">
                    <div style="padding: 16px 24px; border-bottom: 0.5px solid var(--border); display:flex; flex-direction:column; gap: 10px;">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <h3 class="h-sub">Overall progress</h3>
                            <span class="mono muted" style="font-size: 12px;">{{ $doneCount }} of {{ $segments->count() }} ready</span>
                        </div>
                        <div class="progress"><i style="width: {{ $progress }}%;"></i></div>
                    </div>

                    <div style="display:grid; grid-template-columns: 100px 1fr 130px; padding: 12px 24px; border-bottom: 0.5px solid var(--border); font-size: 11px; color: var(--ink-3); letter-spacing: 0.1em; text-transform: uppercase; font-weight: 500; background: var(--surface-2);">
                        <div>Part</div>
                        <div>Generation</div>
                        <div>Status</div>
                    </div>

                    @foreach ($segments as $seg)
                        @php
                            $rowBg = $seg->voice_status === StepStatus::Running ? 'var(--blue-bg)' : 'transparent';
                            $progressClass = $seg->voice_status === StepStatus::Done ? 'green' : '';
                            $progressPct = match (true) {
                                $seg->voice_status === StepStatus::Done    => 100,
                                $seg->voice_status === StepStatus::Running => max(10, $seg->voice_progress ?: 30),
                                default => 0,
                            };
                        @endphp
                        <div style="display:grid; grid-template-columns: 100px 1fr 130px; padding: 14px 24px; {{ ! $loop->last ? 'border-bottom: 0.5px solid var(--border);' : '' }} align-items:center; gap: 16px; font-size: 13px; background: {{ $rowBg }};">
                            <div class="mono" style="font-weight:500; color: {{ $seg->voice_status === StepStatus::Pending ? 'var(--ink-3)' : ($seg->voice_status === StepStatus::Running ? 'var(--blue)' : 'inherit') }};">
                                {{ $seg->label() }}
                            </div>
                            <div class="progress {{ $progressClass }}" @if ($seg->voice_status === StepStatus::Pending) style="opacity:0.4" @endif>
                                <i style="width:{{ $progressPct }}%;"></i>
                            </div>
                            <span class="{{ $seg->voice_status->pillClass() }}" style="height: 22px; font-size: 11px;">
                                @switch($seg->voice_status)
                                    @case(StepStatus::Done)    <svg class="ic ic-sm"><use href="#i-check"/></svg> Done @break
                                    @case(StepStatus::Running) <svg class="ic ic-sm"><use href="#i-loader"/></svg> Generating @break
                                    @case(StepStatus::Failed)  <svg class="ic ic-sm"><use href="#i-alert"/></svg> Failed @break
                                    @default                   <svg class="ic ic-sm"><use href="#i-clock"/></svg> Pending
                                @endswitch
                            </span>
                        </div>
                    @endforeach
                </div>

                {{-- Audio mixing card --}}
                <div class="card card-pad" style="display:flex; flex-direction:column; gap: 14px;">
                    <div style="display:flex; justify-content:space-between; align-items:baseline;">
                        <h3 class="h-sub">Audio mixing</h3>
                        <span class="mono muted" style="font-size: 12px;">applies to all segments</span>
                    </div>
                    <div style="display:flex; flex-direction:column; gap: 10px;">
                        @php
                            $opts = [
                                AudioMixMode::Replace->value => [
                                    'title' => 'Replace original audio entirely',
                                    'sub'   => 'Use the Burmese narrator track only. Original dialogue and music are dropped.',
                                ],
                                AudioMixMode::Duck->value => [
                                    'title' => 'Keep original audio at low volume under narrator',
                                    'sub'   => 'Auto-duck the source audio to roughly −18 dB whenever the narrator is speaking.',
                                ],
                            ];
                        @endphp
                        @foreach ($opts as $val => $opt)
                            @php $checked = $mixMode->value === $val; @endphp
                            <label style="display:flex; align-items:flex-start; gap: 12px; padding: 14px 16px;
                                          border: {{ $checked ? '1.5px solid var(--blue)' : '0.5px solid var(--border)' }};
                                          background: {{ $checked ? 'var(--blue-bg)' : 'transparent' }};
                                          border-radius: var(--radius); cursor:pointer;">
                                <input type="radio" wire:model.live="mixMode" value="{{ $val }}" style="display:none;" />
                                <span class="radio {{ $checked ? 'checked' : '' }}" style="margin-top: 2px;"></span>
                                <div style="display:flex; flex-direction:column; gap: 4px;">
                                    <div style="font-size: 14px; font-weight: 500;">
                                        {{ $opt['title'] }}
                                        @if ($val === 'duck')
                                            <span class="mono" style="color: var(--blue); font-weight: 400; margin-left: 4px;">default</span>
                                        @endif
                                    </div>
                                    <div style="font-size: 12px; color: var(--ink-2);">{{ $opt['sub'] }}</div>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="footer-row">
            <span>Running automatically · narrator-only, no lip-sync</span>
            <span>Step 5 of 6 · Voice</span>
        </div>
    </div>
</div>
