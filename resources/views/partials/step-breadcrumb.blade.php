{{--
    Slim step breadcrumb with a colored status dot per step.

    Required vars (passed via @include):
      - $current : App\Enums\PipelineStep   (the page we're on)
      - $project : ?App\Models\Project      (drives the dot colors)

    Dot colors:
      - done    → emerald
      - running → indigo + pulse
      - active  → indigo (current step, idle)
      - failed  → rose
      - pending → zinc
--}}
@php
    use App\Enums\PipelineStep;
    use App\Enums\StepStatus;

    $steps = PipelineStep::ordered();

    $variant = function (PipelineStep $step) use ($current, $project): string {
        $status = $project ? $project->statusFor($step) : ($step === $current ? StepStatus::Running : StepStatus::Pending);

        return match (true) {
            $status === StepStatus::Failed                            => 'failed',
            $status === StepStatus::Done                              => 'done',
            $step === $current && $status === StepStatus::Running     => 'running',
            $step === $current                                        => 'active',
            default                                                   => 'pending',
        };
    };

    $dotClass = [
        'done'    => 'bg-emerald-500',
        'running' => 'bg-indigo-500 animate-pulse ring-2 ring-indigo-200',
        'active'  => 'bg-indigo-500',
        'failed'  => 'bg-rose-500',
        'pending' => 'bg-zinc-300',
    ];

    $labelClass = [
        'done'    => 'text-emerald-700',
        'running' => 'text-indigo-700 font-semibold',
        'active'  => 'text-zinc-900 font-semibold',
        'failed'  => 'text-rose-700 font-semibold',
        'pending' => 'text-zinc-400',
    ];
@endphp

<nav class="flex flex-wrap items-center gap-x-2 gap-y-1.5 text-xs">
    @foreach ($steps as $step)
        @php $v = $variant($step); @endphp
        <span class="inline-flex items-center gap-1.5">
            <span class="size-1.5 rounded-full transition-colors duration-300 {{ $dotClass[$v] }}"></span>
            <span class="transition-colors duration-300 {{ $labelClass[$v] }}">{{ $step->label() }}</span>
        </span>
        @if (! $loop->last)
            <flux:icon.chevron-right class="size-3 text-zinc-300" />
        @endif
    @endforeach
</nav>
