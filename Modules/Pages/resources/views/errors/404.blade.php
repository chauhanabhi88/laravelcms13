<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <!-- CSRF Token -->
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>
        {!! "404 page" !!}
        </title>
        <link rel="stylesheet" href="{{ asset('modules/theme/backend/icheck-bootstrap/icheck-bootstrap.min.css') }}">
        <link rel="stylesheet" href="{{ asset('modules/theme/backend/css/adminlte.min.css') }}">
        <link rel="stylesheet" href="{{ asset('modules/theme/backend/css/custom_theme.css') }}">
    </head>
    <body class="hold-transition login-page error-page">
        <div class="login-box error-box">
            <div class="d-flex align-items-center text-center auth bg-primary error-main">
                <div class="row col-lg-12 flex-grow">
                    <div class="col-lg-7 mx-auto text-white">
                        <div class="row align-items-center d-flex flex-row">
                            <div class="col-lg-6 text-lg-right pr-lg-4">
                                <h1 class="display-1 mb-0">404</h1>
                            </div>
                            <div class="col-lg-6 error-page-divider text-lg-left pl-lg-4">
                                <h2>SORRY!</h2><h3 class="font-weight-light">The page you’re looking for was not found.</h3>
                            </div>
                        </div>
                        <div class="row mt-5">
                            <div class="col-12 mt-xl-2">
                                <p class="text-white font-weight-medium text-center">Copyright © 2020 All rights reserved.</p>
                            </div>
                        </div>
                    </div>
                </div>    
            </div>
        </div>

        <script type="text/javascript" src="{{ asset('modules/theme/js/jquery.js') }}"></script>
        <script type="text/javascript" src="{{ asset('modules/theme/backend/bootstrap/bootstrap.bundle.min.js') }}"></script>
        <script type="text/javascript" src="{{ asset('modules/theme/backend/js/adminlte.min.js') }}"></script>
    </body>
</html>
