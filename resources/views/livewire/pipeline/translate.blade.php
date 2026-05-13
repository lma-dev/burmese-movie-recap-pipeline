@php
    use App\Enums\StepStatus;
    $segments = $project->segments;
@endphp

<div wire:poll.3s>
    <livewire:step-bar :current="$step" :project="$project" />

    <div class="body-wrap">
        <div class="body-head">
            <div class="title">
                <span class="label-eyebrow">Step 02</span>
                <h1>Transcribe &amp; translate</h1>
                <p class="sub">Runs automatically. Each segment is transcribed in its source language, then translated to Burmese.</p>
            </div>
            <div class="actions">
                <button class="btn" wire:click="back">Back</button>
                <button class="btn primary" wire:click="continueToEditSrt"
                        @disabled($project->translate_status !== StepStatus::Done)>
                    Continue<svg class="ic"><use href="#i-arrow-r"/></svg>
                </button>
            </div>
        </div>

        <div style="flex:1; display:flex; justify-content:center; align-items:flex-start;">
            <div class="card" style="width: 980px; display:flex; flex-direction:column; overflow:hidden;">

                <div style="padding: 18px 24px; border-bottom: 0.5px solid var(--border); display:flex; flex-direction:column; gap: 10px;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <h3 class="h-sub">Overall progress</h3>
                        <span class="mono muted" style="font-size: 12px;">{{ $doneCount }} of {{ $segments->count() }} segments done</span>
                    </div>
                    <div class="progress"><i style="width: {{ $progress }}%;"></i></div>
                </div>

                <div style="display:grid; grid-template-columns: 100px 1fr 1fr; padding: 12px 24px; border-bottom: 0.5px solid var(--border); font-size: 11px; color: var(--ink-3); letter-spacing: 0.1em; text-transform: uppercase; font-weight: 500; background: var(--surface-2);">
                    <div>Part</div>
                    <div>Transcription</div>
                    <div>Translation → my</div>
                </div>

                @foreach ($segments as $seg)
                    <div style="display:grid; grid-template-columns: 100px 1fr 1fr; padding: 16px 24px; {{ ! $loop->last ? 'border-bottom: 0.5px solid var(--border);' : '' }} align-items:center; font-size: 13px;">
                        <div class="mono" style="font-weight:500; color: {{ $seg->transcribe_status === StepStatus::Pending ? 'var(--ink-3)' : 'inherit' }};">
                            {{ $seg->label() }}
                        </div>

                        <span class="{{ $seg->transcribe_status->pillClass() }}" style="justify-self: flex-start;">
                            @switch($seg->transcribe_status)
                                @case(StepStatus::Done)
                                    <svg class="ic ic-sm"><use href="#i-check"/></svg> Done
                                    @break
                                @case(StepStatus::Running)
                                    <svg class="ic ic-sm"><use href="#i-loader"/></svg> Transcribing
                                    @break
                                @case(StepStatus::Failed)
                                    <svg class="ic ic-sm"><use href="#i-alert"/></svg> Failed
                                    @break
                                @default
                                    <svg class="ic ic-sm"><use href="#i-clock"/></svg> Pending
                            @endswitch
                        </span>

                        <span class="{{ $seg->translate_status->pillClass() }}" style="justify-self: flex-start;">
                            @switch($seg->translate_status)
                                @case(StepStatus::Done)
                                    <svg class="ic ic-sm"><use href="#i-check"/></svg> Done
                                    @break
                                @case(StepStatus::Running)
                                    <svg class="ic ic-sm"><use href="#i-loader"/></svg> Translating
                                    @break
                                @case(StepStatus::Failed)
                                    <svg class="ic ic-sm"><use href="#i-alert"/></svg> Failed
                                    @break
                                @default
                                    <svg class="ic ic-sm"><use href="#i-clock"/></svg> Pending
                            @endswitch
                        </span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="footer-row">
            <span>Running automatically · no user input required</span>
            <span>Step 3 of 6 · Translate</span>
        </div>
    </div>
</div>
