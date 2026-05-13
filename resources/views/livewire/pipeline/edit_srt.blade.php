<div>
    <livewire:step-bar :current="$step" :project="$project" />

    <div class="body-wrap">
        <div class="body-head">
            <div class="title">
                <span class="label-eyebrow">Step 03</span>
                <h1>Review &amp; edit Burmese subtitles</h1>
                <p class="sub">Refine the auto-translated lines. The preview on the right updates live as you select a row.</p>
            </div>
            <div class="actions">
                <button class="btn" wire:click="back">Back</button>
                <button class="btn primary" wire:click="next">Next<svg class="ic"><use href="#i-arrow-r"/></svg></button>
            </div>
        </div>

        <div class="cols two">
            {{-- Left column: SRT editor --}}
            <div class="col">
                <div class="card" style="flex:1; display:flex; flex-direction:column; overflow:hidden;">
                    <div style="padding: 14px 16px; border-bottom: 0.5px solid var(--border); display:flex; justify-content:space-between; align-items:center; gap: 12px;">
                        <div style="display:flex; align-items:center; gap: 10px; padding: 8px 12px; border: 0.5px solid var(--border); border-radius: var(--radius); font-size: 13px; min-width: 260px; background: var(--surface);">
                            <svg class="ic ic-sm" style="color: var(--ink-3);"><use href="#i-folder"/></svg>
                            <select wire:model.live="segmentId"
                                    style="font-weight: 500; border:none; background:transparent; flex: 1; outline:none;">
                                @foreach ($project->segments as $s)
                                    <option value="{{ $s->id }}">{{ $s->label() }} — {{ $s->range() }}</option>
                                @endforeach
                            </select>
                            <svg class="ic ic-sm" style="color: var(--ink-3);"><use href="#i-chev-d"/></svg>
                        </div>
                        <span class="mono muted" style="font-size: 12px;">{{ count($lines) }} lines</span>
                    </div>

                    <div style="flex:1; overflow-y: auto; padding: 12px;">
                        @foreach ($lines as $i => $line)
                            @php $selected = $line['id'] === $selectedLineId; @endphp
                            <div
                                wire:key="line-{{ $line['id'] }}"
                                wire:click="selectLine({{ $line['id'] }})"
                                style="border: {{ $selected ? '1.5px solid var(--blue)' : '0.5px solid var(--border)' }};
                                       background: {{ $selected ? 'var(--blue-bg)' : 'transparent' }};
                                       border-radius: var(--radius); padding: 14px; margin-bottom: 10px;
                                       display:flex; flex-direction:column; gap: 8px; cursor: pointer;"
                            >
                                <div style="display:flex; align-items:center; gap: 10px;">
                                    <span class="mono" style="font-size: 11px; color: {{ $selected ? 'var(--blue)' : 'var(--ink-3)' }}; width: 32px; font-weight: 500;">
                                        #{{ str_pad($line['line_number'], 2, '0', STR_PAD_LEFT) }}
                                    </span>
                                    <span class="mono" style="font-size: 11px; color: {{ $selected ? 'var(--blue)' : 'var(--ink-3)' }};">
                                        {{ $line['start_tc'] }} → {{ $line['end_tc'] }}
                                    </span>
                                </div>
                                <textarea
                                    class="my"
                                    wire:model="lines.{{ $i }}.burmese_text"
                                    wire:change="saveLine({{ $line['id'] }})"
                                    style="border: none; resize: vertical; outline: none; font-family: 'Noto Sans Myanmar', sans-serif; font-size: 15px; line-height: 1.5; background: transparent; padding: 0; min-height: 22px; color: var(--ink); width:100%;"
                                ></textarea>
                                @if (! empty($line['source_text']))
                                    <div style="font-size: 12px; color: var(--ink-3); font-style: italic;">
                                        Source · {{ $line['source_text'] }}
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    <div style="padding: 12px 16px; border-top: 0.5px solid var(--border); display:flex; gap: 8px; align-items:center;">
                        <button class="btn sm" wire:click="exportSrt">
                            <svg class="ic ic-sm"><use href="#i-download"/></svg>Export SRT
                        </button>
                        <span class="mono muted" style="font-size: 11px; margin-left: auto;">
                            {{ count($lines) }} lines · auto-saved
                        </span>
                    </div>
                </div>
            </div>

            {{-- Right column: preview + active line + validation --}}
            <div class="col">
                <div class="card card-pad" style="display:flex; flex-direction:column; gap: 14px;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <h3 class="h-sub">Preview</h3>
                        <span class="mono muted" style="font-size: 12px;">{{ $segment?->label() }}</span>
                    </div>
                    <div style="aspect-ratio: 16/9; background: #1a1a18; border-radius: var(--radius); position: relative; overflow: hidden;">
                        <div style="position:absolute; inset:0; background: repeating-linear-gradient(135deg, #2a2a2c 0 6px, #1a1a18 6px 12px); opacity: 0.5;"></div>
                        <div style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center;">
                            <div style="width: 56px; height: 56px; border-radius: 50%; background: rgba(255,255,255,0.92); display:flex; align-items:center; justify-content:center;">
                                <svg class="ic" style="color: #1a1a18;"><use href="#i-play"/></svg>
                            </div>
                        </div>
                        @if ($selectedLine)
                            <div class="my" style="position:absolute; left: 10%; right: 10%; bottom: 12%; background: rgba(0,0,0,0.65); color: #fff; padding: 10px 14px; border-radius: 3px; font-size: 16px; text-align: center; line-height: 1.4;">
                                {{ $selectedLine['burmese_text'] }}
                            </div>
                        @endif
                        <div style="position:absolute; left: 14px; top: 14px;">
                            <span class="pill" style="background: rgba(255,255,255,0.15); border-color: transparent; color: #fff; height: 24px;">
                                မြန်မာ · {{ $segment?->label() }}
                            </span>
                        </div>
                    </div>
                </div>

                @if ($selectedLine)
                    <div class="card card-pad" style="display:flex; flex-direction:column; gap: 10px;">
                        <div style="display:flex; justify-content:space-between; align-items:baseline;">
                            <h3 class="h-sub">Active line</h3>
                            <span class="mono muted" style="font-size: 11px;">
                                #{{ str_pad($selectedLine['line_number'],2,'0',STR_PAD_LEFT) }} · {{ $selectedLine['start_tc'] }} → {{ $selectedLine['end_tc'] }}
                            </span>
                        </div>
                        <div class="my" style="font-size: 17px; line-height: 1.4;">{{ $selectedLine['burmese_text'] }}</div>
                        @if (! empty($selectedLine['source_text']))
                            <div style="font-size: 13px; color: var(--ink-3); font-style: italic;">{{ $selectedLine['source_text'] }}</div>
                        @endif
                    </div>
                @endif

                <div class="card card-pad" style="display:flex; flex-direction:column; gap: 10px;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <h3 class="h-sub">Validation</h3>
                        <span class="pill green" style="height: 22px; font-size: 11px;">all passing</span>
                    </div>
                    <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap: 6px;">
                        @foreach (['Timecodes in order', 'No overlaps', 'Encoding OK'] as $check)
                            <div style="display:flex; align-items:center; gap: 8px; padding: 8px 10px; background: var(--green-bg); border-radius: var(--radius); font-size: 12px;">
                                <svg class="ic ic-sm" style="color: var(--green);"><use href="#i-check"/></svg>
                                <span>{{ $check }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="footer-row">
            <span>Editing · <span class="mono ink-2">{{ $segment?->label() }} / {{ $project->segments->count() }} · {{ count($lines) }} lines</span> · Next saves current edits</span>
            <span>Step 4 of 6 · Edit SRT</span>
        </div>
    </div>
</div>
