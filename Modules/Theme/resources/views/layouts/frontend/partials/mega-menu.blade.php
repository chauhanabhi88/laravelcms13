{{--
    Category bar (md and up). Mouse: hover opens the flyout (CSS group-hover). Keyboard: Left/Right
    move between categories, Enter/Space/Down open, Esc closes (resources/js/components/mega-menu.js).
    Until the Catalog provides a tree, the configured nav links are shown instead.
--}}
<nav aria-label="{{ trans('theme::frontend.nav.categories') }}" x-data="megaMenu()" x-on:keydown.escape="close(true)" x-on:click.outside="close()"
    class="container-page">
    <ul class="scrollbar-none -mx-3 flex items-center overflow-x-auto">
        @forelse ($megaMenu as $index => $category)
            <li class="group/l1" x-on:focusout="$el.contains($event.relatedTarget) || (isOpen({{ $index }}) && close())">
                @if (! empty($category['children']))
                    <button type="button" data-mega-trigger aria-expanded="false" aria-controls="mega-panel-{{ $index }}"
                        x-bind:aria-expanded="isOpen({{ $index }}) ? 'true' : 'false'" x-on:click="toggle({{ $index }})"
                        x-on:keydown="onTriggerKeydown($event, {{ $index }})"
                        class="flex h-11 items-center border-b-2 border-transparent px-3 text-sm font-medium whitespace-nowrap text-ink-soft transition group-hover/l1:border-accent group-hover/l1:text-accent"
                        x-bind:class="isOpen({{ $index }}) && 'border-accent! text-accent!'">
                        {{ $category['name'] }}
                    </button>
                    <div id="mega-panel-{{ $index }}"
                        class="invisible absolute inset-x-0 top-full z-30 border-t border-line bg-surface opacity-0 shadow-pop transition duration-150 group-hover/l1:visible group-hover/l1:opacity-100"
                        x-bind:class="isOpen({{ $index }}) && 'visible! opacity-100!'">
                        <div class="container-page grid max-h-[70dvh] grid-cols-3 gap-x-8 gap-y-6 overflow-y-auto py-6 lg:grid-cols-5">
                            @foreach ($category['children'] as $child)
                                <div>
                                    <a href="{{ $child['url'] }}" class="text-sm font-semibold text-ink hover:text-accent">{{ $child['name'] }}</a>
                                    @if (! empty($child['children']))
                                        <ul class="mt-2 space-y-1.5">
                                            @foreach ($child['children'] as $leaf)
                                                <li><a href="{{ $leaf['url'] }}" class="text-sm text-muted hover:text-accent">{{ $leaf['name'] }}</a></li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                            @endforeach
                            <div class="col-span-full border-t border-line pt-4">
                                <a href="{{ $category['url'] }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-accent hover:text-accent-hover">
                                    {{ trans('theme::frontend.nav.view_all', ['name' => $category['name']]) }}
                                    <x-theme::icon name="arrow-right" size="size-4" />
                                </a>
                            </div>
                        </div>
                    </div>
                @else
                    <a href="{{ $category['url'] }}" data-mega-trigger x-on:keydown="onTriggerKeydown($event, {{ $index }})"
                        class="flex h-11 items-center border-b-2 border-transparent px-3 text-sm font-medium whitespace-nowrap text-ink-soft transition hover:border-accent hover:text-accent">
                        {{ $category['name'] }}
                    </a>
                @endif
            </li>
        @empty
            @foreach ($navItems as $index => $item)
                <li>
                    <a href="{{ $item['url'] }}" data-mega-trigger x-on:keydown="onTriggerKeydown($event, {{ $index }})"
                        @if ($item['active']) aria-current="page" @endif
                        @class([
                            'flex h-11 items-center border-b-2 px-3 text-sm font-medium whitespace-nowrap transition',
                            'border-accent text-accent' => $item['active'],
                            'border-transparent text-ink-soft hover:border-accent hover:text-accent' => ! $item['active'],
                        ])>{{ $item['label'] }}</a>
                </li>
            @endforeach
        @endforelse
    </ul>
</nav>
