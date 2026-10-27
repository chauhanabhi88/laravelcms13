@extends('product::seller.product.wizard.layout')

@section('step')
    <div x-data="imageUploader(@js($uploader))" class="space-y-5">
        <p class="text-sm text-ink-soft">{{ trans('product::seller.wizard.images.intro') }}</p>
        <p class="text-xs text-muted">{{ trans('product::seller.wizard.images.rules', ['px' => $uploader['minPx'], 'mb' => round($uploader['maxKb'] / 1024, 1), 'max' => $uploader['maxImages']]) }}</p>

        <x-theme::alert type="error" x-show="error" x-cloak><span x-text="error"></span></x-theme::alert>

        <template x-for="group in groups" :key="group.id ?? 'shared'">
            <section class="rounded-card border border-line bg-surface p-5 shadow-card">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h2 class="text-base font-semibold text-ink"
                        x-text="group.id === null ? group.name : @js(trans('product::seller.wizard.images.colour_set', ['colour' => '__C__'])).replace('__C__', group.name)"></h2>
                    <span class="text-xs text-muted" x-text="inGroup(group.id).length + ' / ' + limit(group.id)"></span>
                </div>
                @if ($colourAxisName)
                    <p class="mt-1 text-xs text-muted" x-show="group.id !== null">{{ trans('product::seller.wizard.images.colour_hint', ['axis' => \Illuminate\Support\Str::lower($colourAxisName)]) }}</p>
                @endif

                <ul class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6">
                    <template x-for="(image, index) in inGroup(group.id)" :key="image.path">
                        <li class="group relative overflow-hidden rounded-lg border border-line bg-surface-alt" draggable="true"
                            x-on:dragstart="dragging = image.path" x-on:dragover.prevent x-on:drop.prevent="reorder(group.id, dragging, image.path); dragging = null">
                            <img x-bind:src="image.url" alt="" class="aspect-3/4 w-full object-cover" loading="lazy">
                            <span x-show="group.id === null && index === 0" class="absolute top-2 left-2 rounded-chip bg-accent px-2 py-0.5 text-xs font-medium text-accent-ink">{{ trans('product::seller.wizard.images.main') }}</span>
                            <div class="absolute inset-x-0 bottom-0 flex justify-between bg-ink/60 p-1.5 text-white opacity-100 transition sm:opacity-0 sm:group-hover:opacity-100 sm:group-focus-within:opacity-100">
                                <span class="flex gap-1">
                                    <button type="button" class="rounded p-1 hover:bg-white/20" x-on:click="move(group.id, image.path, -1)" x-bind:disabled="busy || index === 0" aria-label="{{ trans('product::seller.wizard.images.move_left') }}"><x-theme::icon name="chevron-left" size="size-4" /></button>
                                    <button type="button" class="rounded p-1 hover:bg-white/20" x-on:click="move(group.id, image.path, 1)" x-bind:disabled="busy || index === inGroup(group.id).length - 1" aria-label="{{ trans('product::seller.wizard.images.move_right') }}"><x-theme::icon name="chevron-right" size="size-4" /></button>
                                </span>
                                <button type="button" class="rounded p-1 hover:bg-white/20" x-on:click="remove(image.path)" x-bind:disabled="busy" aria-label="{{ trans('product::seller.wizard.images.delete') }}"><x-theme::icon name="trash" size="size-4" /></button>
                            </div>
                        </li>
                    </template>

                    <li x-show="canAdd(group.id)">
                        <label class="flex aspect-3/4 cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed border-line-strong px-2 text-center text-xs text-muted transition hover:border-accent hover:text-accent"
                            x-on:dragover.prevent x-on:drop.prevent="if ($event.dataTransfer.files.length) upload(group.id, $event.dataTransfer.files)">
                            <x-theme::icon name="upload" />
                            <span x-text="busy ? @js(trans('product::seller.wizard.images.uploading')) : @js(trans('product::seller.wizard.images.drop'))"></span>
                            <input type="file" class="sr-only" multiple accept="{{ collect($uploader['types'])->map(fn ($type) => '.'.$type)->implode(',') }}"
                                x-bind:disabled="busy" x-on:change="upload(group.id, $event.target.files); $event.target.value = ''">
                        </label>
                    </li>
                </ul>
            </section>
        </template>

        <div class="flex justify-between gap-3">
            <x-theme::button variant="ghost" :href="frontRoute('seller.listing.step', [$product->id, 'variants'])"><x-theme::icon name="arrow-left" size="size-4" /> {{ trans('product::seller.common.back') }}</x-theme::button>
            <x-theme::button :href="frontRoute('seller.listing.step', [$product->id, 'pricing'])">{{ trans('product::seller.common.continue') }} <x-theme::icon name="arrow-right" size="size-4" /></x-theme::button>
        </div>
    </div>
@endsection
