<div id="main__content" data-bgset="/assets/wm356/images/index-bg.jpg?v=2"
     class="lazyload x-bg-position-center x-bg-index lazyload">
    <div class="js-replace-cover-seo-container">
        <div class="x-homepage-banner-container">
            <div
                    data-slickable='{"arrows":false,"dots":true,"slidesToShow":1,"centerMode":true,"infinite":true,"autoplay":true,"autoplaySpeed":4000,"pauseOnHover":false,"focusOnSelect":true,"variableWidth":true,"responsive":{"sm":{"fade":true,"variableWidth":false}}}'
                    class="x-banner-slide-wrapper -single"
                    data-animatable="fadeInUp"
                    data-delay="200"
            >
                @if(count($slides) == 1)
                    @foreach($slides as $i => $item)
                        <div class="-slide-inner-wrapper -slick-item">
                            <div class="-link-wrapper">
                                <picture>
                                    <source type="image/webp"
                                            srcset="{{  Storage::url('slide_img/'.$item->filepic)  }}"/>
                                    <source type="image/jpg"
                                            srcset="{{  Storage::url('slide_img/'.$item->filepic)  }}"/>
                                    <img class="img-fluid -slick-item -item-{{ $i+1 }}" alt="banner-{{ $i+1 }}"
                                         width="1200"
                                         height="590"
                                         src="{{  Storage::url('slide_img/'.$item->filepic)  }}"/>
                                </picture>
                            </div>
                        </div>
                    @endforeach
                    @foreach($slides as $i => $item)
                        <div class="-slide-inner-wrapper -slick-item">
                            <div class="-link-wrapper">
                                <picture>
                                    <source type="image/webp"
                                            srcset="{{  Storage::url('slide_img/'.$item->filepic)  }}"/>
                                    <source type="image/jpg"
                                            srcset="{{  Storage::url('slide_img/'.$item->filepic)  }}"/>
                                    <img class="img-fluid -slick-item -item-{{ $i+1 }}" alt="banner-{{ $i+1 }}"
                                         width="1200"
                                         height="590"
                                         src="{{  Storage::url('slide_img/'.$item->filepic)  }}"/>
                                </picture>
                            </div>
                        </div>
                    @endforeach
                @else
                    @foreach($slides as $i => $item)
                        <div class="-slide-inner-wrapper -slick-item">
                            <div class="-link-wrapper">
                                <picture>
                                    <source type="image/webp"
                                            srcset="{{  Storage::url('slide_img/'.$item->filepic)  }}"/>
                                    <source type="image/jpg"
                                            srcset="{{  Storage::url('slide_img/'.$item->filepic)  }}"/>
                                    <img class="img-fluid -slick-item -item-{{ $i+1 }}" alt="banner-{{ $i+1 }}"
                                         width="1200"
                                         height="590"
                                         src="{{  Storage::url('slide_img/'.$item->filepic)  }}"/>
                                </picture>
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
    </div>

    <div class="x-index-content-main-container -logged">
        <div class="x-quick-transaction-buttons js-quick-transaction-buttons">
            <a class="btn -btn -promotion -vertical" href="{{ route('customer.promotion.index') }}" target="_blank"
               rel="noopener nofollow">
                <span class="-ic-wrapper"> <img alt="โปรโมชั่นสุดคุ้ม เพื่อลูกค้าคนสำคัญ" class="img-fluid -ic"
                                                width="40" height="40"
                                                src="/assets/wm356/images/ic-quick-transaction-button-promotion.png?v=2"/></span>

                <span class="-btn-inner-content">
            <span class="-btn-inner-content-title">{{ __('app.home.promotion') }}</span>
        </span>
            </a>

            <button
                    class="btn -btn -deposit x-bg-position-center lazyloaded"
                    data-toggle="modal"
                    data-target="#depositModal"
                    data-bgset="build/images/btn-deposit-bg.png?v=2"
                    style="background-image: url('/assets/wm356/images/btn-deposit-bg.png?v=2');"
            >
                <span class="-ic-wrapper"> <img alt="ฝากเงินง่ายๆ ด้วยระบบออโต้ การันตี 1 นาที" class="img-fluid -ic"
                                                width="40" height="40"
                                                src="https://asset.cloudigame.co/build/admin/img/wt_theme/ezl/ic-account-deposit.png"/></span>

                <span class="-btn-inner-content">
            <span class="-btn-inner-content-title">{{ __('app.home.refill') }}</span>
        </span>
            </button>

            <button
                    class="btn -btn -withdraw x-bg-position-center lazyloaded"
                    data-toggle="modal"
                    data-target="#withdrawModal"
                    data-bgset="build/images/btn-withdraw-bg.png?v=2"
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


        <div class="x-title-with-tag-header" data-animatable="fadeInUp" data-delay="150">
            <div class="container">
                <h1 class="-title">{{ $config->content_header }}</h1>
            </div>
        </div>

        <div class="x-category-total-game -v2">
            <div class="container-fluid">
                <nav class="nav-menu" id="navbarCategory">
                    <ul class="-menu-parent navbar-nav flex-row nav nav-tabs" id="myTab" role="tablist">
                        @foreach($games as $i => $items)
                            <li class="-list-parent nav-item px-lg-2 -category-{{ $i }}" data-animatable="fadeInUp"
                                data-delay="100">
                                <a href="{{ route('customer.cats.list', ['id' => $i]) }}"
                                   class="x-category-button -category-{{ $i }} -index-page -category-button-v2 -hoverable"
                                   id="{{ $i }}-tab" data-toggle="tab" data-target="#{{ $i }}" type="button" role="tab"
                                >
                                    <img alt="category {{ $i }} image png" class="-img -default" width="300" height="82"
                                         src="\assets\wm356\web\ezl-wm-356\img\menu-category-casino.png?v=2"/>

                                    <img alt="category {{ $i }} image png" class="-img -hover" width="300" height="82"
                                         src="\assets\wm356\web\ezl-wm-356\img\menu-category-casino-hover.png?v=2"/>

                                    <span class="-menu-text-main -text-btn-image">
                                            <div class="-menu-text-wrapper">
                                                <span class="-text-desktop">{{ $i }}</span>
                                                <span class="-text-mobile">{{ $i }}</span>
                                            </div>
                                        </span>
                                </a>
                            </li>
                        @endforeach

                    </ul>
                </nav>
            </div>
        </div>


        <div class="x-lotto-category x-provider-category -provider_slots">
            <div class="container-fluid">
                <div class="tab-content" id="myTabContent">
                    @foreach($games as $i => $items)
                        <div class="tab-pane fade" id="{{ $i }}" role="tabpanel" aria-labelledby="{{ $i }}-tab">
                            <div class="-lotto-category-wrapper" data-animatable="fadeInUp" data-delay="150">
                                <ul class="navbar-nav">

                                    @foreach($games[$i] as $item)
                                        <li class="nav-item -lotto-card-item">
                                            <div
                                                    class="x-game-list-item-macro-in-share js-game-list-toggle -big-with-countdown-dark -cannot-entry -untestable -use-promotion-alert"
                                                    data-status="-cannot-entry -untestable">
                                                <div class="-inner-wrapper">


                                                    <picture>
                                                        <source type="image/webp"
                                                                data-srcset="{{ $item['filepic'] }}"/>
                                                        <source type="image/png"
                                                                data-srcset="{{ $item['filepic'] }}"/>
                                                        <img
                                                                alt="smm-pg-soft cover image png"
                                                                class="img-fluid lazyload -cover-img"
                                                                width="400"
                                                                data-src="{{ $item['filepic'] }}"
                                                                src="https://asset.cloudigame.co/build/admin/img/ezs-default-loading-big.png"
                                                        />
                                                    </picture>

                                                    <div class="-overlay">
                                                        <div class="-overlay-inner">
                                                            <div class="-wrapper-container">
                                                                <a
                                                                        href="{{ route('customer.game.list', ['id' => Str::lower($item['id']) ]) }}"
                                                                        class="-btn -btn-play">
                                                                    <i class="fas fa-play"></i>
                                                                    <span class="-text-btn">{{ __('app.home.join') }}</span>
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="-title"><small>{{$item['name']}}</small></div>
                                            </div>


                                        </li>
                                    @endforeach

                                </ul>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>


    </div>
</div>
