{{-- extend layout --}}
@extends('wallet::layouts.cont')

{{-- page title --}}
@section('title','')



@section('content')
    <div id="main__content" data-bgset="/assets/wm356/images/index-bg.jpg?v=2"
         class="lazyload x-bg-position-center x-bg-index lazyload">

        <div class="x-index-content-main-container -anon">


            @include('wallet::customer.sessions.normal')


        </div>


    </div>


{{--    <div class="x-modal modal -v2 -with-half-size" id="loginModal" tabindex="-1" role="dialog" aria-hidden="true"--}}
{{--         data-loading-container=".js-modal-content" data-ajax-modal-always-reload="true">--}}
{{--        <div--}}
{{--            class="modal-dialog -modal-size -v2 modal-dialog-centered modal-dialog-scrollable -dialog-in-tab -register-index-dialog"--}}
{{--            role="document">--}}
{{--            <div class="modal-content -modal-content">--}}
{{--                <button type="button" class="close f-1 -in-tab" data-dismiss="modal" aria-label="Close">--}}
{{--                    <i class="fas fa-times"></i>--}}
{{--                </button>--}}

{{--                <div class="x-modal-account-security-tabs js-modal-account-security-tabs -v3">--}}


{{--                    <button type="button" class="-btn -login js-modal-account-security-tab-button -active"--}}
{{--                            data-modal-target="#loginModal">--}}
{{--                        เข้าสู่ระบบ--}}
{{--                    </button>--}}
{{--                </div>--}}

{{--                <div class="modal-body -modal-body">--}}
{{--                    <div class="x-register-tab-container -login js-tab-pane-checker-v3">--}}


{{--                        <div class="tab-content">--}}
{{--                            <div class="tab-pane active" id="tab-content-loginPhoneNumber"--}}
{{--                                 data-completed-dismiss-modal="">--}}
{{--                                <div class="x-modal-body-base -v3 -phone-number x-form-register-v3">--}}
{{--                                    <div class="row -register-container-wrapper">--}}
{{--                                        <div class="col">--}}
{{--                                            <div class="x-title-register-modal-v3">--}}
{{--                                                <span class="-title">กรอก Username</span>--}}
{{--                                                <span--}}
{{--                                                    class="-sub-title">Username เพื่อใช้ในการเข้าระบบ</span>--}}
{{--                                            </div>--}}
{{--                                        </div>--}}

{{--                                        <div class="col">--}}
{{--                                            <div class="-fake-inner-body">--}}
{{--                                                --}}{{--                                                    <form method="post" data-register-v3-form="v3/check-for-login"--}}
{{--                                                --}}{{--                                                          data-register-step="loginPhoneNumber">--}}
{{--                                                <form method="POST"--}}
{{--                                                      action="{{ route('customer.session.create') }}"--}}
{{--                                                      @submit.prevent="onSubmit">--}}
{{--                                                    @csrf--}}
{{--                                                    <div--}}
{{--                                                        class="-animatable-container -password-body">--}}
{{--                                                        <input--}}
{{--                                                            type="text"--}}
{{--                                                            required--}}
{{--                                                            autocomplete="off"--}}
{{--                                                            id="user_name"--}}
{{--                                                            name="user_name"--}}
{{--                                                            inputmode="text"--}}
{{--                                                            placeholder=""--}}
{{--                                                            class="form-control x-form-control"--}}
{{--                                                        />--}}
{{--                                                    </div>--}}
{{--                                                    <div class="-x-input-icon flex-column">--}}
{{--                                                        <input type="password" id="password" name="password"--}}
{{--                                                               required--}}
{{--                                                               class="form-control x-form-control"--}}
{{--                                                               placeholder="XXXXXXXX"/>--}}
{{--                                                    </div>--}}


{{--                                                    <div class="text-center">--}}
{{--                                                        <button--}}
{{--                                                            class="btn -submit btn-primary mt-lg-3 mt-0">--}}
{{--                                                            ยืนยัน--}}
{{--                                                        </button>--}}
{{--                                                    </div>--}}
{{--                                                </form>--}}
{{--                                            </div>--}}
{{--                                        </div>--}}
{{--                                    </div>--}}
{{--                                </div>--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                    </div>--}}
{{--                </div>--}}
{{--            </div>--}}
{{--        </div>--}}
{{--    </div>--}}
{{--    <div class="x-modal modal -v2 x-theme-switcher-v2" id="themeSwitcherModal" tabindex="-1" role="dialog"--}}
{{--         aria-hidden="true" data-loading-container=".js-modal-content" data-ajax-modal-always-reload="true">--}}
{{--        <div class="modal-dialog -modal-size -v2 modal-dialog-centered modal-dialog-scrollable modal-dialog-centered"--}}
{{--             role="document">--}}
{{--            <div class="modal-content -modal-content">--}}
{{--                <button type="button" class="close f-1" data-dismiss="modal" aria-label="Close">--}}
{{--                    <i class="fas fa-times"></i>--}}
{{--                </button>--}}
{{--                <div class="modal-body -modal-body">--}}
{{--                    <div class="-theme-switcher-container">--}}
{{--                        <div class="-inner-header-section">--}}
{{--                            <a class="-link-wrapper" href="{{ route('customer.home.index') }}">--}}
{{--                                <picture>--}}
{{--                                    <source type="image/webp"--}}
{{--                                            data-srcset="{{ url(core()->imgurl($config->logo,'img')) }}"/>--}}
{{--                                    <source type="image/png?v=2"--}}
{{--                                            data-srcset="{{ url(core()->imgurl($config->logo,'img')) }}"/>--}}
{{--                                    <img--}}
{{--                                        alt="logo image"--}}
{{--                                        class="img-fluid lazyload -logo lazyload"--}}
{{--                                        width="180"--}}
{{--                                        height="42"--}}
{{--                                        data-src="{{ url(core()->imgurl($config->logo,'img')) }}"--}}
{{--                                        src="{{ url(core()->imgurl($config->logo,'img')) }}"--}}
{{--                                    />--}}
{{--                                </picture>--}}
{{--                            </a>--}}
{{--                        </div>--}}

{{--                        <div class="-inner-top-body-section">--}}
{{--                            <div class="col-6 -wrapper-box">--}}
{{--                                <a--}}
{{--                                    class="btn -btn-item -top-btn -register-button lazyload x-bg-position-center"--}}
{{--                                    href="{{ route('customer.session.store') }}"--}}
{{--                                    data-bgset="/assets/wm356/images/btn-register-login-bg.png?v=2"--}}
{{--                                    style="background-image: url('/assets/wm356/images/btn-register-login-bg.png?v=2');"--}}
{{--                                >--}}
{{--                                    <picture>--}}
{{--                                        <source type="image/webp"--}}
{{--                                                data-srcset="/assets/wm356/images/ic-modal-menu-register.webp?v=2"/>--}}
{{--                                        <source type="image/png?v=2"--}}
{{--                                                data-srcset="/assets/wm356/images/ic-modal-menu-register.png?v=2"/>--}}
{{--                                        <img--}}
{{--                                            alt="รูปไอคอนสมัครสมาชิก"--}}
{{--                                            class="img-fluid -icon-image lazyload"--}}
{{--                                            width="50"--}}
{{--                                            height="50"--}}
{{--                                            data-src="/assets/wm356/images/ic-modal-menu-register.png?v=2"--}}
{{--                                            src="/assets/wm356/images/ic-modal-menu-register.png?v=2"--}}
{{--                                        />--}}
{{--                                    </picture>--}}

{{--                                    <div class="-typo-wrapper">--}}
{{--                                        <div class="-typo">สมัครเลย</div>--}}
{{--                                    </div>--}}
{{--                                </a>--}}
{{--                            </div>--}}
{{--                            <div class="col-6 -wrapper-box">--}}
{{--                                <button--}}
{{--                                    type="button"--}}
{{--                                    class="btn -btn-item -top-btn -login-btn lazyload x-bg-position-center"--}}
{{--                                    data-toggle="modal"--}}
{{--                                    data-dismiss="modal"--}}
{{--                                    data-target="#loginModal"--}}
{{--                                    data-bgset="/assets/wm356/images/btn-register-login-bg.png?v=2"--}}
{{--                                    style="background-image: url('/assets/wm356/images/btn-register-login-bg.png?v=2');"--}}
{{--                                >--}}
{{--                                    <picture>--}}
{{--                                        <source type="image/webp"--}}
{{--                                                data-srcset="/assets/wm356/images/ic-modal-menu-login.webp?v=2"--}}
{{--                                                srcset="/assets/wm356/images/ic-modal-menu-login.webp?v=2">--}}
{{--                                        <source type="image/png?v=2"--}}
{{--                                                data-srcset="/assets/wm356/images/ic-modal-menu-login.png?v=2"--}}
{{--                                                srcset="/assets/wm356/images/ic-modal-menu-login.png?v=2">--}}
{{--                                        <img alt="รูปไอคอนเข้าสู่ระบบ" class="img-fluid -icon-image lazyloaded"--}}
{{--                                             width="50" height="50"--}}
{{--                                             data-src="/assets/wm356/images/ic-modal-menu-login.png?v=2"--}}
{{--                                             src="/assets/wm356/images/ic-modal-menu-login.png?v=2">--}}
{{--                                    </picture>--}}

{{--                                    <div class="-typo-wrapper">--}}
{{--                                        <div class="-typo">เข้าสู่ระบบ</div>--}}
{{--                                    </div>--}}
{{--                                </button>--}}
{{--                            </div>--}}
{{--                        </div>--}}

{{--                        <div class="-inner-center-body-section">--}}
{{--                            <div class="col-6 -wrapper-box">--}}
{{--                                <a--}}
{{--                                    href="{{ route('customer.promotion.show') }}"--}}
{{--                                    class="btn -btn-item -promotion-button -menu-center -horizontal lazyload x-bg-position-center"--}}
{{--                                    data-bgset="/assets/wm356/images/btn-register-login-bg.png"--}}
{{--                                >--}}
{{--                                    <picture>--}}
{{--                                        <source type="image/webp"--}}
{{--                                                data-srcset="/assets/wm356/images/ic-modal-menu-promotion.webp?v=2"/>--}}
{{--                                        <source type="image/png?v=2"--}}
{{--                                                data-srcset="/assets/wm356/images/ic-modal-menu-promotion.png?v=2"/>--}}
{{--                                        <img--}}
{{--                                            alt="รูปไอคอนโปรโมชั่น"--}}
{{--                                            class="img-fluid -icon-image lazyload"--}}
{{--                                            width="65"--}}
{{--                                            height="53"--}}
{{--                                            data-src="/assets/wm356/images/ic-modal-menu-promotion.png?v=2"--}}
{{--                                            src="/assets/wm356/images/ic-modal-menu-promotion.png?v=2"--}}
{{--                                        />--}}
{{--                                    </picture>--}}

{{--                                    <div class="-typo-wrapper">--}}
{{--                                        <div class="-typo">โปรโมชั่น</div>--}}
{{--                                    </div>--}}
{{--                                </a>--}}
{{--                            </div>--}}
{{--                            <div class="col-6 -wrapper-box">--}}
{{--                                <a--}}
{{--                                    href="https://lin.ee/BpAUj1s"--}}
{{--                                    class="btn -btn-item -line-button -menu-center -horizontal lazyload x-bg-position-center"--}}
{{--                                    target="_blank"--}}
{{--                                    rel="noopener nofollow"--}}
{{--                                    data-bgset="/assets/wm356/images/btn-register-login-bg.png"--}}
{{--                                >--}}
{{--                                    <picture>--}}
{{--                                        <source type="image/webp"--}}
{{--                                                data-srcset="https://asset.cloudigame.co/build/admin/img/wt_theme/ezl/ic-modal-menu-line.webp?v=2"/>--}}
{{--                                        <source type="image/png"--}}
{{--                                                data-srcset="https://asset.cloudigame.co/build/admin/img/wt_theme/ezl/ic-modal-menu-line.png"/>--}}
{{--                                        <img--}}
{{--                                            alt="รูปไอคอนดูหนัง"--}}
{{--                                            class="img-fluid -icon-image lazyload"--}}
{{--                                            width="65"--}}
{{--                                            height="53"--}}
{{--                                            data-src="https://asset.cloudigame.co/build/admin/img/wt_theme/ezl/ic-modal-menu-line.png"--}}
{{--                                            src="https://asset.cloudigame.co/build/admin/img/wt_theme/ezl/ic-modal-menu-line.png"--}}
{{--                                        />--}}
{{--                                    </picture>--}}

{{--                                    <div class="-typo-wrapper">--}}
{{--                                        <div class="-typo">ไลน์</div>--}}
{{--                                    </div>--}}
{{--                                </a>--}}
{{--                            </div>--}}
{{--                        </div>--}}

{{--                        <div class="-inner-bottom-body-section"></div>--}}
{{--                    </div>--}}
{{--                </div>--}}
{{--            </div>--}}
{{--        </div>--}}
{{--    </div>--}}
{{--    --}}
@endsection

@push('scripts')
    <script src="{{ asset('vendor/inputmask/jquery.inputmask.js') }}"></script>
    <script type="text/javascript">
        function markmobile() {
            $('#tel').inputmask({
                alias: 'tel',
                mask: "(999)-999-9999",
                removeMaskOnSubmit: true,
                autoUnmask: true,
                clearIncomplete: true,
                clearMaskOnLostFocus: true
            });
        }


        $(document).ready(function () {
            markmobile();

            $('#bank').on('change', function () {
                if ($('#bank option:selected').val() == '18') {
                    // $('#acc_no_tw').prop('required',true);
                    $('.acc_no_tw').css('display', 'block');
                    $('.tw').css('display', 'block');
                } else {
                    // $('#acc_no_tw').prop('required',false);
                    $('.acc_no_tw').css('display', 'none');
                    $('.tw').css('display', 'none');
                }
            });
        });

    </script>
@endpush

