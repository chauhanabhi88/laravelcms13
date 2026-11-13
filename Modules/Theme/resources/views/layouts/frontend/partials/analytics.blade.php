{{--
    Google Tag Manager / GA4 (plan M21 E7), configured in admin > Settings > Theme. Nothing is
    loaded until the visitor accepts analytics cookies (consent-banner partial); consent mode
    starts as "denied". window.trackEvent(name, params) queues GA4 ecommerce events either way.
--}}
@php
    $gtmId = \Modules\Theme\Support\ThemeSettings::gtmContainerId();
    $ga4Id = \Modules\Theme\Support\ThemeSettings::ga4MeasurementId();
@endphp
@if ($gtmId || $ga4Id)
    <script @cspNonce>
        (function () {
            var gtmId = @js($gtmId), ga4Id = @js($ga4Id), loaded = false;
            window.dataLayer = window.dataLayer || [];
            window.gtag = function () { window.dataLayer.push(arguments); };

            window.analyticsConsent = function () {
                try { return window.localStorage.getItem('analytics_consent'); } catch (e) { return null; }
            };

            window.gtag('consent', 'default', {
                ad_storage: 'denied',
                ad_user_data: 'denied',
                ad_personalization: 'denied',
                analytics_storage: window.analyticsConsent() === 'granted' ? 'granted' : 'denied'
            });

            window.loadAnalytics = function () {
                if (loaded) { return; }
                loaded = true;
                var script = document.createElement('script');
                script.async = true;
                if (gtmId) {
                    window.dataLayer.push({ 'gtm.start': new Date().getTime(), event: 'gtm.js' });
                    script.src = 'https://www.googletagmanager.com/gtm.js?id=' + encodeURIComponent(gtmId);
                } else {
                    window.gtag('js', new Date());
                    window.gtag('config', ga4Id);
                    script.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(ga4Id);
                }
                document.head.appendChild(script);
            };

            window.setAnalyticsConsent = function (granted) {
                try { window.localStorage.setItem('analytics_consent', granted ? 'granted' : 'denied'); } catch (e) {}
                window.gtag('consent', 'update', { analytics_storage: granted ? 'granted' : 'denied' });
                if (granted) { window.loadAnalytics(); }
            };

            window.trackEvent = function (name, params) {
                if (gtmId) {
                    window.dataLayer.push({ ecommerce: null });
                    window.dataLayer.push({ event: name, ecommerce: params });
                } else {
                    window.gtag('event', name, params);
                }
            };

            if (window.analyticsConsent() === 'granted') { window.loadAnalytics(); }
        })();
    </script>
@endif
