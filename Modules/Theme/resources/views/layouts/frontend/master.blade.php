<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @yield('meta')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>
        @yield('title')
    </title>

    <link rel="stylesheet" href="{{ asset('modules/theme/frontend/css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('modules/theme/backend/staradmin/css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('modules/theme/backend/fontawesome/css/all.min.css') }}">

    @foreach($cssFiles as $css)
    <link media="all" type="text/css" rel="stylesheet" href="{{ asset($css) }}">
    @endforeach
    @livewireStyles
    <link rel="icon" type="image/x-icon" href="favicon.ico">
</head>

<body class="{{ (!empty($bodyClassName)) ? $bodyClassName : "" }}">
    <header>
        @include('theme::layouts.frontend.partials.header')
    </header>
    @include('theme::layouts.frontend.partials.notifications')
    @yield('content')
    @include('theme::layouts.frontend.partials.footer')

    <script type="text/javascript" src="{{ asset('modules/theme/js/jquery.js') }}"></script>
    <script type="text/javascript" src="{{ asset('modules/theme/js/jquery-ui.js') }}"></script>
    <script type="text/javascript" src="{{ asset('modules/theme/frontend/js/bootstrap.js') }}"></script>
    <script type="text/javascript" src="{{ asset('modules/theme/js/jquery.validate.js') }}"></script>

    <script>
        var csrfToken = '{{ csrf_token() }}';
        var isOnlineOfflineGridShow = <?php echo (int)config('customer.show_customer_online_offline_grid'); ?>;
        var log_url = "{{route('customer.customer_online_offline_log',updateUrlParams(['type' => config('core.route_type')]))}}";
    </script>

    <script type="text/javascript" src="{{ asset('modules/core/js/frontend/custom.js') }}"></script>
    @foreach($jsFiles as $js)
    <script src="{{ asset($js) }}" type="text/javascript"></script>
    @endforeach
    @livewireScripts
    @section('scripts')
    @show
    @stack('js-stack')
    <script>
        jQuery(document).ready(function() {
            jQuery.validator.addMethod("newPassword", function(value, element) {
                var min_password_length = "{{settings('customer', 'min_password_length')}}";
                var max_password_length = "{{!empty(settings('customer', 'max_password_length')) ? settings('customer', 'max_password_length') : 20 }}";
                var passwordRegex = new RegExp("^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[!\"@./`[\\]{|}\\\\~:;_<=>?'\(\)#+\,\$%\^&\*\-]).{" + min_password_length + "," + max_password_length + "}$", "m");
                if (value.length) {
                    return passwordRegex.test(value);
                }
                return true;
            }, "{{trans('customer::customer.messages.invalid_password',['password_length'=>settings('customer', 'min_password_length'), 'max_password_length' => settings('customer', 'max_password_length')])}}");
        });
    </script>
</body>

</html>