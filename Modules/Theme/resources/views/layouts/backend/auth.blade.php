<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <title>
        @yield('title')
    </title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="stylesheet" href="{{ asset('modules/theme/backend/staradmin/css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('modules/theme/backend/css/custom_theme.css') }}">
    <link rel="stylesheet" href="{{ asset('modules/theme/backend/fontawesome/css/all.min.css') }}">
    <link rel="stylesheet" href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css">
    <link rel="stylesheet" href="{{ asset('modules/theme/backend/icheck-bootstrap/icheck-bootstrap.min.css') }}">
    <link href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700" rel="stylesheet">
</head>

<body class="login-page">
    <div id="app" class="container-scroller">
        @yield('content')
    </div>

    <script type="text/javascript" src="{{ asset('modules/theme/js/jquery.js') }}"></script>
    <script type="text/javascript" src="{{ asset('modules/theme/backend/bootstrap/bootstrap.bundle.min.js') }}"></script>
    <script type="text/javascript" src="{{ asset('modules/theme/js/jquery.validate.js') }}"></script>
    @stack('js-stack')

    <script type="text/javascript">
        jQuery(document).on('click', '.input-group-append', function() {
            var classes = jQuery(this).find('span').attr('class');
            classes = classes.split(' ');
            if (classes.includes('fa-eye-slash') || classes.includes('fa-eye')) {
                jQuery(this).find('span').toggleClass("fa-eye fa-eye-slash");
                var input = $(jQuery(this).parent().children());
                if (input.attr("type") === "password") {
                    input.attr("type", "text");
                } else {
                    input.attr("type", "password");
                }
            }
        });
    </script>

</body>

</html>