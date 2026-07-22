<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>
        @yield('title')
    </title>

    <!-- staradmin css start -->
    <link rel="stylesheet" href="{{ asset('modules/theme/backend/staradmin/vendors/iconfonts/mdi/css/materialdesignicons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('modules/theme/backend/staradmin/vendors/iconfonts/ionicons/dist/css/ionicons.css') }}">
    <link rel="stylesheet" href="{{ asset('modules/theme/backend/staradmin/vendors/iconfonts/flag-icon-css/css/flag-icon.min.css') }}">
    <link rel="stylesheet" href="{{ asset('modules/theme/backend/staradmin/vendors/css/vendor.bundle.base.css') }}">
    <link rel="stylesheet" href="{{ asset('modules/theme/backend/staradmin/vendors/css/vendor.bundle.addons.css') }}">
    <link rel="stylesheet" href="{{ asset('modules/theme/backend/staradmin/css/shared/style.css') }}">
    <link rel="stylesheet" href="{{ asset('modules/theme/backend/staradmin/css/style.css') }}">
    <link rel="shortcut icon" href="{{ asset('modules/theme/backend/staradmin/images/favicon.ico') }}" />
    <!-- staradmin css end -->


    <link href="{{ asset('modules/theme/backend/fontawesome/css/all.min.css') }}" rel="stylesheet" media="all">
    <link href="{{ asset('modules/theme/backend/vendor/font-awesome-4.7/css/font-awesome.min.css') }}" rel="stylesheet" media="all">
    <!-- <link href="{{ asset('modules/theme/backend/vendor/mdi-font/css/material-design-iconic-font.min.css') }}" rel="stylesheet" media="all"> -->
    <!-- <link href="{{ asset('modules/theme/backend/vendor/bootstrap-4.1/bootstrap.min.css') }}" rel="stylesheet" media="all"> -->
    <!-- <link href="{{ asset('modules/theme/backend/vendor/animsition/animsition.min.css') }}" rel="stylesheet" media="all"> -->
    <link href="{{ asset('modules/theme/backend/vendor/select2/select2.min.css') }}" rel="stylesheet" media="all">
    <link href="{{ asset('modules/theme/backend/vendor/perfect-scrollbar/perfect-scrollbar.css') }}" rel="stylesheet" media="all">
    <link href="{{ asset('modules/theme/backend/css/theme.css') }}" rel="stylesheet" media="all">
    @foreach($cssFiles as $css)
    <link media="all" type="text/css" rel="stylesheet" href="{{ asset($css) }}">
    @endforeach


   
    <link rel="stylesheet" href="{{ asset('modules/theme/backend/css/developer-style.css') }}">
    <link rel="stylesheet" href="{{ asset('modules/theme/css/jquery-ui.css') }}">
</head>

@php
$sidebar = Config::get('theme.sidebar');
@endphp


<body class="dark-theme sidebar-dark">
    
    <div class="custom-loader" style="display: block;"></div>
    @if($sidebar == 1)
    <div class="container-scroller">
        @include('theme::layouts.backend.partials.staradmin.navbar')
        <div class="container-fluid page-body-wrapper">
            @include('theme::layouts.backend.partials.staradmin.sidebar')
            <div class="main-panel">
                <section class="content-wrapper">
                    @yield('content-header')
                    @include('theme::layouts.backend.partials.notifications')
                    @yield('content')
                </section>
                @include('theme::layouts.backend.partials.staradmin.footer')
            </div>
        </div>
    </div>
    @endif



    <!--use for temp image for summer note -->
    <script>
        var csrfToken = '{{ csrf_token() }}';
        var image_url = '{{route("admin.summernote.image_upload_temp", updateUrlParams())}}';
    </script>

    <!-- Bootstrap JS-->

    <script src="{{ asset('modules/theme/backend/vendor/jquery-3.2.1.min.js') }}"></script>
    <script type="text/javascript" src="{{ asset('modules/theme/js/jquery-ui.js') }}"></script>

    <script src="{{ asset('modules/theme/backend/vendor/bootstrap-4.1/popper.min.js') }}"></script>
    <script src="{{ asset('modules/theme/backend/vendor/bootstrap-4.1/bootstrap.min.js') }}"></script>


    <!-- Vendor JS -->
    <!-- <script src="{{ asset('modules/theme/backend/vendor/animsition/animsition.min.js') }}"></script> -->
    <script src="{{ asset('modules/theme/backend/vendor/perfect-scrollbar/perfect-scrollbar.js') }}"></script>
    <script src="{{ asset('modules/theme/backend/vendor/select2/select2.min.js') }}">
    </script>


    <!-- Main JS-->
    <script src="{{ asset('modules/theme/backend/js/main.js') }}"></script>
    <script type="text/javascript" src="{{ asset('modules/theme/js/jquery.validate.js') }}"></script>

    @foreach($jsFiles as $js)
    <script src="{{ asset($js) }}" type="text/javascript"></script>
    @endforeach
    <script type="text/javascript" src="{{ asset('modules/core/js/backend/custom.js') }}"></script>
    <!-- staradmin js start -->
    <!-- <script src="{{ asset('modules/theme/backend/staradmin/js/shared/off-canvas.js') }}"></script>
    <script src="{{ asset('modules/theme/backend/staradmin/js/misc.js') }}"></script> -->

    <!-- staradmin js end -->
    <script type="text/javascript">
        /* tooltip js */
        $('body').tooltip({
            selector: '[data-toggle="tooltip"]'
        });
        /* change continue label of tooltip */
        if ($('input').hasClass('status')) {
            jQuery(document).on('change', '.status', function() {
                if (($(this).is(':checked'))) {
                    $('.tooltip-inner').html("{{trans('core::core.options.status.enable')}}");
                    $(this).parent().parent().attr('data-original-title', "{{trans('core::core.options.status.enable')}}");
                } else {
                    $('.tooltip-inner').html("{{trans('core::core.options.status.disable')}}");
                    $(this).parent().parent().attr('data-original-title', "{{trans('core::core.options.status.disable')}}");
                }
            });
        }
    </script>
    @section('scripts')
    @show

    @stack('js-stack')
    <script>
        $(document).ready(function() {
            jQuery.validator.addMethod("newPassword", function(value, element) {
                var min_password_length = "{{!empty(settings('customer', 'min_password_length')) ? settings('customer', 'min_password_length') : 6}}";
                var max_password_length = "{{!empty(settings('customer', 'max_password_length')) ? settings('customer', 'max_password_length') : 20 }}";
                var passwordRegex = new RegExp("^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[!\"@./`[\\]{|}\\\\~:;_<=>?'\(\)#+\,\$%\^&\*\-]).{" + min_password_length + "," + max_password_length + "}$", "m");
                if (value.length) {
                    return passwordRegex.test(value);
                }
                return true;
            }, "{{trans('customer::customer.messages.invalid_password',['password_length'=>!empty(settings('customer', 'min_password_length')) ? settings('customer', 'min_password_length') : 6, 'max_password_length' => !empty(settings('customer', 'max_password_length')) ? settings('customer', 'max_password_length') : 20 ])}}");
            fixScroll();
            // Ignore Summernote Elements
            $('form').each(function() {
                if ($(this).data('validator'))
                    $(this).data('validator').settings.ignore = ".note-editor *";
            });
            // End Ignore Summernote

            // When the user scrolls the page, execute myFunction
            window.onscroll = function() {
                var sidebar = <?php echo $sidebar; ?>;
                fixScroll();
                if (sidebar == 2) {
                    fixSidebarScroll();
                }
            };
            // Add the sticky class to the fixElement when you reach its scroll position. Remove "sticky" when you leave the scroll position
            function fixScroll() {
                // Get the header
                var fixElement = document.getElementsByClassName("page-title-header");
                var sidebar = "{{ \Config('theme.sidebar') }}";

                if (fixElement.length > 0) {
                    // Get the offset position of the navbar
                    var sticky = fixElement[0].offsetTop;
                    if (window.pageYOffset > 0) {
                        if (sidebar == 1) {
                            fixElement[0].classList.add("sticky-vertical-header");
                        } else if (sidebar == 3) {
                            fixElement[0].classList.add("sticky-staradmin-header");
                        } else {
                            fixElement[0].classList.add("sticky-header");
                        }
                    } else {
                        if (sidebar == 1) {
                            fixElement[0].classList.remove("sticky-vertical-header");
                        } else if (sidebar == 3) {
                            fixElement[0].classList.remove("sticky-staradmin-header");
                        } else {
                            fixElement[0].classList.remove("sticky-header");
                        }
                    }
                }
            }

            function fixSidebarScroll() {
                // Get the header
                // var fixElement = document.getElementsByClassName("header-desktop3");
                // if (fixElement.length > 0) {
                //     // Get the offset position of the navbar
                //     var sticky = fixElement[0].offsetTop;
                //     if (window.pageYOffset > sticky) {
                //         fixElement[0].classList.add("sticky-header");
                //     } else {
                //         fixElement[0].classList.remove("sticky-header");
                //     }
                // }
            }
        });
    </script>
</body>

</html>
