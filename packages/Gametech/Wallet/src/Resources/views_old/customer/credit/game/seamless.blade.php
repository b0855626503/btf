@foreach($games as $i => $game)
    <div class="card card-trans">
        <div class="card-body">
            <h5 class="content-heading">{{ ucfirst($i) }} (ฟรีเครดิต)</h5>
            <div class="row text-center">
                <div class="x-lotto-category x-provider-category -provider_casinos">
                    <div class="container-fluid">
                        <div class="-lotto-category-wrapper" data-animatable="fadeInUp" data-delay="150">
                            <ul class="navbar-nav">
                @foreach($games[$i] as $k => $item)

                                    <li class="nav-item -lotto-card-item">
                                        <div
                                                class="x-game-list-item-macro-in-share js-game-list-toggle -big-with-countdown-dark -cannot-entry -untestable -use-promotion-alert"
                                                data-status="-cannot-entry -untestable">
                                            <div class="-inner-wrapper">


                                                <picture>
                                                    <source type="image/webp"
                                                            data-srcset="{{ $item->filepic }}"/>
                                                    <source type="image/png"
                                                            data-srcset="{{ $item->filepic }}"/>
                                                    <img
                                                            loading="lazy"
                                                            alt="smm-pg-soft cover image png"
                                                            class="img-fluid lazyload -cover-img"
                                                            width="400"
                                                            height="580"
                                                            data-src="{{ $item->filepic }}"
                                                            src="{{ $item->filepic }}"
                                                    />
                                                </picture>

                                                <div class="-overlay">
                                                    <div class="-overlay-inner">
                                                        <div class="-wrapper-container">
                                                            <a
                                                                    href="{{ route('customer.credit.game.list', ['id' => Str::lower($item->id)]) }}"
                                                                    class="-btn -btn-play">
                                                                <i class="fas fa-play"></i>
                                                                <span class="-text-btn">{{ __('app.home.join') }}</span>
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="-title">{{$item['name']}}</div>
                                        </div>


                                    </li>


                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endforeach
