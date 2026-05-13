@php
    use App\Enums\StepStatus;

    $segments      = $project->segments;
    $voiceStatus   = $project->voice_status  ?? StepStatus::Pending;
    $renderStatus  = $project->render_status ?? StepStatus::Pending;
    $finalize      = $project->finalizeStatus();
    $isDone        = $finalize === StepStatus::Done;
    $isFailed      = $finalize === StepStatus::Failed;
@endphp

<div wire:poll.3s class="flex-1 flex flex-col px-10 lg:px-20 py-8 gap-6">
    @include('partials.step-breadcrumb', ['current' => $step, 'project' => $project])

    {{-- Page header --}}
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="space-y-2">
            <span class="text-[11px] font-semibold uppercase tracking-widest text-zinc-500">Step 05</span>
            <p class="text-sm text-zinc-500 max-w-2xl">
                Voice generation and rendering run automatically with the settings from Split. When each part finishes rendering you can play and download it inline.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <flux:button wire:click="back" variant="ghost">Back</flux:button>
            @if ($isFailed)
                <flux:button wire:click="retry" variant="primary" color="rose" icon="arrow-path">
                    Retry
                </flux:button>
            @endif
            <flux:button
                wire:click="downloadZip"
                :variant="$renderDone > 0 ? 'primary' : 'subtle'"
                icon="archive-box-arrow-down"
                :disabled="$renderDone === 0">
                Download ZIP
            </flux:button>
        </div>
    </div>

    {{-- Settings recap — read-only, just a reminder of what's running --}}
    <section class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-xs">
            <div>
                <div class="text-[10px] uppercase tracking-widest text-zinc-500">Segments</div>
                <div class="font-mono mt-0.5 text-sm">{{ $total }}</div>
            </div>
            <div>
                <div class="text-[10px] uppercase tracking-widest text-zinc-500">Tone</div>
                <div class="mt-0.5 text-sm font-medium">{{ $project->voice_tone->label() }}</div>
            </div>
            <div>
                <div class="text-[10px] uppercase tracking-widest text-zinc-500">Audio mix</div>
                <div class="mt-0.5 text-sm font-medium">{{ $project->audio_mix_mode->value === 'duck' ? 'Duck original' : 'Replace original' }}</div>
            </div>
            <div>
                <div class="text-[10px] uppercase tracking-widest text-zinc-500">Frame</div>
                <div class="mt-0.5 text-sm font-medium">{{ $preset['label'] ?? '—' }}
                    <span class="font-mono text-[10px] text-zinc-500">{{ $preset['width'] ?? '' }}×{{ $preset['height'] ?? '' }}</span>
                </div>
            </div>
        </div>
    </section>

    {{-- Two progress lanes: Voice + Render --}}
    <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm flex flex-col gap-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            {{-- Voice lane --}}
            <div class="flex flex-col gap-2">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <flux:icon.microphone class="size-4 text-indigo-500" />
                        <h3 class="text-sm font-semibold">Voice generation</h3>
                    </div>
                    <span class="font-mono text-xs text-zinc-500">{{ $voiceDone }}/{{ $total }} · {{ $voicePct }}%</span>
                </div>
                <div class="h-2 w-full overflow-hidden rounded-full bg-zinc-100">
                    <div class="h-full rounded-full bg-linear-to-r {{ $voiceStatus === StepStatus::Done ? 'from-emerald-500 to-emerald-400' : ($voiceStatus === StepStatus::Failed ? 'from-rose-500 to-rose-400' : 'from-indigo-500 to-violet-500') }} transition-all duration-500"
                         style="width: {{ $voicePct }}%"></div>
                </div>
                <div class="text-[11px] text-zinc-500">
                    @switch($voiceStatus)
                        @case(StepStatus::Done)     <span class="text-emerald-700">Complete — all narrator tracks ready.</span> @break
                        @case(StepStatus::Running)  Generating Burmese narration… @break
                        @case(StepStatus::Failed)   <span class="text-rose-700">Voice generation failed.</span> @break
                        @default                    Waiting to start. @break
                    @endswitch
                </div>
            </div>

            {{-- Render lane --}}
            <div class="flex flex-col gap-2">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <flux:icon.film class="size-4 text-indigo-500" />
                        <h3 class="text-sm font-semibold">Render</h3>
                    </div>
                    <span class="font-mono text-xs text-zinc-500">{{ $renderDone }}/{{ $total }} · {{ $renderPct }}%</span>
                </div>
                <div class="h-2 w-full overflow-hidden rounded-full bg-zinc-100">
                    <div class="h-full rounded-full bg-linear-to-r {{ $renderStatus === StepStatus::Done ? 'from-emerald-500 to-emerald-400' : ($renderStatus === StepStatus::Failed ? 'from-rose-500 to-rose-400' : 'from-indigo-500 to-violet-500') }} transition-all duration-500"
                         style="width: {{ $renderPct }}%"></div>
                </div>
                <div class="text-[11px] text-zinc-500">
                    @switch($renderStatus)
                        @case(StepStatus::Done)     <span class="text-emerald-700">All parts rendered — ready to download.</span> @break
                        @case(StepStatus::Running)  Burning subtitles and muxing audio… @break
                        @case(StepStatus::Failed)   <span class="text-rose-700">Render failed.</span> @break
                        @default                    {{ $voiceStatus === StepStatus::Done ? 'Starting…' : 'Waits for voice to finish.' }} @break
                    @endswitch
                </div>
            </div>
        </div>

        @if ($isFailed && $project->last_error)
            <div class="rounded-lg bg-rose-50 ring-1 ring-rose-200 px-3 py-2 text-xs text-rose-700">
                <span class="font-semibold">Last error:</span> {{ $project->last_error }}
            </div>
        @endif
    </section>

    {{-- Per-segment list with inline players --}}
    <section class="rounded-2xl border border-zinc-200 bg-white shadow-sm overflow-hidden">
        <header class="flex items-center justify-between gap-3 px-5 py-3 border-b border-zinc-100">
            <h2 class="text-sm font-semibold">Segments</h2>
            <span class="font-mono text-[11px] text-zinc-500">{{ $renderDone }} rendered · {{ $voiceDone }} voiced</span>
        </header>

        <div class="grid grid-cols-[64px_1fr_1fr_120px_180px] px-5 py-2 bg-zinc-50/60 text-[10px] font-semibold uppercase tracking-widest text-zinc-500">
            <div>Part</div>
            <div>Voice</div>
            <div>Render</div>
            <div class="text-right">Status</div>
            <div class="text-right">Actions</div>
        </div>

        <ul class="divide-y divide-zinc-100">
            @foreach ($segments as $seg)
                @php
                    $isOpen      = $openPlayerSegmentId === $seg->id;
                    $vStat       = $seg->voice_status;
                    $rStat       = $seg->render_status;
                    $segDone     = $rStat === StepStatus::Done;
                    $segFailed   = $vStat === StepStatus::Failed || $rStat === StepStatus::Failed;
                    $segRunning  = ! $segDone && ! $segFailed && ($vStat === StepStatus::Running || $rStat === StepStatus::Running);
                    $vPct = $vStat === StepStatus::Done ? 100 : ($vStat === StepStatus::Running ? max(10, $seg->voice_progress ?: 30) : 0);
                    $rPct = $rStat === StepStatus::Done ? 100 : ($rStat === StepStatus::Running ? max(10, $seg->render_progress ?: 30) : 0);
                    $vBar = $vStat === StepStatus::Done ? 'from-emerald-500 to-emerald-400'
                          : ($vStat === StepStatus::Failed ? 'from-rose-500 to-rose-400' : 'from-indigo-500 to-violet-500');
                    $rBar = $rStat === StepStatus::Done ? 'from-emerald-500 to-emerald-400'
                          : ($rStat === StepStatus::Failed ? 'from-rose-500 to-rose-400' : 'from-indigo-500 to-violet-500');
                @endphp
                <li class="{{ $segRunning ? 'bg-indigo-50/40' : '' }}">
                    <div class="grid grid-cols-[64px_1fr_1fr_120px_180px] items-center gap-3 px-5 py-2.5 text-sm">
                        <div class="font-mono text-xs font-medium {{ $segRunning ? 'text-indigo-600' : ($segDone ? 'text-zinc-900' : 'text-zinc-400') }}">
                            {{ $seg->label() }}
                        </div>
                        <div class="h-1.5 w-full overflow-hidden rounded-full bg-zinc-100">
                            <div class="h-full rounded-full bg-linear-to-r {{ $vBar }} transition-all duration-500" style="width: {{ $vPct }}%"></div>
                        </div>
                        <div class="h-1.5 w-full overflow-hidden rounded-full bg-zinc-100">
                            <div class="h-full rounded-full bg-linear-to-r {{ $rBar }} transition-all duration-500" style="width: {{ $rPct }}%"></div>
                        </div>
                        <div class="flex justify-end">
                            @if ($segDone)
                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-medium bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200">
                                    <flux:icon.check class="size-2.5" /> Done
                                </span>
                            @elseif ($segFailed)
                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-medium bg-rose-50 text-rose-700 ring-1 ring-rose-200">
                                    <flux:icon.exclamation-triangle class="size-2.5" /> Failed
                                </span>
                            @elseif ($segRunning)
                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-medium bg-indigo-50 text-indigo-700 ring-1 ring-indigo-200">
                                    <flux:icon.arrow-path class="size-2.5 animate-spin" /> Running
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-medium bg-zinc-100 text-zinc-600 ring-1 ring-zinc-200">
                                    <flux:icon.clock class="size-2.5" /> Pending
                                </span>
                            @endif
                        </div>
                        <div class="flex gap-1.5 justify-end">
                            @if ($segDone)
                                <flux:button size="sm" variant="{{ $isOpen ? 'primary' : 'ghost' }}"
                                    wire:click="togglePlayer({{ $seg->id }})"
                                    icon="{{ $isOpen ? 'pause' : 'play' }}">
                                    {{ $isOpen ? 'Playing' : 'Play' }}
                                </flux:button>
                                <flux:button size="sm" wire:click="downloadSegment({{ $seg->id }})" icon="arrow-down-tray" />
                            @endif
                        </div>
                    </div>

                    @if ($isOpen && $segDone)
                        <div class="px-5 pb-4">
                            <div class="rounded-xl bg-zinc-900 relative overflow-hidden max-w-md"
                                 style="aspect-ratio: {{ ($preset['width'] ?? 9) }}/{{ ($preset['height'] ?? 16) }};">
                                <video src="{{ route('segments.stream', $seg) }}" controls class="w-full h-full block"></video>
                                <div class="absolute left-3.5 top-3.5 pointer-events-none">
                                    <span class="inline-flex items-center rounded-full bg-white/15 text-white px-3 py-0.5 text-xs">
                                        မြန်မာ · {{ $seg->label() }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>

    <footer class="flex items-center justify-between border-t border-dashed border-zinc-200 pt-4 text-xs text-zinc-500">
        <span>
            Tone: <span class="font-medium text-zinc-700">{{ $project->voice_tone->label() }}</span> ·
            Frame: <span class="font-medium text-zinc-700">{{ $preset['label'] ?? '—' }}</span>
        </span>
        <span>Step 5 of 5 · Finalize</span>
    </footer>
</div>
