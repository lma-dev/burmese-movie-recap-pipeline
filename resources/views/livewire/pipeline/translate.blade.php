@php
    use App\Enums\StepStatus;
    $segments = $project->segments;

    $statusBadge = match ($project->translate_status) {
        StepStatus::Done    => ['emerald', 'check-circle', 'Done'],
        StepStatus::Running => ['indigo',  'arrow-path',   'Translating'],
        StepStatus::Failed  => ['rose',    'exclamation-triangle', 'Failed'],
        default             => ['zinc',    'clock',        'Queued'],
    };
    [$badgeColor, $badgeIcon, $badgeText] = $statusBadge;
@endphp

<div wire:poll.3s class="flex-1 flex flex-col px-10 lg:px-20 py-8 gap-6">
    @include('partials.step-breadcrumb', ['current' => $step, 'project' => $project])

    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="space-y-2">
            <span class="text-[11px] font-semibold uppercase tracking-widest text-zinc-500">Step 03</span>
            <p class="text-sm text-zinc-500 max-w-2xl">
                Runs automatically. Each segment is transcribed in its source language, then translated to Burmese.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <flux:button wire:click="back" variant="ghost">Back</flux:button>
            <flux:button
                wire:click="continueToEditSrt"
                :variant="$project->translate_status === StepStatus::Done ? 'primary' : 'subtle'"
                icon-trailing="arrow-right"
                :disabled="$project->translate_status !== StepStatus::Done"
            >Continue</flux:button>
        </div>
    </div>

    <div class="flex-1 flex justify-center items-start">
        <section class="w-full max-w-5xl rounded-2xl border border-zinc-200 bg-white shadow-sm overflow-hidden">
            <header class="flex items-center justify-between gap-3 px-6 py-4 border-b border-zinc-100">
                <div>
                    <h2 class="text-base font-semibold">Overall progress</h2>
                    <p class="text-xs text-zinc-500 mt-0.5">{{ $doneCount }} of {{ $segments->count() }} segments done</p>
                </div>
                <span class="text-xs font-mono text-zinc-500">{{ $progress }}%</span>
            </header>

            <div class="px-6 pt-4">
                <div class="h-2 w-full overflow-hidden rounded-full bg-zinc-100">
                    <div class="h-full rounded-full bg-linear-to-r from-indigo-500 to-violet-500 transition-all duration-500" style="width: {{ $progress }}%"></div>
                </div>
            </div>

            <div class="grid grid-cols-[100px_1fr_1fr] px-6 py-3 mt-3 bg-zinc-50/60 text-[10px] font-semibold uppercase tracking-widest text-zinc-500">
                <div>Part</div>
                <div>Transcription</div>
                <div>Translation → my</div>
            </div>

            <ul class="divide-y divide-zinc-100">
                @foreach ($segments as $seg)
                    <li class="grid grid-cols-[100px_1fr_1fr] items-center px-6 py-4 gap-3 text-sm">
                        <div class="font-mono font-medium {{ $seg->transcribe_status === StepStatus::Pending ? 'text-zinc-400' : 'text-zinc-900' }}">
                            {{ $seg->label() }}
                        </div>

                        @foreach ([$seg->transcribe_status, $seg->translate_status] as $i => $st)
                            @php
                                $runningLabel = $i === 0 ? 'Transcribing' : 'Translating';
                            @endphp
                            <div>
                                @switch($st)
                                    @case(StepStatus::Done)
                                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[11px] font-medium bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200">
                                            <flux:icon.check class="size-3" /> Done
                                        </span>
                                        @break
                                    @case(StepStatus::Running)
                                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[11px] font-medium bg-indigo-50 text-indigo-700 ring-1 ring-indigo-200">
                                            <flux:icon.arrow-path class="size-3 animate-spin" /> {{ $runningLabel }}
                                        </span>
                                        @break
                                    @case(StepStatus::Failed)
                                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[11px] font-medium bg-rose-50 text-rose-700 ring-1 ring-rose-200">
                                            <flux:icon.exclamation-triangle class="size-3" /> Failed
                                        </span>
                                        @break
                                    @default
                                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[11px] font-medium bg-zinc-100 text-zinc-600 ring-1 ring-zinc-200">
                                            <flux:icon.clock class="size-3" /> Pending
                                        </span>
                                @endswitch
                            </div>
                        @endforeach
                    </li>
                @endforeach
            </ul>
        </section>
    </div>

    <footer class="flex items-center justify-between border-t border-dashed border-zinc-200 pt-4 text-xs text-zinc-500">
        <span>Running automatically · no user input required</span>
        <span>Step 3 of 6 · Translate</span>
    </footer>
</div>
