@extends('wallet::layouts.master')

{{-- page title --}}
@section('title','')

@section('back')
    <a class="nav-link p-2 text-light mx-auto hand-point" href="{{ route('customer.home.index') }}">
        <i class="fas fa-chevron-left"></i> กลับ</a>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('vendor/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css') }}">
    <style>
        .section {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .img-select {
            border: 2px solid #fff;
            border-radius: 10%;
            background-color: #ffffff3a;
        }

        .img-bank {
            width: 40px;
            height: 40px;
        }

        .hidden {
            display: none;
        }

        a {
            color: #007bff;
            text-decoration: none;
            background-color: transparent;
        }
    </style>
@endpush

@section('content')
    <div class="container text-light mt-5">
        <h3 class="text-center text-light">ฝากเงิน</h3>
        <p class="text-center text-color-fixed">Deposit</p>
        <div class="row">
            <div class="col-md-8 offset-md-2 col-sm-12">
                <div class="card card-trans">
                    <div class="card-body">
                        <div class="row m-0 mt-4">
                            <div class="col-2 p-0 leftdps">
                                <div class="nav flex-column nav-pills" id="v-pills-tab" role="tablist"
                                     aria-orientation="vertical">
                                    <a class="nav-link active" id="v-pills-home-tab" data-toggle="pill"
                                       href="#v-pills-home" role="tab" aria-controls="v-pills-home"
                                       aria-selected="true"><img class="banktabicon"
                                                                 src="/images/icon/04.png?v=2"> {{ __('app.profile.bank') }}
                                    </a>
                                    <a class="nav-link" id="v-pills-profile-tab" data-toggle="pill"
                                       href="#v-pills-profile" role="tab" aria-controls="v-pills-profile"
                                       aria-selected="false"><img class="banktabicon"
                                                                  src="/images/bank/truewallet.svg?v=1"> TrueWallet</a>
                                </div>
                            </div>
                            <div class="col-10 p-0">
                                <div class="tab-content" id="v-pills-tabContent">
                                    <div class="tab-pane fade active show" id="v-pills-home" role="tabpanel"
                                         aria-labelledby="v-pills-home-tab">
                                        <div class="griddps">
                                            {{--                                            {{ dd($banks) }}--}}
                                            @foreach($banks as $bank)
                                                @if($bank['shortcode'] == 'TW')
                                                    @continue
                                                @endif

                                                @foreach($bank['banks_account'] as $item)
                                                    <div class="ingriddps">
                                                        <div class="iningriddps copybtn">
                                                            <img src="{{ $bank['filepic'] }}">
                                                            <div>
                                                                {{ __('app.profile.bank') }} {{ $bank['name_th'] }} <br>
                                                                <span>{{ $item['acc_no'] }}</span> <br>
                                                                {{ $item['acc_name'] }} <br>
                                                                <button onclick="copylink()"><i
                                                                            class="fad fa-copy"></i> {{ __('app.con.copy') }}
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            @endforeach

                                        </div>
                                    </div>
                                    <div class="tab-pane fade" id="v-pills-profile" role="tabpanel"
                                         aria-labelledby="v-pills-profile-tab">
                                        <div class="griddps">

                                            @foreach($banks as $bank)
                                                @if($bank['shortcode'] != 'TW')
                                                    @continue
                                                @endif

                                                @foreach($bank['banks_account'] as $item)
                                                    <div class="ingriddps">
                                                        <div class="iningriddps copybtn">
                                                            <img src="{{ $bank['filepic'] }}">
                                                            <div>
                                                                {{ $bank['name_th'] }} <br>
                                                                <span>{{ $item['acc_no'] }}</span> <br>
                                                                {{ $item['acc_name'] }} <br>
                                                                <button onclick="copylink()"><i
                                                                            class="fad fa-copy"></i> {{ __('app.con.copy') }}
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            @endforeach

                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modalspanbox mt-3">
                            <span>โอนเงินจากบัญชีที่ลงทะเบียนไว้เท่านั้น</span>
                        </div>
                    </div>
                </div>


            </div>
        </div>
    </div>

@endsection





