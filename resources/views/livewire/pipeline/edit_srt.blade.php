@php
    use App\Enums\StepStatus;

    $readOnly = $project && $project->current_step->order() > $step->order();

    $statusBadge = match (true) {
        $readOnly                                                  => ['zinc',    'lock-closed',  'View only · locked'],
        ($project->edit_srt_status ?? StepStatus::Pending) === StepStatus::Done    => ['emerald', 'check-circle', 'Reviewed'],
        ($project->edit_srt_status ?? StepStatus::Pending) === StepStatus::Running => ['indigo',  'arrow-path',   'Editing'],
        default                                                    => ['indigo',  'pencil-square','In review'],
    };
    [$badgeColor, $badgeIcon, $badgeText] = $statusBadge;
@endphp

<div class="flex-1 flex flex-col px-10 lg:px-20 py-8 gap-6">
    @include('partials.step-breadcrumb', ['current' => $step, 'project' => $project])

    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="space-y-2">
            <span class="text-[11px] font-semibold uppercase tracking-widest text-zinc-500">Step 04</span>
            <p class="text-sm text-zinc-500 max-w-2xl">
                Refine the auto-translated lines. The preview on the right updates live as you select a row.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <flux:button wire:click="back" variant="primary" color="zinc" icon="arrow-left">Back</flux:button>
            <flux:modal.trigger name="confirm-finalize">
                <flux:button variant="primary" icon-trailing="arrow-right">Next</flux:button>
            </flux:modal.trigger>
        </div>
    </div>

    {{-- Confirm before starting voice + render --}}
    <flux:modal name="confirm-finalize" class="md:w-[520px]">
        <div class="space-y-5">
            <div>
                <flux:heading size="lg">Start voice &amp; render?</flux:heading>
                <flux:subheading>
                    We'll lock in your subtitle edits, then generate the Burmese narration and render each part. You won't be able to edit subtitles after this.
                </flux:subheading>
            </div>

            <dl class="divide-y divide-zinc-100 rounded-xl ring-1 ring-zinc-200 overflow-hidden">
                <div class="grid grid-cols-[140px_1fr] items-center px-4 py-2.5">
                    <dt class="text-xs uppercase tracking-widest text-zinc-500">Segments</dt>
                    <dd class="font-mono text-sm">{{ $project->segments->count() }} parts</dd>
                </div>
                <div class="grid grid-cols-[140px_1fr] items-center px-4 py-2.5">
                    <dt class="text-xs uppercase tracking-widest text-zinc-500">Narration tone</dt>
                    <dd class="text-sm font-medium">{{ $project->voice_tone->label() }}</dd>
                </div>
                <div class="grid grid-cols-[140px_1fr] items-center px-4 py-2.5">
                    <dt class="text-xs uppercase tracking-widest text-zinc-500">Audio mix</dt>
                    <dd class="text-sm font-medium">
                        {{ $project->audio_mix_mode->value === 'duck' ? 'Duck original' : 'Replace original' }}
                    </dd>
                </div>
                <div class="grid grid-cols-[140px_1fr] items-center px-4 py-2.5">
                    <dt class="text-xs uppercase tracking-widest text-zinc-500">Output frame</dt>
                    <dd class="text-sm font-medium">{{ $project->frame_preset }}</dd>
                </div>
            </dl>

            <div class="flex items-center gap-2 rounded-lg bg-amber-50 ring-1 ring-amber-200 px-3 py-2 text-xs text-amber-800">
                <flux:icon.information-circle class="size-4 shrink-0" />
                <span>Confirm to start generation. This cannot be undone.</span>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button wire:click="confirmAndFinalize" variant="primary" icon-trailing="arrow-right">
                    Confirm &amp; start
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- Left: SRT editor --}}
        <section class="rounded-2xl border border-zinc-200 bg-white shadow-sm overflow-hidden flex flex-col h-[calc(100vh-220px)] min-h-[420px]">
            <header class="flex items-center justify-between gap-3 px-4 py-3 border-b border-zinc-100 bg-white/80 backdrop-blur sticky top-0 z-10">
                <div class="flex items-center gap-2 rounded-lg ring-1 ring-zinc-200 px-3 py-1.5 bg-white text-sm flex-1 min-w-0">
                    <flux:icon.folder class="size-4 text-zinc-500 shrink-0" />
                    <select wire:model.live="segmentId" class="font-medium border-none bg-transparent flex-1 outline-none text-sm min-w-0">
                        @foreach ($project->segments as $s)
                            <option value="{{ $s->id }}">{{ $s->label() }} — {{ $s->range() }}</option>
                        @endforeach
                    </select>
                    <flux:icon.chevron-down class="size-4 text-zinc-500 shrink-0" />
                </div>
                <span class="font-mono text-xs text-zinc-500 whitespace-nowrap">{{ count($lines) }} lines</span>
            </header>

            <div class="flex-1 overflow-y-auto divide-y divide-zinc-100">
                @foreach ($lines as $i => $line)
                    @php $selected = $line['id'] === $selectedLineId; @endphp
                    <div
                        wire:key="line-{{ $line['id'] }}"
                        wire:click="selectLine({{ $line['id'] }})"
                        class="group cursor-pointer transition flex gap-3 px-4 py-2.5
                               {{ $selected
                                   ? 'bg-indigo-50/70 border-l-2 border-indigo-500'
                                   : 'border-l-2 border-transparent hover:bg-zinc-50' }}"
                    >
                        <div class="flex flex-col items-end shrink-0 w-16 pt-0.5">
                            <span class="font-mono text-[11px] font-semibold {{ $selected ? 'text-indigo-600' : 'text-zinc-400' }}">
                                #{{ str_pad($line['line_number'], 2, '0', STR_PAD_LEFT) }}
                            </span>
                            <span class="font-mono text-[10px] leading-tight {{ $selected ? 'text-indigo-500' : 'text-zinc-400' }}">
                                {{ $line['start_tc'] }}
                            </span>
                        </div>
                        <div class="flex-1 min-w-0 flex flex-col gap-1">
                            <textarea
                                rows="1"
                                class="font-my w-full border-none resize-none outline-none bg-transparent p-0 text-[15px] leading-snug min-h-[1.5rem] disabled:text-zinc-500 disabled:cursor-not-allowed"
                                wire:model="lines.{{ $i }}.burmese_text"
                                @if (! $readOnly)
                                    wire:change="saveLine({{ $line['id'] }})"
                                @endif
                                @disabled($readOnly)
                                @readonly($readOnly)
                            ></textarea>
                            @if (! empty($line['source_text']))
                                <div class="text-[11px] text-zinc-400 italic truncate group-hover:text-zinc-500">
                                    {{ $line['source_text'] }}
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <footer class="px-4 py-3 border-t border-zinc-100 flex items-center gap-2">
                <flux:button wire:click="exportSrt" size="sm" icon="arrow-down-tray">Export SRT</flux:button>
                <span class="font-mono text-[11px] text-zinc-500 ml-auto">{{ count($lines) }} lines · auto-saved</span>
            </footer>
        </section>

        {{-- Right: preview + active line + validation --}}
        <div class="flex flex-col gap-4 min-h-0">

            <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
                <div class="flex justify-between items-center mb-3">
                    <h3 class="text-base font-semibold">Preview</h3>
                    <span class="font-mono text-xs text-zinc-500">{{ $segment?->label() }}</span>
                </div>
                <div class="aspect-video rounded-xl bg-zinc-900 relative overflow-hidden">
                    <div class="absolute inset-0 opacity-50" style="background: repeating-linear-gradient(135deg, #2a2a2c 0 6px, #1a1a18 6px 12px);"></div>
                    <div class="absolute inset-0 grid place-items-center">
                        <div class="size-14 rounded-full bg-white/90 grid place-items-center">
                            <flux:icon.play class="size-6 text-zinc-900" />
                        </div>
                    </div>
                    @if ($selectedLine)
                        <div class="font-my absolute left-[10%] right-[10%] bottom-[12%] bg-black/65 text-white px-3.5 py-2.5 rounded text-base text-center leading-snug">
                            {{ $selectedLine['burmese_text'] }}
                        </div>
                    @endif
                    <div class="absolute left-3.5 top-3.5">
                        <span class="inline-flex items-center rounded-full bg-white/15 text-white px-3 py-0.5 text-xs">
                            မြန်မာ · {{ $segment?->label() }}
                        </span>
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm flex flex-col gap-3">
                <div class="flex justify-between items-center">
                    <h3 class="text-base font-semibold">Validation</h3>
                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[11px] font-medium bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200">
                        all passing
                    </span>
                </div>
                <div class="grid grid-cols-3 gap-2">
                    @foreach (['Timecodes in order', 'No overlaps', 'Encoding OK'] as $check)
                        <div class="flex items-center gap-2 rounded-lg bg-emerald-50 text-emerald-700 px-2.5 py-2 text-xs">
                            <flux:icon.check class="size-3.5" />
                            <span>{{ $check }}</span>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>
    </div>

    <footer class="flex items-center justify-between border-t border-dashed border-zinc-200 pt-4 text-xs text-zinc-500">
        <span>Editing · <span class="font-mono text-zinc-700">{{ $segment?->label() }} / {{ $project->segments->count() }} · {{ count($lines) }} lines</span> · Next saves current edits</span>
        <span>Step 4 of 6 · Edit SRT</span>
    </footer>
</div>
