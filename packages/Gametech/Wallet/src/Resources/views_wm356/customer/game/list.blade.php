@extends('wallet::layouts.master')

{{-- page title --}}
@section('title','')


@section('content')
	<div id="main__content" class="x-ez-games-by-category">
		<section class="x-category-index -v2">
			<div class="-nav-menu-container js-category-menus">
				<div class="container-fluid pr-lg-0">
					<div class="-nav-menu-container js-category-menus -v2">
						<div class="x-quick-transaction-buttons js-quick-transaction-buttons">
							<a class="btn -btn -promotion -vertical" href="{{ route('customer.promotion.index') }}"
							   target="_blank"
							   rel="noopener nofollow">
                            <span class="-ic-wrapper"> <img alt="โปรโมชั่นสุดคุ้ม เพื่อลูกค้าคนสำคัญ"
                                                            class="img-fluid -ic" width="40" height="40"
                                                            src="/assets/wm356/images/ic-quick-transaction-button-promotion.png?v=2"/></span>
								
								<span class="-btn-inner-content">
            <span class="-btn-inner-content-title">{{ __('app.home.promotion') }}</span>
        </span>
							</a>
							
							<button
									class="btn -btn -deposit x-bg-position-center lazyloaded"
									data-toggle="modal"
									data-target="#depositModal"
									data-bgset="/assets/wm356/images/btn-deposit-bg.png?v=2"
									style="background-image: url('/assets/wm356/images/btn-deposit-bg.png?v=2');"
							>
                            <span class="-ic-wrapper"> <img alt="ฝากเงินง่ายๆ ด้วยระบบออโต้ การันตี 1 นาที"
                                                            class="img-fluid -ic" width="40" height="40"
                                                            src="https://asset.cloudigame.co/build/admin/img/wt_theme/ezl/ic-account-deposit.png"/></span>
								
								<span class="-btn-inner-content">
            <span class="-btn-inner-content-title">{{ __('app.home.refill') }}</span>
        </span>
							</button>
							
							<button
									class="btn -btn -withdraw x-bg-position-center lazyloaded"
									data-toggle="modal"
									data-target="#withdrawModal"
									data-bgset="/assets/wm356/images/btn-withdraw-bg.png?v=2"
									style="background-image: url('/assets/wm356/images/btn-withdraw-bg.png?v=2');"
							>
                            <span class="-ic-wrapper"> <img alt="ถอนเงินง่ายๆ ด้วยระบบออโต้ การันตี เท่าไหร่ก็จ่าย"
                                                            class="img-fluid -ic" width="40" height="40"
                                                            src="https://asset.cloudigame.co/build/admin/img/wt_theme/ezl/ic-account-withdraw.png"/></span>
								
								<span class="-btn-inner-content">
            <span class="-btn-inner-content-title">{{ __('app.home.withdraw') }}</span>
        </span>
							</button>
						</div>
						<nav class="nav-menu" id="navbarCategory">
							<div class="menu-scroll-wrapper d-lg-none d-block">
								<div class="menu-scroll">
									@foreach($gameTypes as $gameType)
										<div class="menu-item">
											<a href="{{ route('customer.cats.list', ['id' => strtolower($gameType->id)]) }}">
												<img src="{{ $gameType->icon }}">
												<small>{{ __('app.game.'.strtolower($gameType->id)) }}</small>
											</a>
										</div>
									@endforeach
								</div>
							</div>
							
							<div class="sidebar-menu d-lg-block d-none">
								@foreach($gameTypes as $gameType)
									<a href="{{ route('customer.cats.list', ['id' => strtolower($gameType->id)]) }}"
									   class="sidebar-item">
										<img src="{{ $gameType->icon }}" alt="คาสิโน">
										<span>{{ __('app.game.'.strtolower($gameType->id)) }}</span>
									</a>
								@endforeach
							
							</div>
						</nav>
					</div>
				</div>
			</div>
			
			<div class="-games-list-outer-container -has-sidebar">
				<div class="container-fluid -container-fluid">
					
					<div class="-games-list-container js-game-scroll-container js-game-container">
						
						<div class="-games-list-wrapper">
							<div class="-game-title-wrapper">
								
								<div class="-game-title-inner">
									<h2 class="-game-title h3 -shimmer">
										{{ $game_name->name }}
									</h2>
								</div>
								
								<div class="-game-search-inner">
									<form id="frmseach">
										<div class="input-group x-search-component -v2">
											<input type="text" id="searchKeyword" name="search" value=""
											       class="x-form-control form-control -form-search-input"
											       placeholder="ค้นหาชื่อเกม..." data-search/>
										</div>
									</form>
								</div>
							
							</div>
							
							<ul class="navbar-nav -slot-provider-page">
								@foreach($games as $i => $item)
									<li class="nav-item" data-filter-item
									    data-filter-name="{{ strtolower($item->name) }}">
										<div
												class="x-game-list-item-macro-in-share js-game-list-toggle -big -cannot-entry -untestable -use-promotion-alert"
												data-status="-cannot-entry -untestable">
											<div class="-inner-wrapper">
												
												
												<picture>
													<source type="image/webp"
													        data-srcset="{{ $item->image }}"/>
													<source type="image/png"
													        data-srcset="{{ $item->image }}"/>
													<img
															loading="lazy"
															alt="smm-{{ $id }} cover image png"
															class="img-fluid lazyload -cover-img"
															width="400"
															height="580"
															data-src="{{ $item->image }}"
															src="https://asset.cloudigame.co/build/admin/img/ezs-default-loading-big.png"
													/>
												</picture>
												
												<div class="-overlay">
													<div class="-overlay-inner">
														<div class="-wrapper-container">
															<a href="{{ route('customer.game.redirect', [ 'id' => $id , 'name' => $item->code ,'method' => $item->method ]) }}"
															   class="js-account-approve-aware -btn -btn-play"
															   data-toggle="modal" data-target="#gametechPopup"
															   target="gametechPopup">
																<i class="fas fa-play"></i>
																<span class="-text-btn">{{ __('app.home.join') }}</span>
															</a>
														</div>
													</div>
												</div>
											</div>
											<div class="-title">{{ $item->name }}</div>
										</div>
									</li>
								@endforeach
							
							
							</ul>
						</div>
					</div>
				</div>
			</div>
		</section>
		
		<section class="js-replace-seo-section-container">
		
		</section>
	</div>
@endsection

@push('scripts')
	<script type="text/javascript">

        function isMobile() {
            return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        }

        document.querySelectorAll("a[target='gametechPopup']").forEach(link => {
            link.addEventListener("click", function (e) {
                e.preventDefault();
                const url = link.href;

                if (isMobile()) {
                    // ✅ แสดง Toast ก่อน
                    if (window.Toast && window.Toast.fire) {
                        window.Toast.fire({
                            icon: 'success',
                            title: '{{ __("app.game.login_complete") }}'
                        });
                    }

                    // ✅ รอ 500ms แล้ว redirect ไปหน้าเกม
                    setTimeout(() => {
                        window.location.href = url;
                    }, 500);

                } else {
                    // ✅ เดสก์ท็อปเปิด popup
                    const newWindow = window.open(url, 'gametechPopup', 'width=800,height=400,screenX=200,screenY=200');

                    if (!newWindow) {
                        if (window.Toast && window.Toast.fire) {
                            window.Toast.fire({
                                icon: 'error',
                                title: 'Popup ถูกบล็อก กรุณาอนุญาต popup ในเบราว์เซอร์ของคุณ'
                            });
                        }
                    } else {
                        window.Toast.fire({
                            icon: 'success',
                            title: '{{ __("app.game.login_complete") }}'
                        });
                    }
                }
            });
        });


        $(document).ready(function () {

            // console.log(previousURL);

            $('[data-search]').on('keyup', function () {
                var searchVal = $(this).val();
                var filterItems = $('[data-filter-item]');

                if (searchVal !== '') {
                    filterItems.addClass('hidden');
                    $('[data-filter-item][data-filter-name*="' + searchVal.toLowerCase() + '"]').removeClass('hidden');
                } else {
                    filterItems.removeClass('hidden');
                }
            });
        });
		
		{{--var isMobile = false;--}}
		{{--var windowObjectReference = null; // global variable--}}
		{{--var PreviousUrl = ''; /* global variable that will store the--}}
		{{--            url currently in the secondary window */--}}
		
		
		{{--function openPopup(url) {--}}
		
		{{--Toast.fire({--}}
		{{--    icon: 'info',--}}
		{{--    title: '{{ __('app.game.login') }}'--}}
		{{--})--}}
		
		{{--    const w = 900;--}}
		{{--    const h = 500;--}}
		{{--    const y = window.top.outerHeight / 2 + window.top.screenY - (h / 2);--}}
		{{--    const x = window.top.outerWidth / 2 + window.top.screenX - (w / 2);--}}
		{{--    PreviousUrl = url;--}}
		{{--    console.log(windowObjectReference);--}}
		
		
		{{--    if (windowObjectReference == null || windowObjectReference.closed) {--}}
		
		{{--        if (/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|ipad|iris|kindle|Android|Silk|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i.test(navigator.userAgent)--}}
		{{--            || /1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i.test(navigator.userAgent.substr(0, 4))) {--}}
		{{--            // alert('mobile');--}}
		{{--            windowObjectReference = window.open(PreviousUrl, '_blank');--}}
		{{--            windowObjectReference.focus();--}}
		
		{{--        } else {--}}
		{{--            // alert('pc');--}}
		{{--            windowObjectReference = window.open(PreviousUrl, "gametech", `toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=no, resizable=no, copyhistory=no, width=${w}, height=${h}, top=${y}, left=${x}`);--}}
		{{--            windowObjectReference.focus();--}}
		{{--        }--}}
		{{--    } else {--}}
		{{--        windowObjectReference.location.href = PreviousUrl;--}}
		{{--        windowObjectReference.focus();--}}
		
		{{--    }--}}
		
		{{--}--}}
		
		{{--const link = document.querySelector("a[target='gametechPopup']");--}}
		
		
		{{--link.addEventListener(--}}
		{{--    "click",--}}
		{{--    (event) => {--}}
		{{--        openPopup(link.href);--}}
		{{--        event.preventDefault();--}}
		{{--    },--}}
		{{--    false--}}
		{{--);--}}
		{{--        {{ dd($refill) }}--}}
		@if($refill)
        $(document).ready(function () {

            Swal.fire({
                title: "{{ __('app.promotion.can') }}",
                html: "{{ __('app.promotion.word') }} {{ $refill->value }} {!!  __('app.promotion.word2') !!}",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: '{{ __('app.promotion.yes') }}',
                cancelButtonText: '{{ __('app.promotion.no') }}',
                allowOutsideClick: false,
                allowEscapeKey: false,
                allowEnterKey: false
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '{{ route('customer.promotion.index') }}';
                } else {
                    axios.post(`{{ route('customer.promotion.cancel') }}`).then(response => {
                        if (response.data.success) {
                            Toast.fire({
                                icon: 'warning',
                                title: '{{ __('app.promotion.no2') }}'
                            })
                        }
                    }).catch(err => [err]);

                }
            })

        });
		@endif
	</script>
@endpush

