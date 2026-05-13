@php
    use App\Enums\StepStatus;
    $status = $project?->source_status ?? StepStatus::Pending;
@endphp

<div>
    <livewire:step-bar :current="$step" :project="$project" />

    <div class="body-wrap">
        <div class="body-head">
            <div class="title">
                <span class="label-eyebrow">Step 00</span>
                <h1>Source</h1>
                <p class="sub">Paste a movie recap URL. The app fetches metadata and confirms the source is ready before moving on.</p>
            </div>
            <div class="actions">
                <button type="button" class="btn ghost" wire:click="$set('sourceUrl','')">Cancel</button>
                <button type="button" class="btn primary"
                        wire:click="continueToSplit"
                        @disabled($status !== StepStatus::Done)>
                    Continue<svg class="ic"><use href="#i-arrow-r"/></svg>
                </button>
            </div>
        </div>

        <div style="flex:1; display:flex; align-items:center; justify-content:center; padding: 12px 0;">
            <div class="card" style="width: 720px; padding: 40px 48px; display:flex; flex-direction:column; gap: 24px;">

                <div style="display:flex; flex-direction:column; gap: 6px; text-align:center;">
                    <h3 class="h-section" style="text-align:center;">Paste a video URL</h3>
                    <p class="muted" style="font-size: 13px; margin: 0;">YouTube, TikTok, Facebook, and Xiaohongshu links are supported.</p>
                </div>

                <form wire:submit="fetch" style="display:flex; flex-direction:column; gap: 12px;">
                    <input
                        type="url"
                        wire:model="sourceUrl"
                        placeholder="https://www.youtube.com/watch?v=…"
                        class="input mono"
                        style="font-size: 14px;"
                        @disabled($status === StepStatus::Running)
                    />
                    @error('sourceUrl') <span class="muted" style="color: var(--red); font-size: 12px;">{{ $message }}</span> @enderror

                    <button type="submit" class="btn primary" style="height: 44px; align-self: flex-start;"
                            @disabled($status === StepStatus::Running)>
                        <svg class="ic"><use href="#i-link"/></svg>
                        {{ $status === StepStatus::Running ? 'Fetching…' : 'Fetch' }}
                    </button>
                </form>

                <div style="display:flex; gap: 6px; justify-content:center; flex-wrap:wrap; padding-top: 4px;">
                    <span class="pill" style="opacity: 0.85;">YouTube</span>
                    <span class="pill" style="opacity: 0.85;">TikTok</span>
                    <span class="pill" style="opacity: 0.85;">Facebook</span>
                    <span class="pill" style="opacity: 0.85;">Xiaohongshu</span>
                </div>

                {{-- Live progress card. Polls the project row every 2s while running. --}}
                @if ($project && $status !== StepStatus::Pending)
                    <div wire:poll.2s class="card-2" style="padding: 16px; display:flex; flex-direction:column; gap: 12px;">
                        <div style="display:flex; justify-content:space-between; font-size:12px;">
                            <span class="ink-2" style="font-weight:500;">{{ $status->label() }}</span>
                        </div>
                        <div class="progress {{ $status === StepStatus::Done ? 'green' : '' }}">
                            <i style="width: {{ $status === StepStatus::Done ? 100 : 60 }}%;"></i>
                        </div>
                        <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap: 8px; font-size: 11px; color: var(--ink-3);">
                            @php $done = $status === StepStatus::Done; @endphp
                            <div style="display:flex; align-items:center; gap:6px;">
                                <svg class="ic ic-sm" style="color: var(--green);"><use href="#i-check"/></svg>Connecting
                            </div>
                            <div style="display:flex; align-items:center; gap:6px;">
                                <svg class="ic ic-sm" style="color: {{ $done ? 'var(--green)' : 'var(--ink-3)' }};"><use href="#i-check"/></svg>Resolving
                            </div>
                            <div style="display:flex; align-items:center; gap:6px;">
                                <svg class="ic ic-sm" style="color: {{ $done ? 'var(--green)' : 'var(--ink-3)' }};"><use href="#i-check"/></svg>Fetching metadata
                            </div>
                            <div style="display:flex; align-items:center; gap:6px; color: {{ $done ? 'var(--green)' : 'var(--ink-3)' }}; font-weight: 500;">
                                <svg class="ic ic-sm"><use href="#i-check"/></svg>Ready
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Resolved metadata row, shown once fetch succeeds. --}}
                @if ($project && $project->source_title)
                    <div class="card-2" style="padding: 16px; display:flex; align-items:center; gap: 14px;">
                        <svg class="ic ic-lg" style="color: var(--ink-3);"><use href="#i-video"/></svg>
                        <div style="flex:1; min-width:0;">
                            <div style="font-size: 15px; font-weight: 500; line-height: 1.3; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                {{ $project->source_title }}
                            </div>
                            <div class="mono muted" style="font-size: 12px; margin-top: 4px;">
                                Total duration · {{ sprintf('%02d:%02d', intdiv((int)$project->source_duration_sec, 60), $project->source_duration_sec % 60) }}
                            </div>
                        </div>
                        <span class="pill green"><svg class="ic ic-sm"><use href="#i-check"/></svg>Ready</span>
                    </div>
                @endif

                @if ($project?->last_error)
                    <div class="card-2" style="padding: 14px; background: var(--red-bg); color: var(--red); font-size: 12px;">
                        {{ $project->last_error }}
                    </div>
                @endif
            </div>
        </div>

        <div class="footer-row">
            <span><span class="mono">⌘V</span> Paste · <span class="mono">⌘↵</span> Fetch · <span class="mono">→</span> Continue</span>
            <span>Step 1 of 6 · Source</span>
        </div>
    </div>
</div>
