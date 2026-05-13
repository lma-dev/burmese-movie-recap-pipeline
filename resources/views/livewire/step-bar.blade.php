{{--
    Persistent step bar.

    Props:
      - $current : App\Enums\PipelineStep   (the page we're on)
      - $project : ?App\Models\Project      (drives done/active state)
      - $steps   : array of PipelineStep    (the 6 ordered steps)
--}}
<div class="stepbar">
    <div class="brand">
        <div class="name">Recap Pipeline</div>
        <div class="ver">Burmese · v2.0</div>
    </div>

    <div class="steps">
        @foreach ($steps as $step)
            @php $cls = $this->classFor($step); @endphp
            <div class="step {{ $cls }}">
                <div class="num">
                    @if ($cls === 'done')
                        <svg class="ic ic-sm"><use href="#i-check"/></svg>
                    @else
                        {{ $step->order() }}
                    @endif
                </div>
                <div>
                    <div class="label">{{ $step->label() }}</div>
                    <div class="state">{{ $this->stateLabel($step) }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="meta">
        <div class="avatar">AM</div>
        <div class="who">
            <div class="name">Aung Min</div>
            <div class="role">Editor</div>
        </div>
        <svg class="ic ic-sm chev"><use href="#i-chev-d"/></svg>
    </div>
</div>
