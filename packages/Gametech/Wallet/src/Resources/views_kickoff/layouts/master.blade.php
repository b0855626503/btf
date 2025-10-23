<!DOCTYPE html>
<html lang="{{ config('app.locale') }}">
<head>
	<meta charset="utf-8">
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
	
	<link rel="icon" type="image/png" sizes="32x32" href="{!! core()->imgurl($config->favicon,'img') !!}">
	<link rel="icon" type="image/x-icon" href="{!! core()->imgurl($config->favicon,'img') !!}">
	<link rel="apple-touch-icon" sizes="60x60" href="{!! core()->imgurl($config->favicon,'img') !!}">
	<meta name="apple-mobile-web-app-title" content="{{ ucwords($config->sitename) }} - {{ $config->title }}"/>
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
	
	<title>{{ ucwords($config->sitename) }} - {{ $config->title }}</title>
	
	<link rel="canonical" href="{{ url('/') }}"/>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css"
	      integrity="sha512-GQGU0fMMi238uA+a/bdWJfpUGKUkBdgfFdgBm72SUQ6BeyWjoY/ton0tEjH+OSH9iP4Dfh+7HM0I9f5eR0L/4w=="
	      crossorigin="anonymous" referrerpolicy="no-referrer"/>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
	<link href="https://cdnjs.cloudflare.com/ajax/libs/Swiper/6.7.5/swiper-bundle.min.css" rel="stylesheet">
	<link rel="stylesheet" type="text/css"
	      href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
	<link rel="stylesheet" type="text/css"
	      href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.5.0/font/bootstrap-icons.min.css">
	<link href="/assets/kimberbet/css/trans.css" rel="stylesheet">
	<link href="/assets/kimberbet/css/stylemain.css?v={{ filemtime(public_path('assets/kimberbet/css/stylemain.css')) }}"
	      rel="stylesheet">
	<link href="/assets/kimberbet/css/slide.css?v={{ filemtime(public_path('assets/kimberbet/css/slide.css')) }}"
	      rel="stylesheet">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.9.3/dropzone.min.css"
	      integrity="sha512-jU/7UFiaW5UBGODEopEqnbIAHOI8fO6T99m7Tsmqs2gkdujByJfkCbbfPSN4Wlqlb9TGnsuC0YgUgWkRBK7B9A=="
	      crossorigin="anonymous" referrerpolicy="no-referrer"/>
	<style>
	
	</style>
	@stack('styles')
	
	@laravelPWA
</head>
<body>

@include('wallet::layouts.component')

<div id="app" class="main" style="position: relative;">
	
	@include('wallet::layouts.header_member')
	
	{{--	@include('wallet::layouts.mobile')--}}
	
	<div id="member" class="member">
		@yield('content')
	</div>
	
	@include('wallet::layouts.footer')
	
</div>

@include('wallet::layouts.contact')
@stack('components')

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p"
        crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Swiper/6.7.5/swiper-bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.9.3/dropzone.min.js"
        integrity="sha512-U2WE1ktpMTuRBPoCFDzomoIorbOyUv0sP8B+INA3EzNAhehbzED1rOJg6bCqPf/Tuposxb5ja/MAUnC8THSbLQ=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="{{ asset('lang-').app()->getLocale() }}.js?v={{ date('YmdHi') }}"></script>
<script>
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
<script>
    window.vueData = @json([
        'menus' => $menus,
        'webconfig' => $webconfig,
    ]);
    window.vueData.i18n = window.i18n || {};

    Dropzone.autoDiscover = false;
</script>
<script src="{{ mix('assets/kimberbet/js/manifest.js') }}"></script>
<script src="{{ mix('assets/kimberbet/js/vendor.js') }}"></script>
<script src="{{ mix('assets/kimberbet/js/app.js') }}" id="mainscript" baseUrl="{{ url()->to('/') }}"></script>

<script src="{{ asset('assets/kimberbet/js/js.js?v='.filemtime(public_path('assets/kimberbet/js/js.js'))) }}"></script>

@if (isset($notice_new[Route::currentRouteName()]['messages']) && !empty($notice_new[Route::currentRouteName()]['messages']))
	<div class="modal fade announcement-modal" id="announcementModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header">
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<div class="swiper mySwiper">
						<div class="swiper-wrapper">
							@foreach ($notice_new[Route::currentRouteName()]['messages'] as $item)
								<div class="swiper-slide">
									<div class="p-3 fs-5 text-center">{!! $item !!}</div>
								</div>
							@endforeach
						</div>
						<div class="swiper-pagination"></div>
					</div>
				</div>
			</div>
		</div>
	</div>
	
	{{-- สั่งเปิด modal และ init swiper เมื่อโหลดหน้า --}}
	<script>
        let swiperInstance = null;

        window.addEventListener('DOMContentLoaded', () => {
            const modalEl = document.getElementById('announcementModal');

            modalEl.addEventListener('shown.bs.modal', function () {
                // ถ้ามีอยู่แล้วให้ destroy ก่อนเพื่อป้องกันซ้ำ
                if (swiperInstance) {
                    swiperInstance.destroy(true, true);
                }

                swiperInstance = new Swiper(".mySwiper", {
                    loop: true,
                    autoplay: {
                        delay: 5000,
                        disableOnInteraction: false
                    },
                    pagination: {
                        el: ".swiper-pagination",
                        clickable: true
                    }
                });
            });

            $('#announcementModal').modal('show');

            // swiper.on('slideChange', function () {
            //     $('#announcementModal').modal('handleUpdate');
            // });
        });
	</script>
@endif
<script>
    document.addEventListener("DOMContentLoaded", function () {
        
        const private_channel = '{{ env('APP_NAME') }}_members.{{ auth()->guard('customer')->user()->code }}';

        window.Echo.private(private_channel)
            .notification((notification) => {
                window.Toast.fire({
                    icon: 'success',
                    title: notification.message
                });
                window.reLoadCredit();
            });
    });

</script>

@stack('script')
@stack('scripts')
</body>
</html>