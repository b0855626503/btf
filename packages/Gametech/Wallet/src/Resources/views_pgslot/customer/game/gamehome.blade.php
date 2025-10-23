<div class="gameborder">
    <div class="headertab"><h2>เลือกเล่นเกม</h2></div>
    <div class="row m-0 mt-3">
        <div class="col-12 p-0 px-1">
            <ul class="customgametab nav nav-pills mb-3 text-center " id="pills-tab" role="tablist">
{{--               {{ dd($games) }}--}}
                @foreach($games as $i => $item)

                    <li class="nav-item" role="presentation">
                        <a class="nav-link" id="pills-{{ $i }}-tab" data-toggle="pill" href="#pills-{{ $i }}"
                           role="tab"
                           aria-controls="pills-{{ $i }}" aria-selected="false">
                            <img src="/assets/pgslot/images/icon/icon05.png">
                            {{ $i }}
                        </a>
                    </li>
                @endforeach
{{--                @if(isset($games['SLOT']))--}}
{{--                    <li class="nav-item" role="presentation">--}}
{{--                        <a class="nav-link active" id="pills-slot-tab" data-toggle="pill" href="#pills-slot"--}}
{{--                           role="tab"--}}
{{--                           aria-controls="pills-slot" aria-selected="false">--}}
{{--                            <img src="/assets/pgslot/images/icon/icon05.png">--}}
{{--                            สล็อต--}}
{{--                        </a>--}}
{{--                    </li>--}}
{{--                @endif--}}
{{--                @if(isset($games['SPORT']))--}}
{{--                    <li class="nav-item" role="presentation">--}}
{{--                        <a class="nav-link" id="pills-sport-tab" data-toggle="pill" href="#pills-sport" role="tab"--}}
{{--                           aria-controls="pills-sport" aria-selected="true">--}}
{{--                            <img src="/assets/pgslot/images/icon/icn-sportsbook-check.png">--}}
{{--                            กีฬา--}}
{{--                        </a>--}}
{{--                    </li>--}}
{{--                @endif--}}
{{--                @if(isset($games['CASINO']))--}}
{{--                    <li class="nav-item" role="presentation">--}}
{{--                        <a class="nav-link" id="pills-Casino-tab" data-toggle="pill" href="#pills-Casino" role="tab"--}}
{{--                           aria-controls="pills-Casino" aria-selected="true">--}}
{{--                            <img src="/assets/pgslot/images/icon/icon04.png">--}}
{{--                            คาสิโน--}}
{{--                        </a>--}}
{{--                    </li>--}}
{{--                @endif--}}
                {{--                    <li class="nav-item" role="presentation">--}}
                {{--                        <a class="nav-link" id="pills-card-tab" data-toggle="pill" href="#pills-card" role="tab"--}}
                {{--                           aria-controls="pills-card" aria-selected="false">--}}
                {{--                            <img src="/assets/pgslot/images/icon/icn-card-checked.png">--}}
                {{--                            ไพ่--}}
                {{--                        </a>--}}
                {{--                    </li>--}}
                {{--                    <li class="nav-item" role="presentation">--}}
                {{--                        <a class="nav-link" id="pills-fish-tab" data-toggle="pill" href="#pills-fish" role="tab"--}}
                {{--                           aria-controls="pills-fish" aria-selected="false">--}}
                {{--                            <img src="/assets/pgslot/images/icon/icn-fishing-checked.png">--}}
                {{--                            ยิงปลา--}}
                {{--                        </a>--}}
                {{--                    </li>--}}
                {{--                    <li class="nav-item" role="presentation">--}}
                {{--                        <a class="nav-link" id="pills-lotto-tab" data-toggle="pill" href="#pills-lotto" role="tab"--}}
                {{--                           aria-controls="pills-lotto" aria-selected="false">--}}
                {{--                            <img src="/assets/pgslot/images/icon/icn-lotto-checked.png">--}}
                {{--                            หวย--}}
                {{--                        </a>--}}
                {{--                    </li>--}}

            </ul>
        </div>
        <div class="col-12 p-0 px-md-3">
            <div class="tab-content" id="pills-tabContent">
                @foreach($games as $i => $items)
                    <div class="tab-pane fade" id="pills-{{ $i }}" role="tabpanel"
                         aria-labelledby="pills-{{ $i }}-tab">
                        <div class="gridgame">
                            @foreach($games[$i] as $item)
                                <div class="ingridgame">
                                    <div class="iningridgame">
                                        <a href="{{ route('customer.game.list', ['id' => Str::lower($item['id'])]) }}">
                                            <img src="{{ $item['filepic'] }}">
{{--                                                                                        <img src="/assets/pgslot/images/game/testpic.png">--}}
                                        </a>
                                    </div>
                                </div>
                            @endforeach

                        </div>
                    </div>
                @endforeach

{{--                @if(isset($games['SLOT']))--}}
{{--                    <div class="tab-pane fade show active" id="pills-slot" role="tabpanel"--}}
{{--                         aria-labelledby="pills-slot-tab">--}}
{{--                        <div class="gridgame">--}}
{{--                            @foreach($games['SLOT'] as $k => $item)--}}
{{--                                <div class="ingridgame">--}}
{{--                                    <div class="iningridgame">--}}
{{--                                        <a href="{{ route('customer.game.list', ['id' => Str::lower($item->id)]) }}">--}}
{{--                                            <img src="{{ $item->filepic }}">--}}
{{--                                            <img src="/assets/pgslot/images/game/testpic.png">--}}
{{--                                        </a>--}}
{{--                                    </div>--}}
{{--                                </div>--}}
{{--                            @endforeach--}}

{{--                        </div>--}}
{{--                    </div>--}}
{{--                @endif--}}

{{--                @if(isset($games['SPORT']))--}}
{{--                    <div class="tab-pane fade" id="pills-sport" role="tabpanel" aria-labelledby="pills-sport-tab">--}}
{{--                        <div class="gridgame">--}}
{{--                            @foreach($games['SPORT'] as $k => $item)--}}
{{--                                <div class="ingridgame">--}}
{{--                                    <div class="iningridgame">--}}
{{--                                        <a href="{{ route('customer.game.list', ['id' => Str::lower($item->id)]) }}">--}}
{{--                                            <img src="{{ $item->filepic }}">--}}
{{--                                            <img src="/assets/pgslot/images/game/slot/slot10.png">--}}
{{--                                        </a>--}}
{{--                                    </div>--}}
{{--                                </div>--}}
{{--                            @endforeach--}}
{{--                        </div>--}}
{{--                    </div>--}}
{{--                @endif--}}
{{--                @if(isset($games['CASINO']))--}}
{{--                    <div class="tab-pane fade" id="pills-Casino" role="tabpanel" aria-labelledby="pills-Casino-tab">--}}
{{--                        <div class="gridgame">--}}
{{--                            @foreach($games['CASINO'] as $k => $item)--}}
{{--                                <div class="ingridgame">--}}
{{--                                    <div class="iningridgame">--}}
{{--                                        <a href="{{ route('customer.game.list', ['id' => Str::lower($item->id)]) }}">--}}
{{--                                            <img src="{{ $item->filepic }}">--}}
{{--                                            <img src="/assets/pgslot/images/game/testpic.png">--}}
{{--                                        </a>--}}
{{--                                    </div>--}}
{{--                                </div>--}}
{{--                            @endforeach--}}

{{--                        </div>--}}
{{--                    </div>--}}
{{--                @endif--}}
{{--                <div class="tab-pane fade" id="pills-itpslot" role="tabpanel" aria-labelledby="pills-itpslot-tab">--}}
{{--                    <div class="gridgame">--}}
{{--                        <div class="ingridgame">--}}
{{--                            <div class="iningridgame">--}}
{{--                                <img src="/assets/pgslot/images/game/itpslot/01.png">--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                        <div class="ingridgame">--}}
{{--                            <div class="iningridgame">--}}
{{--                                <img src="/assets/pgslot/images/game/itpslot/02.png">--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                        <div class="ingridgame">--}}
{{--                            <div class="iningridgame">--}}
{{--                                <img src="/assets/pgslot/images/game/itpslot/03.png">--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                        <div class="ingridgame">--}}
{{--                            <div class="iningridgame">--}}
{{--                                <img src="/assets/pgslot/images/game/itpslot/04.png">--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                        <div class="ingridgame">--}}
{{--                            <div class="iningridgame">--}}
{{--                                <img src="/assets/pgslot/images/game/itpslot/05.png">--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                        <div class="ingridgame">--}}
{{--                            <div class="iningridgame">--}}
{{--                                <img src="/assets/pgslot/images/game/itpslot/06.png">--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                        <div class="ingridgame">--}}
{{--                            <div class="iningridgame">--}}
{{--                                <img src="/assets/pgslot/images/game/itpslot/07.png">--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                        <div class="ingridgame">--}}
{{--                            <div class="iningridgame">--}}
{{--                                <img src="/assets/pgslot/images/game/itpslot/08.png">--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                        <div class="ingridgame">--}}
{{--                            <div class="iningridgame">--}}
{{--                                <img src="/assets/pgslot/images/game/itpslot/09.png">--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                        <div class="ingridgame">--}}
{{--                            <div class="iningridgame">--}}
{{--                                <img src="/assets/pgslot/images/game/itpslot/10.png">--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                        <div class="ingridgame">--}}
{{--                            <div class="iningridgame">--}}
{{--                                <img src="/assets/pgslot/images/game/itpslot/11.png">--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                    </div>--}}
{{--                </div>--}}
{{--                <div class="tab-pane fade" id="pills-card" role="tabpanel" aria-labelledby="pills-card-tab">--}}
{{--                    <div class="gridgame">--}}
{{--                        <div class="ingridgame">--}}
{{--                            <div class="iningridgame">--}}
{{--                                <img src="/assets/pgslot/images/game/card/01.png">--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                        <div class="ingridgame">--}}
{{--                            <div class="iningridgame">--}}
{{--                                <img src="/assets/pgslot/images/game/card/02.png">--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                        <div class="ingridgame">--}}
{{--                            <div class="iningridgame">--}}
{{--                                <img src="/assets/pgslot/images/game/card/03.png">--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                        <div class="ingridgame">--}}
{{--                            <div class="iningridgame">--}}
{{--                                <img src="/assets/pgslot/images/game/card/04.png">--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                        <div class="ingridgame">--}}
{{--                            <div class="iningridgame">--}}
{{--                                <img src="/assets/pgslot/images/game/card/05.png">--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                        <div class="ingridgame">--}}
{{--                            <div class="iningridgame">--}}
{{--                                <img src="/assets/pgslot/images/game/card/06.png">--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                        <div class="ingridgame">--}}
{{--                            <div class="iningridgame">--}}
{{--                                <img src="/assets/pgslot/images/game/card/07.png">--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                        <div class="ingridgame">--}}
{{--                            <div class="iningridgame">--}}
{{--                                <img src="/assets/pgslot/images/game/card/08.png">--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                        <div class="ingridgame">--}}
{{--                            <div class="iningridgame">--}}
{{--                                <img src="/assets/pgslot/images/game/card/09.png">--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                        <div class="ingridgame">--}}
{{--                            <div class="iningridgame">--}}
{{--                                <img src="/assets/pgslot/images/game/card/10.png">--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                        <div class="ingridgame">--}}
{{--                            <div class="iningridgame">--}}
{{--                                <img src="/assets/pgslot/images/game/card/11.png">--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                        <div class="ingridgame">--}}
{{--                            <div class="iningridgame">--}}
{{--                                <img src="/assets/pgslot/images/game/card/12.png">--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                        <div class="ingridgame">--}}
{{--                            <div class="iningridgame">--}}
{{--                                <img src="/assets/pgslot/images/game/card/13.png">--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                        <div class="ingridgame">--}}
{{--                            <div class="iningridgame">--}}
{{--                                <img src="/assets/pgslot/images/game/card/14.png">--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                    </div>--}}
{{--                </div>--}}
{{--                <div class="tab-pane fade" id="pills-fish" role="tabpanel" aria-labelledby="pills-fish-tab">--}}
{{--                    <div class="gridgame">--}}
{{--                        <div class="ingridgame">--}}
{{--                            <div class="iningridgame">--}}
{{--                                <img src="/assets/pgslot/images/game/fish/01.png">--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                        <div class="ingridgame">--}}
{{--                            <div class="iningridgame">--}}
{{--                                <img src="/assets/pgslot/images/game/fish/02.png">--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                        <div class="ingridgame">--}}
{{--                            <div class="iningridgame">--}}
{{--                                <img src="/assets/pgslot/images/game/fish/03.png">--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                        <div class="ingridgame">--}}
{{--                            <div class="iningridgame">--}}
{{--                                <img src="/assets/pgslot/images/game/fish/04.png">--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                        <div class="ingridgame">--}}
{{--                            <div class="iningridgame">--}}
{{--                                <img src="/assets/pgslot/images/game/fish/05.png">--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                        <div class="ingridgame">--}}
{{--                            <div class="iningridgame">--}}
{{--                                <img src="/assets/pgslot/images/game/fish/06.png">--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                        <div class="ingridgame">--}}
{{--                            <div class="iningridgame">--}}
{{--                                <img src="/assets/pgslot/images/game/fish/07.png">--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                        <div class="ingridgame">--}}
{{--                            <div class="iningridgame">--}}
{{--                                <img src="/assets/pgslot/images/game/fish/08.png">--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                        <div class="ingridgame">--}}
{{--                            <div class="iningridgame">--}}
{{--                                <img src="/assets/pgslot/images/game/fish/09.png">--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                        <div class="ingridgame">--}}
{{--                            <div class="iningridgame">--}}
{{--                                <img src="/assets/pgslot/images/game/fish/10.png">--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                        <div class="ingridgame">--}}
{{--                            <div class="iningridgame">--}}
{{--                                <img src="/assets/pgslot/images/game/fish/11.png">--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                        <div class="ingridgame">--}}
{{--                            <div class="iningridgame">--}}
{{--                                <img src="/assets/pgslot/images/game/fish/12.png">--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                    </div>--}}
{{--                </div>--}}
{{--                <div class="tab-pane fade" id="pills-lotto" role="tabpanel" aria-labelledby="pills-lotto-tab">--}}
{{--                    <div class="gridgame">--}}
{{--                        <div class="ingridgame third">--}}
{{--                            <div class="iningridgame">--}}
{{--                                <img src="/assets/pgslot/images/game/lotto/01.png">--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                        <div class="ingridgame third">--}}
{{--                            <div class="iningridgame">--}}
{{--                                <img src="/assets/pgslot/images/game/lotto/02.png">--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                        <div class="ingridgame third">--}}
{{--                            <div class="iningridgame">--}}
{{--                                <img src="/assets/pgslot/images/game/lotto/03.png">--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                    </div>--}}
{{--                </div>--}}
{{--                <div class="tab-pane fade" id="pills-esports" role="tabpanel" aria-labelledby="pills-esports-tab">--}}
{{--                    <div class="gridgame">--}}
{{--                        <div class="ingridgame third">--}}
{{--                            <div class="iningridgame">--}}
{{--                                <img src="/assets/pgslot/images/game/esports/01.png">--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                        <div class="ingridgame third">--}}
{{--                            <div class="iningridgame">--}}
{{--                                <img src="/assets/pgslot/images/game/esports/02.png">--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                        <div class="ingridgame third">--}}
{{--                            <div class="iningridgame">--}}
{{--                                <img src="/assets/pgslot/images/game/esports/03.png">--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                        <div class="ingridgame third">--}}
{{--                            <div class="iningridgame">--}}
{{--                                <img src="/assets/pgslot/images/game/esports/04.png">--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                    </div>--}}
{{--                </div>--}}
            </div>
        </div>
    </div>
</div>