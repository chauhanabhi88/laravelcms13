{{--
    Grievance officer contact (Consumer Protection (E-Commerce) Rules 2020, plan M21 §6), from
    admin > Settings > Theme. $officer = ThemeSettings::grievanceOfficer(); $compact for the footer.
--}}
@if ($officer ?? null)
    @if ($compact ?? false)
        <p class="text-xs text-muted">
            {{ trans('theme::frontend.grievance.footer', ['name' => $officer['name']]) }}
            @if ($officer['email'])
                · <a href="mailto:{{ $officer['email'] }}" class="font-medium hover:text-ink">{{ $officer['email'] }}</a>
            @endif
            @if ($officer['phone'])
                · <a href="tel:{{ preg_replace('/[^0-9+]/', '', $officer['phone']) }}" class="font-medium hover:text-ink">{{ $officer['phone'] }}</a>
            @endif
        </p>
    @else
        <section class="mt-10 rounded-card border border-line bg-surface p-6" aria-labelledby="grievance-officer-heading">
            <h2 id="grievance-officer-heading" class="text-lg font-semibold text-ink">{{ trans('theme::frontend.grievance.heading') }}</h2>
            <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-[10rem_1fr]">
                <dt class="text-muted">{{ trans('theme::frontend.grievance.name') }}</dt>
                <dd class="font-medium text-ink">{{ $officer['name'] }}@if ($officer['designation']), {{ $officer['designation'] }}@endif</dd>
                @if ($officer['email'])
                    <dt class="text-muted">{{ trans('theme::frontend.grievance.email') }}</dt>
                    <dd><a href="mailto:{{ $officer['email'] }}" class="font-medium text-accent hover:underline">{{ $officer['email'] }}</a></dd>
                @endif
                @if ($officer['phone'])
                    <dt class="text-muted">{{ trans('theme::frontend.grievance.phone') }}</dt>
                    <dd><a href="tel:{{ preg_replace('/[^0-9+]/', '', $officer['phone']) }}" class="font-medium text-accent hover:underline">{{ $officer['phone'] }}</a></dd>
                @endif
                @if ($officer['address'])
                    <dt class="text-muted">{{ trans('theme::frontend.grievance.address') }}</dt>
                    <dd class="text-ink">{{ $officer['address'] }}</dd>
                @endif
            </dl>
            <p class="mt-4 text-sm text-muted">{{ trans('theme::frontend.grievance.timelines') }}</p>
        </section>
    @endif
@endif
