<!DOCTYPE html>
<html lang="{{ config('app.locale') }}">
<head>
	<link rel="preconnect" href="https://fonts.googleapis.com/" crossorigin=""/>
	<link rel="dns-prefetch" href="//fonts.googleapis.com/"/>
	<link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Kanit&display=swap"
	      onload="this.onload=null;this.rel='stylesheet'">
	<noscript>
		<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Kanit&display=swap">
	</noscript>
	
	<meta charset="UTF-8"/>
	<meta http-equiv="X-UA-Compatible" content="IE=edge"/>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
	<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1"/>
	<meta name="viewport" content="width=device-width, initial-scale=1">
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
	
	<link rel="canonical" href="{{ url('/') }}"/>
	
	<meta name="twitter:site" content="@twitter"/>
	<meta name="twitter:card" content="summary"/>
	<meta name="twitter:title" content="{{ ucwords($config->sitename) }} - {{ $config->title }}"/>
	<meta name="twitter:description"
	      content="{{ $config->description }}"/>
	<meta name="twitter:image" content="{{ url(core()->imgurl($config->logo,'img')) }}"/>
	
	
	<link preload href="{!! url(core()->imgurl($config->favicon,'img')) !!}" as="style"
	      onload="this.onload=null;this.rel='icon'" crossorigin=""/>
	<noscript>
		<link rel="icon" href="{!! url(core()->imgurl($config->favicon,'img')) !!}"/>
	</noscript>
	<meta name="msapplication-TileColor" content="#ffffff"/>
	<meta name="msapplication-TileImage" content="/assets/wm356/images/ms-icon-144x144.png"/>
	<meta name="theme-color" content="#ffffff"/>
	
	<meta name="format-detection" content="telephone=no"/>
	
	<link rel="preload" as="style"
	      href="/assets/wm356/css/style.css?v={{ filemtime(public_path('assets/wm356/css/style.css')) }}"
	      onload="this.onload=null;this.rel='stylesheet'">
	<noscript>
		<link rel="stylesheet"
		      href="/assets/wm356/css/style.css?v={{ filemtime(public_path('assets/wm356/css/style.css')) }}">
	</noscript>
	
	<link
			rel="preload"
			href="https://cdnjs.cloudflare.com/ajax/libs/flag-icon-css/7.5.0/css/flag-icons.min.css"
			as="style"
			type="text/css"
			integrity="sha512-+WVTaUIzUw5LFzqIqXOT3JVAc5SrMuvHm230I9QAZa6s+QRk8NDPswbHo2miIZj3yiFyV9lAgzO1wVrjdoO4tw=="
			crossorigin="anonymous"
			referrerpolicy="no-referrer"
	/>
	
	<link
			rel="stylesheet"
			href="https://cdnjs.cloudflare.com/ajax/libs/flag-icon-css/7.5.0/css/flag-icons.min.css"
			integrity="sha512-+WVTaUIzUw5LFzqIqXOT3JVAc5SrMuvHm230I9QAZa6s+QRk8NDPswbHo2miIZj3yiFyV9lAgzO1wVrjdoO4tw=="
			crossorigin="anonymous"
			referrerpolicy="no-referrer"
	/>
	
	<link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin="anonymous"/>
	<link rel="dns-prefetch" href="//cdnjs.cloudflare.com"/>
	
	<link
			rel="preload"
			href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
			as="style"
			type="text/css"
			integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg=="
			crossorigin="anonymous"
			referrerpolicy="no-referrer"
	/>
	
	<link
			rel="stylesheet"
			href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
			integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg=="
			crossorigin="anonymous"
			referrerpolicy="no-referrer"
	/>
	
	
	<script type="text/javascript">
        window["gif64"] = "data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7";
        window["Bonn"] = {
            boots: [],
            inits: [],
        };
	</script>
	
	<style>
        {{--.x-header {--}}
        {{--    background: {{ ($config->wallet_navbar_color? $config->wallet_navbar_color :'#1d1d1d') }}       !important;--}}
        {{--}--}}

        .x-footer.-ezl .-copy-right-container {
            background-color: {{ ($config->wallet_footer_color?$config->wallet_footer_color:'#255b48') }}       !important;
        }
	
	</style>
	
	@if($config->header_code)
		{!! $config->header_code !!}
	@endif
	
	@stack('styles')
	@stack('script')
	
	<link rel="preload" href="{{ asset('lang-').app()->getLocale() }}.js?v={{ date('Ymdhi') }}" as="script">
	<link rel="preload" href="{{ asset('assets/wm356/js/login.js') }}" as="script">
	<link rel="preload"
	      href="{{ asset('assets/wm356/js/minified_safe_optimized_no_jquery_bundle.js?v='. filemtime(public_path('assets/wm356/js/minified_safe_optimized_no_jquery_bundle.js')) ) }}"
	      as="script">
	<link rel="preload" href="{{ mix('assets/wm356/js/manifest.js') }}" as="script">
	<link rel="preload" href="{{ mix('assets/wm356/js/vendor.js') }}" as="script">
	<link rel="preload" href="{{ mix('assets/wm356/js/app.js') }}" as="script">
	<script src="{{ mix('assets/wm356/js/manifest.js') }}"></script>
	<script src="{{ mix('assets/wm356/js/vendor.js') }}"></script>
	<script src="{{ mix('assets/wm356/js/app.js') }}" id="mainscript" baseUrl="{{ url()->to('/') }}"></script>
	<script src="{{ asset('assets/wm356/js/minified_safe_optimized_no_jquery_bundle.js?v='.filemtime(public_path('assets/wm356/js/minified_safe_optimized_no_jquery_bundle.js')) ) }}"></script>
	<script src="{{ asset('lang-').app()->getLocale() }}.js?v={{ date('Ymdhi') }}"></script>
	<script src="{{ asset('assets/wm356/js/login.js') }}"></script>
	@laravelPWA
</head>

<body>

@include('wallet::layouts.header')

@yield('content')

@include('wallet::layouts.footer')


<script></script>

<script>
    var IS_ANDROID = false;
    var IS_MOBILE = false;
</script>

<script type="text/javascript" defer>
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

@stack('scripts')
</body>
</html>