@foreach($games as $i => $game)
    <div class="card card-trans">
        <div class="card-body">
            <h5 class="content-heading">{{ ucfirst($i) }}</h5>
            <div class="x-lotto-category x-provider-category -provider_{{ strtolower($i) }}">
                <div class="container-fluid">
                    <div class="-lotto-category-wrapper" data-animatable="fadeInUp" data-delay="150">
                        <ul class="navbar-nav">
                            @foreach($games[$i] as $k => $item)

                                <li class="nav-item -lotto-card-item">
                                    <a href="{{ route('customer.game.list', ['id' => Str::lower($item->id)]) }}"
                                            class="x-game-list-item-macro-in-share js-game-list-toggle -big-with-countdown-dark -cannot-entry -untestable -use-promotion-alert"
                                            data-status="-cannot-entry -untestable">
                                        <div class="-inner-wrapper">


                                            <picture>
                                                <source type="image/webp"
                                                        data-srcset="{{ Storage::url('game_img/' . $item->filepic).'?'.microtime() }}"/>
                                                <source type="image/png"
                                                        data-srcset="{{ Storage::url('game_img/' . $item->filepic).'?'.microtime() }}"/>
                                                <img
                                                        loading="lazy"
                                                        alt="smm-pg-soft cover image png"
                                                        class="img-fluid lazyload -cover-img image-hover"
                                                        width="400"
                                                        height="580"
                                                        data-src="{{ Storage::url('game_img/' . $item->filepic).'?'.microtime() }}"
                                                        src="{{ Storage::url('game_img/' . $item->filepic).'?'.microtime() }}"
                                                />
                                            </picture>


                                        </div>
                                        <div class="-title">{{$item['name']}}</div>
                                    </a>


                                </li>

                                {{--                    <div class="col-4 mb-4 col-md-3">--}}
                                {{--                        <a class="btn btn-link p-0 mx-auto" href="{{ route('customer.game.list', ['id' => Str::lower($item->id)]) }}">--}}
                                {{--                        <img--}}
                                {{--                            loading="lazy"--}}
                                {{--                            alt="{{ $item->name }}"--}}
                                {{--                            src="{{ Storage::url('game_img/' . $item->filepic).'?'.microtime() }}"--}}
                                {{--                            data-src="{{ Storage::url('game_img/' . $item->filepic).'?'.microtime() }}"--}}
                                {{--                            class="d-block mx-auto rounded-circle transfer-slide-img h-90 w-90"/>--}}
                                {{--                        <p class="text-main text-center mb-0 cut-text text-small">{{ $item->name }}</p>--}}
                                {{--                        <p class="mb-0"></p>--}}
                                {{--                        </a>--}}
                                {{--                    </div>--}}
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endforeach
