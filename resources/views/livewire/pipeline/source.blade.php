@php
    use App\Enums\StepStatus;
    $status = $project?->source_status ?? StepStatus::Pending;

    $readOnly = $project && $project->current_step->order() > $step->order();

    $statusBadge = match (true) {
        $readOnly => ['zinc', 'lock-closed', 'View only · locked'],
        $status === StepStatus::Done => ['emerald', 'check-circle', 'Ready'],
        $status === StepStatus::Running => ['indigo', 'arrow-path', 'Fetching'],
        $status === StepStatus::Failed => ['rose', 'exclamation-triangle', 'Failed'],
        default => ['zinc', 'clock', 'Awaiting URL'],
    };
    [$badgeColor, $badgeIcon, $badgeText] = $statusBadge;
@endphp

<div class="flex-1 flex flex-col px-10 lg:px-20 py-8 gap-6">
    @include('partials.step-breadcrumb', ['current' => $step, 'project' => $project])

    {{-- Page header --}}
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="space-y-2">
            <span class="text-[11px] font-semibold uppercase tracking-widest text-zinc-500">Step 01</span>
            <p class="text-sm text-zinc-500 max-w-2xl">
                Paste a movie recap URL. We fetch metadata and confirm the source is ready before moving on.
            </p>
        </div>

        <div class="flex items-center gap-2">
            @if (!$readOnly)
                <flux:button wire:click="$set('sourceUrl','')" variant="ghost">Cancel</flux:button>
            @endif
            <flux:button wire:click="continueToSplit"
                :variant="$status === StepStatus::Done ? 'primary' : 'subtle'"
                icon-trailing="arrow-right"
                :disabled="$status !== StepStatus::Done">Continue</flux:button>
        </div>
    </div>

    {{-- Centered card --}}
    <div class="flex-1 flex justify-center items-start py-2">
        <div class="w-full max-w-3xl rounded-2xl border border-zinc-200 bg-white shadow-sm p-10 flex flex-col gap-6">
            <div class="text-center space-y-1">
                <h3 class="text-xl font-semibold tracking-tight">Paste a video URL</h3>
                <p class="text-sm text-zinc-500">YouTube, TikTok, Facebook, and Xiaohongshu links are supported.</p>
            </div>

            <form wire:submit="fetch" class="flex flex-col gap-3">
                <div class="relative">
                    <flux:icon.link class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-zinc-400" />
                    <input type="url" wire:model="sourceUrl" placeholder="https://www.youtube.com/watch?v=…"
                        @disabled($status === StepStatus::Running || $readOnly)
                        class="w-full h-12 pl-10 pr-4 rounded-xl border border-zinc-300 bg-white font-mono text-sm placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 disabled:bg-zinc-50 disabled:text-zinc-500 disabled:cursor-not-allowed" />
                </div>
                @error('sourceUrl')
                    <span class="text-xs text-rose-600">{{ $message }}</span>
                @enderror

                @if (!$readOnly)
                    <flux:button type="submit" variant="primary" color="sky" icon="arrow-down-tray"
                        :disabled="$status === StepStatus::Running" class="self-start">
                        {{ $status === StepStatus::Running ? 'Fetching…' : 'Fetch' }}
                    </flux:button>
                @endif
            </form>

            <div class="flex flex-wrap justify-center gap-2">
                @foreach (['YouTube', 'TikTok', 'Facebook', 'Xiaohongshu'] as $src)
                    <span
                        class="inline-flex items-center rounded-full bg-zinc-100 text-zinc-600 px-3 py-1 text-xs font-medium ring-1 ring-zinc-200">
                        {{ $src }}
                    </span>
                @endforeach
            </div>

            {{-- Live progress (polls while running) --}}
            @if ($project)
                @php
                    $jobLabel = match ($status) {
                        StepStatus::Pending => 'Queued — waiting for worker',
                        StepStatus::Running => 'Fetching from source…',
                        StepStatus::Done => 'Done',
                        StepStatus::Failed => 'Failed',
                    };
                    $barWidth = match ($status) {
                        StepStatus::Pending => 15,
                        StepStatus::Running => 65,
                        StepStatus::Done => 100,
                        StepStatus::Failed => 100,
                    };
                    $barCls = match ($status) {
                        StepStatus::Done => 'from-emerald-500 to-emerald-400',
                        StepStatus::Failed => 'from-rose-500 to-rose-400',
                        default => 'from-indigo-500 to-violet-500',
                    };
                    $isRunning = $status === StepStatus::Running;
                    $isDone = $status === StepStatus::Done;
                @endphp
                <div wire:poll.2s="pollStatus"
                    class="rounded-xl border border-zinc-200 bg-zinc-50/60 p-4 flex flex-col gap-3">
                    <div class="flex justify-between text-xs">
                        <span class="font-medium text-zinc-700">{{ $jobLabel }}</span>
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-medium bg-{{ $badgeColor }}-50 text-{{ $badgeColor }}-700 ring-1 ring-{{ $badgeColor }}-200">
                            {{ $status->label() }}
                        </span>
                    </div>
                    <div class="h-2 w-full overflow-hidden rounded-full bg-zinc-200">
                        <div class="h-full rounded-full bg-linear-to-r {{ $barCls }} transition-all duration-500"
                            style="width: {{ $barWidth }}%"></div>
                    </div>
                    <div class="grid grid-cols-4 gap-2 text-[11px] text-zinc-500">
                        <div class="flex items-center gap-1.5 {{ $isRunning || $isDone ? 'text-emerald-600' : '' }}">
                            <flux:icon.check class="size-3" /> Queued
                        </div>
                        <div class="flex items-center gap-1.5 {{ $isRunning || $isDone ? 'text-emerald-600' : '' }}">
                            <flux:icon.check class="size-3" /> Connecting
                        </div>
                        <div class="flex items-center gap-1.5 {{ $isDone ? 'text-emerald-600' : '' }}">
                            <flux:icon.check class="size-3" /> Fetching metadata
                        </div>
                        <div class="flex items-center gap-1.5 font-medium {{ $isDone ? 'text-emerald-600' : '' }}">
                            <flux:icon.check class="size-3" /> Ready
                        </div>
                    </div>
                </div>
            @endif

            @if ($project && $project->source_title)
                <div class="flex items-center gap-3 rounded-xl border border-zinc-200 bg-zinc-50/60 p-4">
                    <div class="grid place-items-center size-10 rounded-lg bg-white text-zinc-500 ring-1 ring-zinc-200">
                        <flux:icon.video-camera class="size-5" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-semibold truncate">{{ $project->source_title }}</div>
                        <div class="text-xs text-zinc-500 font-mono mt-0.5">
                            Total duration ·
                            {{ sprintf('%02d:%02d', intdiv((int) $project->source_duration_sec, 60), $project->source_duration_sec % 60) }}
                        </div>
                    </div>
                    <span
                        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200">
                        <flux:icon.check class="size-3" /> Ready
                    </span>
                </div>
            @endif

            @if ($project?->last_error)
                <div class="rounded-xl bg-rose-50 text-rose-700 ring-1 ring-rose-200 p-3 text-xs">
                    {{ $project->last_error }}
                </div>
            @endif
        </div>
    </div>

    <footer class="flex items-center justify-between border-t border-dashed border-zinc-200 pt-4 text-xs text-zinc-500">
        <span><span class="font-mono">⌘V</span> Paste · <span class="font-mono">⌘↵</span> Fetch · <span
                class="font-mono">→</span> Continue</span>
        <span>Step 1 of 6 · Source</span>
    </footer>

    @script
        <script>
            Livewire.on('source-completed', () => {
                window.location.reload();
            });
        </script>
    @endscript
</div>
