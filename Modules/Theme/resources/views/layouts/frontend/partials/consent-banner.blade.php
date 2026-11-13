{{--
    Analytics cookie consent (DPDP Act 2023, plan M21 §6). Shown only when analytics is
    configured and the visitor has not chosen yet; the choice stays in localStorage.
--}}
@if (\Modules\Theme\Support\ThemeSettings::analyticsEnabled())
    @php $privacyUrl = \Illuminate\Support\Facades\Route::has('page') ? frontRoute('page', ['privacy-policy']) : null; @endphp
    <div id="consent-banner" role="dialog" aria-modal="false" aria-labelledby="consent-banner-title" hidden
        class="fixed inset-x-3 bottom-20 z-40 mx-auto max-w-xl rounded-card border border-line bg-surface p-4 shadow-pop md:bottom-4">
        <p id="consent-banner-title" class="text-sm font-semibold text-ink">{{ trans('theme::frontend.consent.title') }}</p>
        <p class="mt-1 text-sm text-muted">
            {{ trans('theme::frontend.consent.text') }}
            @if ($privacyUrl)
                <a href="{{ $privacyUrl }}" class="font-medium text-accent underline">{{ trans('theme::frontend.consent.policy') }}</a>
            @endif
        </p>
        <div class="mt-3 flex flex-wrap justify-end gap-2">
            <button type="button" data-consent="denied" class="rounded-lg border border-line-strong px-4 py-2 text-sm font-semibold text-ink hover:bg-surface-alt">{{ trans('theme::frontend.consent.reject') }}</button>
            <button type="button" data-consent="granted" class="rounded-lg bg-accent px-4 py-2 text-sm font-semibold text-accent-ink hover:bg-accent-hover">{{ trans('theme::frontend.consent.accept') }}</button>
        </div>
    </div>
    <script @cspNonce>
        (function () {
            var banner = document.getElementById('consent-banner');
            if (!banner || !window.analyticsConsent || window.analyticsConsent()) { return; }
            banner.hidden = false;
            banner.querySelectorAll('[data-consent]').forEach(function (button) {
                button.addEventListener('click', function () {
                    window.setAnalyticsConsent(button.getAttribute('data-consent') === 'granted');
                    banner.hidden = true;
                });
            });
        })();
    </script>
@endif
