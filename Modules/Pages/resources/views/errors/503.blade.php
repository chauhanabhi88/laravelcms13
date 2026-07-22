
<style>
body {
	margin: 0px;
}
	
</style>

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1.0, user-scalable=no"/>
        <title>
        {!! "503 page" !!}
        </title>
        <link rel="stylesheet" href="{{ asset('modules/theme/backend/css/custom_theme.css') }}">
    </head>
    <body>
        <div class="page-main maintainance-main">
            <div class="maintenance-content maintainance-main">
                <img src="{{ asset('modules/theme/backend/images/logo.png') }}" alt="" class="image"/>
                <div class="text">
                    <h3>Coming soon..</h3>
                    <p>{{settings('core', 'maintenance_mode_message')}}</p>
                </div>
                <img src="{{ asset('modules/theme/backend/images/maintenance-sign.png') }}" alt="" class="image"/>
            </div>
        </div>
    </body>
</html>

