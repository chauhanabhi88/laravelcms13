{{--
    Variant picker (FR-PDP-002): colour-like axes as swatches, the others (sizes) as buttons,
    each a radio group. Options no available variant can satisfy (given the other choices)
    are struck through but stay clickable to show "Out of stock". Single-variant products
    have no picker.
--}}
@if ($page->hasPicker())
    @php
        $selectedOptions = $page->selectedOptions();
        $sizeAxisId = $page->sizeAxisId();
    @endphp
    <div class="mt-5 space-y-5" x-ref="picker">
        @foreach ($page->axes as $axis)
            @php
                $axisId = $axis['attributeId'];
            @endphp
            <div>
                <div class="mb-2 flex items-center justify-between gap-3">
                    <p id="pdp-axis-{{ $axisId }}" class="text-sm font-semibold text-ink">
                        {{ trans('storefront::product.picker.select', ['name' => $axis['name']]) }}<span class="font-normal text-muted" x-text="selectedLabel({{ $axisId }}) ? ': ' + selectedLabel({{ $axisId }}) : ''"></span>
                    </p>
                    @if ($sizeAxisId === $axisId)
                        <button type="button" x-on:click="$dispatch('open-modal', 'size-chart')"
                            class="inline-flex shrink-0 items-center gap-1 text-sm font-semibold text-accent hover:underline">
                            {{ trans('storefront::product.picker.size_chart') }} <x-theme::icon name="chevron-right" size="size-4" />
                        </button>
                    @endif
                </div>
                <div role="radiogroup" aria-labelledby="pdp-axis-{{ $axisId }}" class="flex flex-wrap gap-2" x-on:keydown="radioKeydown($event)">
                    @foreach ($axis['options'] as $option)
                        @php
                            $isSelected = ($selectedOptions[$axisId] ?? null) === $option['id'];
                        @endphp
                        @if ($axis['type'] === \Modules\Storefront\ViewModels\ProductPageViewModel::AXIS_SWATCH)
                            <button type="button" role="radio" title="{{ $option['label'] }}"
                                aria-checked="{{ $isSelected ? 'true' : 'false' }}" x-bind:aria-checked="isSelected({{ $axisId }}, {{ $option['id'] }}) ? 'true' : 'false'"
                                x-bind:tabindex="tabIndexFor({{ $axisId }}, {{ $option['id'] }})"
                                x-on:click="select({{ $axisId }}, {{ $option['id'] }})"
                                class="relative flex items-center justify-center rounded-card border-2 p-0.5 transition focus-visible:outline-offset-2"
                                x-bind:class="isSelected({{ $axisId }}, {{ $option['id'] }}) ? 'border-accent ring-2 ring-accent/25' : (isAvailable({{ $axisId }}, {{ $option['id'] }}) ? 'border-line hover:border-line-strong' : 'border-line opacity-50')">
                                @if ($option['image'])
                                    <img src="{{ $option['image'] }}" alt="" loading="lazy" class="h-14 w-11 rounded-chip object-cover">
                                @else
                                    <span class="m-1 size-8 rounded-full border border-ink/10 bg-surface-alt" @if ($option['hex']) style="background-color: {{ $option['hex'] }}" @endif aria-hidden="true"></span>
                                @endif
                                <span x-show="! isAvailable({{ $axisId }}, {{ $option['id'] }})" x-cloak class="pointer-events-none absolute inset-x-1 top-1/2 h-0.5 -rotate-45 bg-muted" aria-hidden="true"></span>
                                <span class="sr-only" x-text="isAvailable({{ $axisId }}, {{ $option['id'] }}) ? @js($option['label']) : @js(trans('storefront::product.picker.unavailable_option', ['name' => $option['label']]))">{{ $option['label'] }}</span>
                            </button>
                        @else
                            <button type="button" role="radio"
                                aria-checked="{{ $isSelected ? 'true' : 'false' }}" x-bind:aria-checked="isSelected({{ $axisId }}, {{ $option['id'] }}) ? 'true' : 'false'"
                                x-bind:tabindex="tabIndexFor({{ $axisId }}, {{ $option['id'] }})"
                                x-on:click="select({{ $axisId }}, {{ $option['id'] }})"
                                class="h-10 min-w-12 rounded-lg border px-3 text-sm font-semibold transition focus-visible:outline-offset-2"
                                x-bind:class="isSelected({{ $axisId }}, {{ $option['id'] }}) ? 'border-accent bg-accent-soft text-accent' : (isAvailable({{ $axisId }}, {{ $option['id'] }}) ? 'border-line-strong bg-surface text-ink hover:border-accent' : 'border-line bg-surface text-muted line-through')">
                                {{ $option['label'] }}
                                <span class="sr-only" x-show="! isAvailable({{ $axisId }}, {{ $option['id'] }})">({{ trans('storefront::product.picker.out_of_stock') }})</span>
                            </button>
                        @endif
                    @endforeach
                </div>
            </div>
        @endforeach
        <p x-show="pickerError" x-cloak role="alert" class="text-sm font-medium text-danger" x-text="pickerError"></p>
    </div>
@endif
