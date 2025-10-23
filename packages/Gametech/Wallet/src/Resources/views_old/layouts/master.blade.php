<!DOCTYPE html>
<html lang="{{ config('app.locale') }}">
<head>
    <meta charset="UTF-8">
    <title>{{ ucwords($config->sitename) }} - {{ $config->title }}</title>
    <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ core()->imgurl($config->favicon,'img') }}">
    <meta name="description" content="{{ $config->description }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <!-- Google Font: Source Sans Pro -->
    <link href="https://fonts.googleapis.com/css?family=Prompt&display=swap" rel="stylesheet">

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css"
    />


    <link rel="stylesheet" href="{{ mix('assets/old/css/web.css') }}">
    <link rel="stylesheet" href="{{ asset('css/all.css') }}">

    <style>
        .nav-top {
                background: {{ ($config->wallet_navbar_color?$config->wallet_navbar_color:'#6f0000') }}            !important;
        }

        .nav-footer {
            background: {{ ($config->wallet_footer_color?$config->wallet_footer_color:'#6f0000') }}            !important;
        }

        .custom-theme {
            background: linear-gradient(45deg, {{ ($config->wallet_body_start_color?$config->wallet_body_start_color:'#200122') }} 10%, {{ ($config->wallet_body_stop_color?$config->wallet_body_stop_color:'#6f0000') }} 90%) !important;
        }

        .exchange {
            background: {{ ($config->wallet_footer_exchange?$config->wallet_footer_exchange:'#6f0000') }}            !important;
        }

        .exchange-single {
            background: {{ ($config->wallet_footer_exchange?$config->wallet_footer_exchange:'#6f0000') }}            !important;
        }

        a.active, a.active i, a.active p {
            color: {{ ($config->wallet_footer_active?$config->wallet_footer_active:'#6f0000') }}            !important;
        }

        .newsboxhead {
            height : 50px;
        }

        .newsboxhead span {
            font-size: x-large;
        }


    </style>
    @stack('styles')

    @if($config->header_code)
        {!! $config->header_code !!}
    @endif
</head>

<body class="layout-navbar-fixed custom-theme">


<div id="app" class="bg-login">

    <div class="wrapper">

        <nav class="navbar navbar-expand border-bottom nav-header nav-top">
            <div class="container">
                <div class="row w-100">
                    <div class="col-3 w-40">@yield('back')</div>
                    {!! core()->showImg($config->logo,'img','','','img-top') !!}
                    <div class="col-1 offset-8">
                        <a href="{{ route('customer.session.destroy') }}"
                           class="nav-link text-light p-2 signout-btn mx-auto hand-point">
                            <i class="fal fa-sign-out-alt"></i>
                        </a>
                    </div>
                </div>

            </div>
        </nav>


        <div style="margin-top: 6rem;margin-bottom: 6rem;">
            @if(isset($notice[Route::currentRouteName()]['route']) === true)
                <div class="container">
                    <div class="row">
                        <div class="col-md-8 offset-md-2 col-sm-12">
                            <div class="card text-light card-trans">
                                <div class="containalert">
                                    <div class="newsboxhead" data-animatable="fadeInUp" data-delat="200">
                                        <div class="-icon-container">
                                            <i class="fas fa-volume-up"></i>
                                        </div>
                                        <span> {{ $notice[Route::currentRouteName()]['msg'] }} </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            @endif

            @yield('content')
        </div>

        @include('wallet::layouts.footer')

    </div>
</div>
<div class="myAlert-top alertcopy">
    <i class="fal fa-check-circle"></i>
    <br>
    <strong>
        {{ __('app.home.copy') }} </strong>
</div>

<script type="text/javascript">
    window.flashMessages = [];
    window.serverErrors = [];

    @foreach (['success', 'warning', 'error', 'info'] as $key)
    @if ($value = session($key))
    window.flashMessages.push({'type': '{{ $key }}', 'message': "{{ $value }}"});
    @endif
        @endforeach

        @if (isset($errors))
        @if (count($errors))
        window.serverErrors = @json($errors->getMessages());
    @endif
    @endif

</script>

<script src="{{ mix('assets/old/js/manifest.js') }}"></script>
<script src="{{ mix('assets/old/js/vendor.js') }}"></script>
<script src="{{ mix('assets/old/js/app.js') }}"></script>
<script id="mainscript" baseUrl="{{ url()->to('/') }}" src="{{ mix('assets/old/js/web.js') }}"></script>
<script src="{{ asset('assets/ui/js/ui.js') }}" ></script>
<script src="{{ asset('assets/old/js/mainjs.js?v='.microtime()) }}"></script>

@stack('scripts')
<script>

    @if(isset($notice_new[Route::currentRouteName()]['route']) === true)

    $(document).ready(function () {

        Swal.fire({
            html: '{!! $notice_new[Route::currentRouteName()]['msg'] !!}',
            focusConfirm: false,
            showCloseButton: true,
            showConfirmButton: false
        });

    });

    @endif

    const private_channel = '{{ env('APP_NAME') }}_members.{{ auth()->guard('customer')->user()->code }}';


    Echo.private(private_channel)
        .notification((notification) => {
            Toast.fire({
                icon: 'success',
                title: notification.message
            });
            @if(request()->routeIs('customer.topup.hengpay'))
            close();
            @endif
        });


</script>
@stack('script')
</body>
</html>
