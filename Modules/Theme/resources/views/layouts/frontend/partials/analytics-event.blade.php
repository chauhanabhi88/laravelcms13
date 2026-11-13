{{--
    One GA4 ecommerce event (plan M21 E7), printed only when analytics is configured.
    $event: view_item_list | view_item | add_to_cart | begin_checkout | purchase; $params: the
    event payload; $on (optional): a window event to wait for (e.g. "mini-cart:show" after Add
    to Cart); $once (optional): send at most once per browser session under this key.
--}}
@if (\Modules\Theme\Support\ThemeSettings::analyticsEnabled())
    <script @cspNonce>
        (function () {
            var send = function () {
                @if (! empty($once))
                    try {
                        var key = 'analytics_sent_' + @js($once);
                        if (window.sessionStorage.getItem(key)) { return; }
                        window.sessionStorage.setItem(key, '1');
                    } catch (e) {}
                @endif
                window.trackEvent && window.trackEvent(@js($event), @js($params));
            };
            @if (! empty($on))
                window.addEventListener(@js($on), send);
            @else
                send();
            @endif
        })();
    </script>
@endif
