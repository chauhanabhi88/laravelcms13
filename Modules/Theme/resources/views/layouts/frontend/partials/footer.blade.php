<footer class="mt-24 border-t border-line bg-surface">
    <div class="container-page grid gap-10 py-12 sm:grid-cols-2 lg:grid-cols-6">
        <div class="sm:col-span-2">
            <a href="{{ frontRoute('homepage') }}" class="inline-flex">
                <img src="{{ $logoUrl }}" alt="{{ $siteName }}" class="h-8 w-auto" loading="lazy">
            </a>
            @if ($footerAbout)
                <div class="prose-content mt-4 max-w-md text-sm! leading-6!">{!! $footerAbout !!}</div>
            @endif

            @if ($supportPhone || $supportEmail)
                <div class="mt-5 space-y-2 text-sm">
                    <p class="font-semibold text-ink">{{ trans('theme::frontend.footer.contact_us') }}</p>
                    @if ($supportPhone)
                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', $supportPhone) }}" class="flex items-center gap-2 text-muted hover:text-ink">
                            <x-theme::icon name="phone" size="size-4" /> {{ $supportPhone }}
                        </a>
                    @endif
                    @if ($supportEmail)
                        <a href="mailto:{{ $supportEmail }}" class="flex items-center gap-2 text-muted hover:text-ink">
                            <x-theme::icon name="mail" size="size-4" /> {{ $supportEmail }}
                        </a>
                    @endif
                </div>
            @endif

            @if ($appLinks)
                <div class="mt-5">
                    <p class="text-sm font-semibold text-ink">{{ trans('theme::frontend.footer.get_app') }}</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($appLinks as $store => $url)
                            <a href="{{ $url }}" target="_blank" rel="noopener"
                                class="inline-flex items-center gap-2 rounded-lg border border-line-strong px-3 py-2 text-xs font-semibold text-ink hover:bg-surface-alt">
                                <x-theme::icon name="smartphone" size="size-4" /> {{ trans('theme::frontend.footer.'.$store) }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        @foreach (array_merge(['shop' => $footerShop], $footerColumns) as $key => $column)
            @if ($column['links'])
                <nav aria-labelledby="footer-{{ $key }}">
                    <h2 id="footer-{{ $key }}" class="text-sm font-semibold text-ink">{{ $column['label'] }}</h2>
                    <ul class="mt-4 space-y-2.5 text-sm">
                        @foreach ($column['links'] as $link)
                            <li><a href="{{ $link['url'] }}" class="text-muted transition hover:text-ink">{{ $link['label'] }}</a></li>
                        @endforeach
                    </ul>
                </nav>
            @endif
        @endforeach
    </div>

    <div class="border-t border-line">
        <div class="container-page flex flex-col gap-4 py-6 text-xs text-muted md:flex-row md:items-center md:justify-between">
            <p>&copy; {{ now()->year }} {{ $siteName }}. {{ trans('theme::frontend.footer.rights') }}</p>

            @if ($socialLinks)
                <ul class="flex flex-wrap gap-4" aria-label="{{ trans('theme::frontend.footer.follow_us') }}">
                    @foreach ($socialLinks as $network => $url)
                        <li><a href="{{ $url }}" target="_blank" rel="noopener" class="font-medium hover:text-ink">{{ trans('theme::frontend.footer.social.'.$network) }}</a></li>
                    @endforeach
                </ul>
            @endif

            <div class="flex flex-wrap items-center gap-2">
                <span>{{ trans('theme::frontend.footer.we_accept') }}</span>
                @foreach (['upi' => 'smartphone', 'cards' => 'credit-card', 'netbanking' => 'store', 'cod' => 'banknote'] as $method => $icon)
                    <x-theme::chip :icon="$icon">{{ trans('theme::frontend.footer.payment_methods.'.$method) }}</x-theme::chip>
                @endforeach
            </div>
        </div>
    </div>
</footer>
