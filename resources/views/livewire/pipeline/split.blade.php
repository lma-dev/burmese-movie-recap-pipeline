@php
    use App\Enums\StepStatus;
    use App\Enums\AudioMixMode;
    use App\Enums\VoiceTone;

    $segments     = $project->segments;
    $status       = $project->split_status;
    $done         = $segments->filter(fn ($s) => $s->video_path)->count();
    $estParts     = (int) ceil(($project->source_duration_sec ?? 0) / $segmentSeconds);
    $lastSec      = ($project->source_duration_sec ?? 0) % $segmentSeconds;
    $total        = $segments->count() ?: max(1, $estParts);
    $progressPct  = $total > 0 ? (int) round($done * 100 / $total) : 0;

    $hasStarted    = $status !== StepStatus::Pending;
    $selectedPreset = $presets[$framePreset] ?? null;
    $canContinue   = $status === StepStatus::Done;

    $toneIcons = [
        VoiceTone::Neutral->value   => 'microphone',
        VoiceTone::Dramatic->value  => 'fire',
        VoiceTone::Calm->value      => 'sparkles',
        VoiceTone::Energetic->value => 'bolt',
    ];
@endphp

<div class="flex-1 flex flex-col px-10 lg:px-20 py-8 gap-6">
    @include('partials.step-breadcrumb', ['current' => $step, 'project' => $project])

    {{-- Page header --}}
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="space-y-2">
            <span class="text-[11px] font-semibold uppercase tracking-widest text-zinc-500">Step 02</span>
            <p class="text-sm text-zinc-500 max-w-2xl">
                Configure everything here. Cut length, narrator tone, audio mix, and output frame all run automatically with these values — the only manual step left is reviewing subtitles.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <flux:button wire:click="back" variant="primary" color="zinc" icon="arrow-left">Back</flux:button>

            <flux:modal.trigger name="confirm-settings">
                <flux:button variant="primary" icon-trailing="arrow-right">Continue</flux:button>
            </flux:modal.trigger>
        </div>
    </div>

    {{-- Continue confirmation dialog — confirms the locked-in settings before
         moving to Translate. All downstream steps run automatically with
         exactly these values. --}}
    <flux:modal name="confirm-settings" class="md:w-[520px]">
        <div class="space-y-5">
            <div>
                <flux:heading size="lg">Confirm settings</flux:heading>
                <flux:subheading>
                    These values drive every downstream step. After continuing, only the subtitle text can be edited.
                </flux:subheading>
            </div>

            <dl class="divide-y divide-zinc-100 rounded-xl ring-1 ring-zinc-200 overflow-hidden">
                <div class="grid grid-cols-[140px_1fr] items-center px-4 py-2.5">
                    <dt class="text-xs uppercase tracking-widest text-zinc-500">Segment length</dt>
                    <dd class="font-mono text-sm">
                        {{ intdiv($segmentSeconds, 60) }}:{{ str_pad($segmentSeconds % 60, 2, '0', STR_PAD_LEFT) }}
                        <span class="text-zinc-400">· {{ $estParts }} parts</span>
                    </dd>
                </div>
                <div class="grid grid-cols-[140px_1fr] items-center px-4 py-2.5">
                    <dt class="text-xs uppercase tracking-widest text-zinc-500">Narration tone</dt>
                    <dd class="text-sm font-medium">{{ $voiceTone->label() }}</dd>
                </div>
                <div class="grid grid-cols-[140px_1fr] items-center px-4 py-2.5">
                    <dt class="text-xs uppercase tracking-widest text-zinc-500">Audio mix</dt>
                    <dd class="text-sm font-medium">
                        {{ $mixMode === AudioMixMode::Duck ? 'Duck original' : 'Replace original' }}
                    </dd>
                </div>
                <div class="grid grid-cols-[140px_1fr] items-center px-4 py-2.5">
                    <dt class="text-xs uppercase tracking-widest text-zinc-500">Output frame</dt>
                    <dd class="text-sm font-medium">
                        {{ $selectedPreset['label'] ?? '—' }}
                        @if ($selectedPreset)
                            <span class="font-mono text-[11px] text-zinc-500">
                                · {{ $selectedPreset['width'] }}×{{ $selectedPreset['height'] }} · {{ $selectedPreset['fps'] }}fps
                            </span>
                        @endif
                    </dd>
                </div>
            </dl>

            <div class="flex items-center gap-2 rounded-lg bg-amber-50 ring-1 ring-amber-200 px-3 py-2 text-xs text-amber-800">
                <flux:icon.information-circle class="size-4 shrink-0" />
                <span>Clicking confirm will start cutting with these settings. They cannot be changed afterwards.</span>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button wire:click="confirmAndContinue" variant="primary" icon-trailing="arrow-right">
                    Confirm &amp; continue
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Settings grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 auto-rows-min">

        {{-- Segment length --}}
        <section class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm flex flex-col gap-3">
            <h3 class="text-sm font-semibold">Segment length</h3>

            <div class="flex flex-col gap-1.5">
                <div class="flex items-center justify-between font-mono text-[11px] text-zinc-500">
                    <span>1 min</span>
                    <span class="text-indigo-600 font-semibold text-sm">
                        {{ intdiv($segmentSeconds, 60) }}:{{ str_pad($segmentSeconds % 60, 2, '0', STR_PAD_LEFT) }}
                    </span>
                    <span>5 min</span>
                </div>
                <input
                    type="range"
                    min="60" max="300" step="30"
                    wire:model.live="segmentSeconds"
                    class="w-full accent-indigo-500"
                />
            </div>

            <div class="grid grid-cols-3 gap-1.5 text-[10px]">
                <div class="rounded-md bg-zinc-50 ring-1 ring-zinc-200 px-2 py-1.5">
                    <div class="font-semibold uppercase tracking-widest text-zinc-500">Source</div>
                    <div class="font-mono mt-0.5">
                        {{ sprintf('%02d:%02d', intdiv($project->source_duration_sec ?? 0, 60), ($project->source_duration_sec ?? 0) % 60) }}
                    </div>
                </div>
                <div class="rounded-md bg-zinc-50 ring-1 ring-zinc-200 px-2 py-1.5">
                    <div class="font-semibold uppercase tracking-widest text-zinc-500">Parts</div>
                    <div class="font-mono mt-0.5">{{ $estParts }}</div>
                </div>
                <div class="rounded-md bg-zinc-50 ring-1 ring-zinc-200 px-2 py-1.5">
                    <div class="font-semibold uppercase tracking-widest text-zinc-500">Last</div>
                    <div class="font-mono mt-0.5">
                        {{ sprintf('%02d:%02d', intdiv($lastSec, 60), $lastSec % 60) }}
                    </div>
                </div>
            </div>
        </section>

        {{-- Narration tone --}}
        <section class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm flex flex-col gap-3">
            <h3 class="text-sm font-semibold">Narration tone</h3>

            <div class="grid grid-cols-2 gap-2">
                @foreach ($tones as $tone)
                    @php
                        $checked = $voiceTone === $tone;
                        $icon    = $toneIcons[$tone->value];
                    @endphp
                    <label class="relative flex items-center gap-2 rounded-lg border p-2 transition cursor-pointer
                                  {{ $checked
                                      ? 'border-indigo-400 bg-indigo-50/60 ring-1 ring-indigo-200'
                                      : 'border-zinc-200 bg-white hover:border-zinc-300 hover:bg-zinc-50' }}">
                        <input type="radio" wire:model.live="voiceTone" value="{{ $tone->value }}" class="sr-only" />
                        <div class="grid place-items-center size-7 rounded-md shrink-0
                                    {{ $checked ? 'bg-white text-indigo-600 ring-1 ring-indigo-200' : 'bg-zinc-100 text-zinc-500' }}">
                            @switch($icon)
                                @case('microphone') <flux:icon.microphone class="size-4" /> @break
                                @case('fire')       <flux:icon.fire class="size-4" /> @break
                                @case('sparkles')   <flux:icon.sparkles class="size-4" /> @break
                                @case('bolt')       <flux:icon.bolt class="size-4" /> @break
                            @endswitch
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-[13px] font-medium leading-tight {{ $checked ? 'text-indigo-900' : 'text-zinc-900' }}">
                                {{ $tone->label() }}
                            </div>
                            <div class="text-[10px] text-zinc-500 truncate">{{ $tone->description() }}</div>
                        </div>
                        @if ($checked)
                            <flux:icon.check class="size-3.5 text-indigo-500 shrink-0" />
                        @endif
                    </label>
                @endforeach
            </div>
        </section>

        {{-- Audio mixing --}}
        <section class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm flex flex-col gap-3">
            <h3 class="text-sm font-semibold">Audio mixing</h3>

            @php
                $mixOpts = [
                    AudioMixMode::Duck->value => [
                        'title' => 'Duck original',
                        'sub'   => 'Auto-duck source to ~−18 dB while narrator speaks.',
                    ],
                    AudioMixMode::Replace->value => [
                        'title' => 'Replace original',
                        'sub'   => 'Narrator only. Dialogue and music dropped.',
                    ],
                ];
            @endphp

            <div class="flex flex-col gap-2">
                @foreach ($mixOpts as $val => $opt)
                    @php $checked = $mixMode->value === $val; @endphp
                    <label class="flex items-start gap-2.5 rounded-lg border p-2.5 transition cursor-pointer
                                  {{ $checked
                                      ? 'border-indigo-400 bg-indigo-50/60 ring-1 ring-indigo-200'
                                      : 'border-zinc-200 bg-white hover:bg-zinc-50' }}">
                        <input type="radio" wire:model.live="mixMode" value="{{ $val }}" class="sr-only" />
                        <div class="size-3.5 mt-0.5 rounded-full border-2 grid place-items-center shrink-0
                                    {{ $checked ? 'border-indigo-500' : 'border-zinc-300' }}">
                            @if ($checked)
                                <div class="size-1.5 rounded-full bg-indigo-500"></div>
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-1.5">
                                <div class="text-[13px] font-medium">{{ $opt['title'] }}</div>
                                @if ($val === 'duck')
                                    <span class="text-[9px] font-mono uppercase tracking-wider text-indigo-500">default</span>
                                @endif
                            </div>
                            <div class="text-[10px] text-zinc-500 leading-snug">{{ $opt['sub'] }}</div>
                        </div>
                    </label>
                @endforeach
            </div>
        </section>

        {{-- Frame preset --}}
        <section class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm flex flex-col gap-3 md:col-span-2 xl:col-span-3">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold">Output frame</h3>
                @if ($selectedPreset)
                    <span class="font-mono text-[11px] text-zinc-500">
                        {{ $selectedPreset['width'] }}×{{ $selectedPreset['height'] }} · {{ $selectedPreset['aspect'] }} · {{ $selectedPreset['fps'] }}fps
                    </span>
                @endif
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                @foreach ($presets as $key => $preset)
                    @php $checked = $key === $framePreset; @endphp
                    <label class="flex flex-col gap-1.5 rounded-lg p-3 transition cursor-pointer
                                  {{ $checked
                                      ? 'border-2 border-indigo-400 bg-indigo-50/60 ring-1 ring-indigo-200'
                                      : 'border border-zinc-200 bg-zinc-50/40 hover:bg-zinc-50' }}">
                        <input type="radio" wire:model.live="framePreset" value="{{ $key }}" class="sr-only" />
                        <div class="flex items-center gap-2">
                            <div class="size-3.5 rounded-full border-2 grid place-items-center shrink-0
                                        {{ $checked ? 'border-indigo-500' : 'border-zinc-300' }}">
                                @if ($checked)
                                    <div class="size-1.5 rounded-full bg-indigo-500"></div>
                                @endif
                            </div>
                            <span class="text-[13px] font-semibold">{{ $preset['label'] }}</span>
                            <span class="ml-auto inline-flex items-center rounded-full bg-white text-zinc-600 px-1.5 py-0.5 text-[9px] ring-1 ring-zinc-200">
                                {{ $preset['platform'] }}
                            </span>
                        </div>
                        <div class="font-mono text-[10px] text-zinc-500">
                            {{ $preset['width'] }}×{{ $preset['height'] }} · {{ $preset['aspect'] }} · {{ $preset['fps'] }}fps
                        </div>
                    </label>
                @endforeach
            </div>
        </section>
    </div>


    <footer class="flex items-center justify-between border-t border-dashed border-zinc-200 pt-4 text-xs text-zinc-500">
        <span>Settings apply to all downstream steps</span>
        <span>Step 2 of 5 · Settings</span>
    </footer>
</div>
