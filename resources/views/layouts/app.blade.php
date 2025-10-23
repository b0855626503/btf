<!DOCTYPE html>
<html lang="{{ config('app.locale') }}">
<head>
    <link rel="preconnect" href="https://fonts.gstatic.com/" crossorigin=""/>
    <link rel="dns-prefetch" href="//fonts.gstatic.com/"/>
    <link preload href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap"
          as="font" onload="this.onload=null;this.rel='stylesheet'" crossorigin=""/>
    <noscript>
        <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" crossorigin=""
              rel="stylesheet"/>
    </noscript>

    <meta charset="UTF-8"/>
    <meta http-equiv="X-UA-Compatible" content="IE=edge"/>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no"/>
    <link rel="icon" type="image/png" sizes="32x32" href="{!! core()->imgurl($config->favicon,'img') !!}">
    <link rel="icon" type="image/x-icon" href="{!! core()->imgurl($config->favicon,'img') !!}">
    <link rel="apple-touch-icon" sizes="60x60" href="{!! core()->imgurl($config->favicon,'img') !!}">
    <meta name="apple-mobile-web-app-title" content="{{ ucwords($config->sitename) }} - {{ $config->title }}"/>
    <title>{{ ucwords($config->sitename) }} - {{ $config->title }}</title>
    <meta name="description" content="{{ $config->description }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="keywords"
          content="slot, casino, pgslot, joker, บาคาร่าออนไลน์, พนันออนไลน์, เว็บพนันออนไลน์, คาสิโนออนไลน์, บาคาร่า, บอลออนไลน์, สล็อต, ค่าน้ำดีที่สุด, เว็บพนัน, เกมสล็อต, นักพนัน"/>

    <meta property="og:title" content="{{ ucwords($config->sitename) }} - {{ $config->title }}"/>
    <meta property="og:description"
          content="{{ $config->description }}"/>
    <meta property="og:locale" content="{{ config('app.locale') }}"/>
    <meta property="og:site_name" content="{{ ucwords($config->sitename) }}"/>
    <meta property="og:url" content="{{ url('') }}"/>
    <meta property="og:image" content="{{ url(core()->imgurl($config->logo,'img')) }}"/>

    <link rel="canonical" href=""/>

    <meta name="twitter:site" content="@twitter"/>
    <meta name="twitter:card" content="summary"/>
    <meta name="twitter:title" content="{{ ucwords($config->sitename) }} - {{ $config->title }}"/>
    <meta name="twitter:description"
          content="{{ $config->description }}"/>
    <meta name="twitter:image" content="{{ url(core()->imgurl($config->logo,'img')) }}"/>


    <link preload href="{!! core()->imgurl($config->favicon,'img') !!}" as="style"
          onload="this.onload=null;this.rel='icon'" crossorigin=""/>
    <noscript>
        <link rel="icon" href="{!! core()->imgurl($config->favicon,'img') !!}"/>
    </noscript>
    <meta name="msapplication-TileColor" content="#ffffff"/>
    <meta name="msapplication-TileImage" content="assets/wm356/images/ms-icon-144x144.png"/>
    <meta name="theme-color" content="#ffffff"/>

    <meta name="format-detection" content="telephone=no"/>
    <link rel="stylesheet" href="assets/wm356/css/style.css?v=3"/>
    <link
            rel="stylesheet"
            href="https://cdn.jsdelivr.net/gh/lipis/flag-icons@6.6.6/css/flag-icons.min.css"
    />
    <script type="text/javascript">
        window["gif64"] = "data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7";
        window["Bonn"] = {
            boots: [],
            inits: [],
        };
    </script>
    @stack('script')

    <style>
        .x-header {
            background: {{ ($config->wallet_navbar_color? $config->wallet_navbar_color :'#1d1d1d') }}    !important;
        }

        .x-footer.-ezl .-copy-right-container {
            background-color: {{ ($config->wallet_footer_color?$config->wallet_footer_color:'#255b48') }}    !important;
        }
        {{--body, html {--}}
        {{--    height: 100%;--}}
        {{--    font-family: FC Iconic Text, Helvetica Neue, Helvetica, Arial, sans-serif;--}}
        {{--    background-color: {{ ($config->wallet_body_start_color? $config->wallet_body_start_color :'#0f0f0f') }} !important;--}}
        {{--}--}}
        {{--.x-provider-category.-provider_casinos {--}}
        {{--    background: {{ ($config->wallet_body_start_color? $config->wallet_body_start_color :'#0f0f0f') }} !important;--}}
        {{--}--}}

        {{--#main__content {--}}
        {{--    background: {{ ($config->wallet_body_start_color? $config->wallet_body_start_color :'#0f0f0f') }} !important;--}}
        {{--}--}}
    </style>

    @if($config->header_code)
        {!! $config->header_code !!}
    @endif
</head>

<body class="">

<nav class="x-header js-header-selector navbar navbar-expand-lg -anon">
    <div class="container-fluid -inner-container">
        <div class="">
            <button type="button" class="btn bg-transparent p-0 x-hamburger" data-toggle="modal"
                    data-target="#themeSwitcherModal">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>

        <div id="headerBrand">
            <a class="navbar-brand" href="{{ route('customer.home.index') }}">
                <img alt="{{ $config->description }}" class="-logo -default img-fluid" width="440"
                     height="104" src="{{ url(core()->imgurl($config->logo,'img')) }}"/>
                <img alt="{{ $config->description }}" class="-logo -invert img-fluid" width="440"
                     height="104" src="{{ url(core()->imgurl($config->logo,'img')) }}"/>
            </a>
        </div>

        <div class="x-menu">
            <div class="-menu-container">

            </div>
        </div>

        <div id="headerContent">
            <div class="d-flex">
                <a href="{{ $config->linelink }}" class="x-header-btn-support -in-anon" target="_blank"
                   rel="noreferrer nofollow">
                    <picture>
                        <source type="image/webp" srcset="/assets\wm356\web\ezl-wm-356\img\ic-line-support.webp?v=1"/>
                        <source type="image/png?v=2" srcset="/assets\wm356\web\ezl-wm-356\img\ic-line-support.png?v=1"/>
                        <img alt="{{ $config->description }}" class="img-fluid -ic" loading="lazy" fetchpriority="low"
                             width="120" height="39" src="/assets\wm356\web\ezl-wm-356\img\ic-line-support.png?v=1"/>
                    </picture>
                    <picture>
                        <source type="image/webp"
                                srcset="/assets\wm356\web\ezl-wm-356\img\ic-line-support-mobile.webp?v={{ time() }}"/>
                        <source type="image/png"
                                srcset="/assets\wm356\web\ezl-wm-356\img\ic-line-support-mobile.png?v={{ time() }}"/>
                        <img alt="{{ $config->description }}" class="img-fluid -ic -mobile" loading="lazy"
                             fetchpriority="low"
                             width="28" height="28"
                             src="/assets\wm356\web\ezl-wm-356\img\ic-line-support-mobile.png?v={{ time() }}"/>
                    </picture>
                </a>

                <a href="{{ route('customer.session.store') }}" class="-btn-header-login btn mr-1 mr-sm-2">
                    {{ __('app.login.register') }}
                </a>

                <a href="{{ route('customer.session.index') }}" class="-btn-header-login btn" data-toggle="modal"
                   data-target="#loginModal">
                    {{ __('app.login.login') }}
                </a>
            </div>
        </div>
    </div>
</nav>


@yield('content')

<footer class="x-footer -ezl -anon">
    <div class="-inner-wrapper lazyload x-bg-position-center"
         data-bgset="https://asset.cloudigame.co/build/admin/img/wt_theme/ezl/footer-inner-bg.png">
        <div class="container -inner-title-wrapper">
            {!! $config->content_detail !!}
        </div>


    </div>

    <div class="text-center -copy-right-container">
        <p class="mb-0 -copy-right-text">
            Copyright © 2023 {{ $config->sitename }}. All Rights Reserved.
        </p>
    </div>
</footer>

@auth
    <div class="x-button-actions" id="account-actions-mobile">
        <div class="-outer-wrapper">
            <div class="-left-wrapper">
      <span class="-item-wrapper">
        <span class="-ic-img">
          <span class="-text d-block">{{ __('app.home.refill') }}</span>
          <a href="#deposit" data-toggle="modal" data-target="#depositModal">
            <img src="/images/icon/deposit.png">
          </a>
        </span>
      </span>
                <span class="-item-wrapper">
        <span class="-ic-img">
          <span class="-text d-block">{{ __('app.home.withdraw') }}</span>
          <a href="#withdraw" data-toggle="modal" data-target="#withdrawModal">
            <img src="/images/icon/withdraw.png">
          </a>
        </span>
      </span>
            </div>
            @if(request()->routeIs('customer.credit.*'))
                <a href="{{ route('customer.credit.index') }}">
                    <span class="-center-wrapper js-footer-lobby-selector js-menu-mobile-container">
        <div class="-selected">
          <img src="/images/icon/menu.png">
          <h5>{{ __('app.home.playgame') }}</h5>
        </div>
      </span>
                </a>
            @else
                <a href="{{ route('customer.home.index') }}">
                            <span class="-center-wrapper js-footer-lobby-selector js-menu-mobile-container">
        <div class="-selected">
          <img src="/images/icon/menu.png">
          <h5>{{ __('app.home.playgame') }}</h5>
        </div>
      </span>
                </a>
            @endif


            <div class="-fake-center-bg-wrapper">
                <svg viewBox="-10 -1 30 12">
                    <defs>
                        <linearGradient id="rectangleGradient" x1="0%" y1="0%" x2="0%" y2="100%">
                            <stop offset="0%" stop-color="#225db9"></stop>
                            <stop offset="100%" stop-color="#041d4a"></stop>
                        </linearGradient>
                    </defs>
                    <path d="M-10 -1 H30 V12 H-10z M 5 5 m -5, 0 a 5,5 0 1,0 10,0 a 5,5 0 1,0 -10,0z"></path>
                </svg>
            </div>

            <div class="-right-wrapper">
      <span class="-item-wrapper">
        <span class="-ic-img">
          <span class="-text d-block">{{ __('app.home.promotion') }}</span>
          <a href="{{ route('customer.promotion.index') }}">
            <img src="/images/icon/tab_promotion.png">
          </a>
        </span>
      </span>
                <span class="-item-wrapper">
        <span class="-ic-img">
          <span class="-text d-block">{{ __('app.home.contact') }}</span>
          <a href="{{ $config->linelink }}">
            <img src="/images/icon/support-mobile.webp">
          </a>
        </span>
      </span>
            </div>
            <div class="-fully-overlay js-footer-lobby-overlay"></div>

        </div>
    </div>
@endauth


{{--<script src="https://js.pusher.com/7.2.0/pusher.min.js"></script>--}}

<script></script>

{{--<script>--}}
{{--    Bonn.boots.push(function () {--}}
{{--        setTimeout(function () {--}}
{{--            $("#bankInfoModal").modal("show");--}}
{{--        }, 500);--}}
{{--    });--}}
{{--</script>--}}

<script>
    var IS_ANDROID = false;
    var IS_MOBILE = false;
</script>


{{--<script src="assets/wm356/web/ezl-wm-356/app.629ea432.js"></script>--}}


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


{{--<script src="{{ mix('assets/wm356/js/manifest.js') }}"></script>--}}
<script src="{{ mix('assets/wm356/js/vendor.js') }}"></script>

<script src="assets/wm356/js/runtime.1ba6bf05.js?v=5"></script>
<script src="assets/wm356/js/0.e84cf97a.js?v=1"></script>
<script src="assets/wm356/js/1.9a969cca.js?v=1"></script>
<script src="https://code.jquery.com/jquery-3.7.1.js" integrity="sha256-eKhayi8LEQwp4NKxN+CfCh+3qOVUtJn3QNZ0TciWLP4="
        crossorigin="anonymous"></script>
<script src="assets/wm356/web/ezl-wm-356/app.629ea432.js?v=1"></script>
<script src="{{ mix('assets/wm356/js/app.js') }}" id="mainscript" baseUrl="{{ url()->to('/') }}"></script>
{{--<script src="{{ mix('assets/wm356/js/vue.js') }}" id="mainscript" baseUrl="{{ url()->to('/') }}"></script>--}}
@stack('scripts')
<script src="{{ asset('lang-').app()->getLocale() }}.js?time={{ time() }}"></script>

{{--@stack('scripts')--}}
{{--<script src="{{ asset('js/js.js?'.time()) }}"></script>--}}
</body>
</html>


