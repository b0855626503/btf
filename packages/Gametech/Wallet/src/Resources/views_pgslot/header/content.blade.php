<div id="app">
    <div id="dps" class="tabcontent">
        <div class="headertab"><h2>{{ __('app.home.deposit') }}</h2></div>
        <div class="containdps pb-5 mt-4">
            <div class="row m-0 mt-3">
                <div class="col-12 p-0 leftdps">
                    <div class="nav flexdeposit nav-pills" id="v-pills-tab" role="tablist" aria-orientation="vertical">
                        @if(count($topupbanks) > 0)
                            <a class="nav-link w-100 active" id="v-pills-home-tab" data-toggle="pill" href="#v-pills-home"
                               role="tab" aria-controls="v-pills-home" aria-selected="true"><img class="banktabicon"
                                                                                                 src="/assets/pgslot/images/icon/bankicon.png?v=2">
                                {{ __('app.home.topup_bank') }}</a>
                        @endif
                        @if(count($topuptws) > 0)
                            <a class="nav-link w-100" id="v-pills-profile-tab" data-toggle="pill" href="#v-pills-profile"
                               role="tab" aria-controls="v-pills-profile" aria-selected="false"><img class="banktabicon"
                                                                                                     src="/assets/pgslot/images/bank/truewallet.svg?v=1">
                                {{ __('app.home.topup_wallet') }}</a>
                        @endif
                        @if($config->papayapay == 'Y')
                            <a class="nav-link w-100" id="v-pills-crypto-tab" data-toggle="pill" href="#v-pills-crypto"
                               role="tab" aria-controls="v-pills-crypto" aria-selected="false"><img class="banktabicon"
                                                                                                    src="/assets/pgslot/images/bank/crypto2.png">
                                {{ __('app.home.topup_scan') }}</a>
                        @endif
                    </div>
                </div>
                <div class="col-12 p-0">
                    <div class="tab-content" id="v-pills-tabContent">
                        <div class="tab-pane fade active show" id="v-pills-home" role="tabpanel"
                             aria-labelledby="v-pills-home-tab">
                            <div class="griddps">
                                @foreach($topupbanks as $bank)
                                    @foreach($bank['banks_account'] as $item)
                                        <div class="ingriddps">
                                            <div class="iningriddps copybtn">
                                                <img src="{{ $bank['filepic'] }}"> <br>
                                                {{ $bank['name_th'] }} <br>
                                                <span>{{$item['acc_no'] }}</span> <br>
                                                {{ $item['acc_name'] }} <br>
                                                <button onclick="copylink()"><i class="fad fa-copy"></i> คัดลอก</button>
                                            </div>
                                        </div>
                                    @endforeach
                                @endforeach

                            </div>
                        </div>
                        {{--                    <div class="tab-pane fade" id="v-pills-profile" role="tabpanel"--}}
                        {{--                         aria-labelledby="v-pills-profile-tab">--}}
                        {{--                        <div class="griddps">--}}
                        {{--                            <div class="ingriddps">--}}
                        {{--                                <div class="iningriddps copybtn">--}}
                        {{--                                    <img src="/assets/pgslot/images/bank/truewallet.svg?v=1"> <br>--}}
                        {{--                                    ธนาคารไทยพาณิชย์ <br>--}}
                        {{--                                    <span>123-456-7890</span> <br>--}}
                        {{--                                    ทดสอบ ทดสอบ <br>--}}
                        {{--                                    <button onclick="copylink()"><i class="fad fa-copy"></i> คัดลอก</button>--}}
                        {{--                                </div>--}}
                        {{--                            </div>--}}
                        {{--                            <div class="ingriddps">--}}
                        {{--                                <div class="iningriddps copybtn">--}}
                        {{--                                    <img src="/assets/pgslot/images/bank/truewallet.svg?v=1"> <br>--}}
                        {{--                                    ธนาคารไทยพาณิชย์ <br>--}}
                        {{--                                    <span>123-456-7890</span> <br>--}}
                        {{--                                    ทดสอบ ทดสอบ <br>--}}
                        {{--                                    <button onclick="copylink()"><i class="fad fa-copy"></i> คัดลอก</button>--}}
                        {{--                                </div>--}}
                        {{--                            </div>--}}
                        {{--                        </div>--}}
                        {{--                    </div>--}}
                        <div class="tab-pane fade" id="v-pills-profile" role="tabpanel"
                             aria-labelledby="v-pills-profile-tab">
                            <div class="griddps">
                                @foreach($topuptws as $bank)
                                    @foreach($bank['banks_account'] as $item)
                                        <div class="ingriddps">
                                            <div class="iningriddps copybtn">
                                                <img src="{{ $bank['filepic'] }}"> <br>
                                                {{ $bank['name_th'] }} <br>
                                                <span>{{$item['acc_no'] }}</span> <br>
                                                {{ $item['acc_name'] }} <br>
                                                <button onclick="copylink()"><i class="fad fa-copy"></i> คัดลอก</button>
                                            </div>
                                        </div>
                                    @endforeach
                                @endforeach
                            </div>
                        </div>
                        {{--                    <div class="tab-pane fade" id="v-pills-crypto" role="tabpanel" aria-labelledby="v-pills-crypto-tab">--}}
                        {{--                        <div class="samhokha-withdraw__blog-detail boxcrypto dps01 grid grid-cols-1 md:grid-cols-2 mx-auto relative gap-5 md:w-5/5 md:px-0 fadeInUp animated faster">--}}
                        {{--                            <div class="relative w-full flex flex-col text-white content-start px-2 h-auto bg-bn">--}}
                        {{--                                <h3 class="text-center">ฝากเงินคริปโต</h3>--}}
                        {{--                                <div class="hr1__"></div>--}}
                        {{--                                <p class="text-app_yellow pt-1 px-2 mx-auto md:mx-0 text-center md:text-left">--}}
                        {{--                                    โปรดเลือกโปรโมชั่น</p>--}}
                        {{--                                <div class="form-check">--}}
                        {{--                                    <input class="form-check-input" type="radio" name="rdo_pro" id="rdo_0" value=""--}}
                        {{--                                           checked="">--}}
                        {{--                                    <label class="form-check-label" for="rdo_0">No Promotion</label>--}}
                        {{--                                </div>--}}
                        {{--                                <div class="form-check">--}}
                        {{--                                    <input class="form-check-input" type="radio" name="rdo_pro" id="rdo_2" value="2">--}}
                        {{--                                    <label class="form-check-label" for="rdo_2"> Pro 20 % Turn 20.00 X No More than 500--}}
                        {{--                                        THB </label>--}}
                        {{--                                </div>--}}
                        {{--                                <div class="form-check">--}}
                        {{--                                    <input class="form-check-input" type="radio" name="rdo_pro" id="rdo_3" value="3">--}}
                        {{--                                    <label class="form-check-label" for="rdo_3"> Pro 30 % Turn 30.00 X No More than 500--}}
                        {{--                                        THB </label>--}}
                        {{--                                </div>--}}
                        {{--                                <div class="form-check">--}}
                        {{--                                    <input class="form-check-input" type="radio" name="rdo_pro" id="rdo_4" value="4">--}}
                        {{--                                    <label class="form-check-label" for="rdo_4"> Pro 40 % Turn 40.00 X No More than 500--}}
                        {{--                                        THB </label>--}}
                        {{--                                </div>--}}

                        {{--                            </div>--}}
                        {{--                            <div class="relative w-full flex flex-col text-white content-start px-2 h-auto bg-bn mb-8 md:mb-0">--}}
                        {{--                                <p class="text-app_yellow pt-3 px-2 mx-auto md:mx-0 text-center md:text-left">--}}
                        {{--                                    เลือกเหรียญที่ต้องการฝาก</p>--}}
                        {{--                                <div class="mb-2">--}}
                        {{--                                    <div>--}}
                        {{--                                        <div class="flex-container wrap">--}}
                        {{--                                            <div class="coin-item mb-2" onclick="cryptotab();">--}}
                        {{--                                                <div class="dcoin" adata="BUSD">--}}
                        {{--                                                    <div class="flex-container">--}}
                        {{--                                                        <div class="icon-crypto">--}}
                        {{--                                                            <img src="/assets/pgslot/images/bank/BUSD.png?v=2">--}}
                        {{--                                                        </div>--}}
                        {{--                                                        <div>--}}
                        {{--                                                            <div class="tx-network">--}}
                        {{--                                                                <span>BUSD </span>--}}
                        {{--                                                                <span id="txNetworkBUSD">(BNB Smart Chain (BEP20))</span>--}}
                        {{--                                                            </div>--}}
                        {{--                                                            <div class="tx-exch">--}}
                        {{--                                                                <span>Exchange : </span>--}}
                        {{--                                                                <input type="hidden" name="exval_BUSD" value="34.67">--}}
                        {{--                                                                <span id="txExBUSD">34.67</span>--}}
                        {{--                                                                <span> THB</span>--}}
                        {{--                                                            </div>--}}
                        {{--                                                        </div>--}}
                        {{--                                                    </div>--}}
                        {{--                                                </div>--}}
                        {{--                                            </div>--}}
                        {{--                                            <div class="coin-item mb-2" onclick="cryptotab();">--}}
                        {{--                                                <div class="dcoin" adata="USDT">--}}
                        {{--                                                    <div class="flex-container">--}}
                        {{--                                                        <div class="icon-crypto">--}}
                        {{--                                                            <img src="/assets/pgslot/images/bank/USDT.png?v=2">--}}
                        {{--                                                        </div>--}}
                        {{--                                                        <div>--}}
                        {{--                                                            <div class="tx-network">--}}
                        {{--                                                                <span>USDT </span>--}}
                        {{--                                                                <span id="txNetworkUSDT">(BNB Smart Chain (BEP20))</span>--}}
                        {{--                                                            </div>--}}
                        {{--                                                            <div class="tx-exch">--}}
                        {{--                                                                <span>Exchange : </span>--}}
                        {{--                                                                <input type="hidden" name="exval_USDT" value="34.7">--}}
                        {{--                                                                <span id="txExUSDT">34.70</span>--}}
                        {{--                                                                <span> THB</span>--}}
                        {{--                                                            </div>--}}
                        {{--                                                        </div>--}}
                        {{--                                                    </div>--}}
                        {{--                                                </div>--}}
                        {{--                                            </div>--}}
                        {{--                                        </div>--}}
                        {{--                                    </div>--}}
                        {{--                                </div>--}}
                        {{--                            </div>--}}
                        {{--                        </div>--}}

                        {{--                        <div class="samhokha-withdraw__blog-detail boxcrypto dps02 animate__animated animate__fadeInUp grid grid-cols-1 md:grid-cols-2 mx-auto relative gap-5 md:w-5/5 md:px-0 fadeInUp animated faster">--}}
                        {{--                            <div class="relative w-full flex flex-col text-white content-start px-5 h-auto bg-bn">--}}
                        {{--                                <div class="ht3 text-center">Depositing Crypto</div>--}}
                        {{--                                <div class="box__titwar mb-2">--}}
                        {{--                                    <i class="icon-xl fas fa-bell fa-shake"></i>--}}
                        {{--                                    <span class="">Please transfer to QR code shown below</span>--}}
                        {{--                                </div>--}}
                        {{--                                <div class="flex flex-wrap mb-2">--}}
                        {{--                                    <div class="box__qr">--}}
                        {{--                                        <div class="img_qrcode text-center" id="qrcode"--}}
                        {{--                                             title="0xc38dcc4c1d94a23aceeb22f841ede5bb1dff96f5">--}}
                        {{--                                            <canvas width="150" height="150" style="display: none;"></canvas>--}}
                        {{--                                            <img src="data:image/png;base64,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"--}}
                        {{--                                                 style="display: block;">--}}
                        {{--                                        </div>--}}
                        {{--                                        <div class="tit_reqr text-center mt-2">Please use coins <span--}}
                        {{--                                                    id="spCoinId">BUSD</span> Transfer to deposit account only.--}}
                        {{--                                        </div>--}}
                        {{--                                        <div class="div_network">--}}
                        {{--                                            <span class="">NETWORK :: BNB Smart Chain (BEP20)</span>--}}
                        {{--                                        </div>--}}
                        {{--                                        <div class="div_address">--}}
                        {{--                                            <span class="">ADDRESS :: 0xc38dcc4c1d94a23aceeb22f841ede5bb1dff96f5</span>--}}
                        {{--                                            <span class=""--}}
                        {{--                                                  id="spAddress">0xc38dcc4c1d94a23aceeb22f841ede5bb1dff96f5</span>--}}
                        {{--                                            <i class="fas fa-copy i_coppy_addr" onclick="coppy_text()"></i>--}}
                        {{--                                        </div>--}}
                        {{--                                        <div class="div_memo">--}}
                        {{--                                            <span class="">TAG :: </span>--}}
                        {{--                                            <i class="fas fa-copy i_coppy_memo" onclick="coppy_text1()"></i>--}}
                        {{--                                        </div>--}}
                        {{--                                    </div>--}}
                        {{--                                </div>--}}
                        {{--                                <div class="">--}}
                        {{--                                    <div class="drow-bxwar">--}}
                        {{--                                        <span>* Please use coins BUSD Transfer deposit account as network BNB Smart Chain (BEP20) Only</span>--}}
                        {{--                                    </div>--}}
                        {{--                                </div>--}}
                        {{--                            </div>--}}
                        {{--                            <div class="relative w-full flex flex-col text-white content-start p-5 h-auto bg-bn">--}}
                        {{--                                <div class="box__ex mb-2">--}}
                        {{--                                    <span>34.66 THB</span>--}}
                        {{--                                </div>--}}
                        {{--                                <div class="mb-2">--}}
                        {{--                                    <div class="drow-timedown mb-2">--}}
                        {{--                                        <span class="box-time">Please Transfer Within <label class="lbtime2"--}}
                        {{--                                                                                             id="countdowntime">15:00</label> Minute </span>--}}
                        {{--                                    </div>--}}
                        {{--                                    <div class="drow-pclick2">--}}
                        {{--                                        <span>When transfer Please press button below</span>--}}
                        {{--                                    </div>--}}
                        {{--                                </div>--}}
                        {{--                                <div>--}}
                        {{--                                    <div class="">--}}
                        {{--                                        <div class="bg-app_green hover:bg-gray-600 hover:text-app_green duration-150 text-app_black w-full md:w-2/2 h-10 rounded-lg text-lg md:text-xl font-semibold md:font-medium flex flex-row justify-center items-center mb-2 cur"--}}
                        {{--                                             id="btnSuccress" onclick="cryptotabclose();">OK--}}
                        {{--                                        </div>--}}
                        {{--                                        <div class="bg-app_gray2 hover:bg-gray-600 hover:text-app_green duration-150 text-app_black w-full md:w-2/2 h-10 rounded-lg text-lg md:text-xl font-semibold md:font-medium flex flex-row justify-center items-center cur"--}}
                        {{--                                             id="btnCancel" onclick="cryptotabclose();">Cancel--}}
                        {{--                                        </div>--}}
                        {{--                                    </div>--}}
                        {{--                                    <div>--}}
                        {{--                                        <div class="drow-txwar">Please transfer to the account number above. The system--}}
                        {{--                                            will automatically add credit within 3-5 minutes if you haven't received the--}}
                        {{--                                            credit or have any doubts. Please select from the menu, contact staff 24--}}
                        {{--                                            hours. Thank you.--}}
                        {{--                                        </div>--}}
                        {{--                                    </div>--}}
                        {{--                                </div>--}}
                        {{--                            </div>--}}
                        {{--                        </div>--}}


                        {{--                    </div>--}}
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div id="wd" class="tabcontent">
        <div class="headertab"><h2>{{ __('app.home.withdraw') }}</h2></div>
        <div class="wdtab">
            <div class="wdtablink bank active">
                <img class="banktabicon" src="/assets/pgslot/images/icon/bankicon.png?v=2">
                <span>{{ __('app.home.topup_bank') }}</span>
            </div>

        </div>
        <div class="containwd  mt-4 p-2 pt-0 boxwd bank">
            <div class="row m-0">
                <div class="col-lg-6 col-md-6 col-sm-12 p-0 centerleftct">
                    <div class="detailwd">
                        <table align="center">
                            <tbody>
                            <tr>
                                <td>
                                    <img src="{{ Storage::url('bank_img/' . $userdata->bank->filepic) }}">
                                </td>
                                <td>
                                    {{ __('app.profile.bank') }}: {{ $userdata->bank->name_th }} <br>
                                    <span>{{ __('app.profile.account') }}: {{ $userdata->acc_no }}</span><br>
                                     {{ $userdata->name }}
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="col-lg-6 col-md-6 col-sm-12 p-0">
                    <form name="withdraw" method="post" id="frmwithdraw" onsubmit="return false;">

                        <div class="containinputwd">
                            <div class="headerinput"><span>{{ __('app.withdraw.detail') }}</span></div>
                            <table>
                                <tbody>
                                <tr>
                                    <td>
                                        {{ __('app.home.withdraw_credit') }}
                                    </td>
                                    <td>
                                        {{ __('app.home.withdraw_min') }}
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <span class="wallet_amount">{{ $userdata->balance }}</span>
                                    </td>
                                    <td>
                                        {{ $config->minwithdraw }}
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="2">
                                        <div class="porela" style="margin-top: 15px;">
                                            <label for="exampleInputEmail1">{{ __('app.home.withdraw_amount') }}</label>
                                            <input pattern="[0-9]+" type="text" class="inputstyle" id="amount" name="amount"
                                                   autocomplete="off"
                                                   aria-describedby="emailHelp" placeholder="จำนวนเงินที่ต้องการถอน">
                                        </div>
                                        <small>{{ __('app.home.withdraw_turn') }} : {{number_format($userdata->turnpro,2) }}</small>
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                            <div class="btnwd text-center">
                                <button type="button" class="loginbtn">{{ __('app.home.withdraw') }}</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div>

    <div id="history" class="tabcontent">
        <div>
            <div class="headertab"><h2>{{ __('app.home.history') }}</h2></div>
            <historys>
                @foreach($historytab as $bank)
                    <history :item="{{ json_encode($bank) }}" {{ $bank['select'] == 'true' ? ':selected="true"' : '' }}></history>
                @endforeach

            </historys>
        </div>
        {{--    <div class="containhis pb-5">--}}
        {{--        <div class="row m-0 mt-3">--}}
        {{--            <div class="col-2 p-0 leftdps">--}}
        {{--                <div class="nav flex-column nav-pills" id="v-pills-tab" role="tablist" aria-orientation="vertical">--}}
        {{--                    <a class="nav-link green active" id="v-pills-dps-tab" data-toggle="pill" href="#v-pills-dps"--}}
        {{--                       role="tab" aria-controls="v-pills-dps" aria-selected="true"--}}
        {{--                       onclick="LoadHistory('deposit')">ฝาก</a>--}}
        {{--                    <a class="nav-link red" id="v-pills-wd-tab" data-toggle="pill" href="#v-pills-wd" role="tab"--}}
        {{--                       aria-controls="v-pills-wd" aria-selected="fals" onclick="LoadHistory('withdraw')">ถอน</a>--}}
        {{--                </div>--}}
        {{--            </div>--}}
        {{--            <div class="col-10 p-0 containhislist">--}}
        {{--                <div class="tab-content" id="v-pills-tabContent">--}}
        {{--                    <div class="tab-pane fade show active" id="v-pills-dps" role="tabpanel"--}}
        {{--                         aria-labelledby="v-pills-dps-tab">--}}
        {{--                        <div class="containerhis historydata">--}}


        {{--                        </div>--}}
        {{--                    </div>--}}
        {{--                    <div class="tab-pane fade" id="v-pills-wd" role="tabpanel" aria-labelledby="v-pills-wd-tab">--}}
        {{--                        <div class="containerhis historydata">--}}


        {{--                        </div>--}}
        {{--                    </div>--}}
        {{--                </div>--}}
        {{--            </div>--}}
        {{--        </div>--}}
        {{--    </div>--}}
    </div>
    <div id="promotion" class="tabcontent">
        <div class="headertab"><h2>{{ __('app.home.promotion') }}</h2></div>
        <!-- Swiper -->
        <promotions></promotions>
{{--        <div class="swiper mypromotion">--}}
{{--            <div class="swiper-wrapper">--}}
{{--            </div>--}}
{{--            <div class="swiper-button-next"></div>--}}
{{--            <div class="swiper-button-prev"></div>--}}
{{--            <div class="swiper-pagination"></div>--}}
{{--        </div>--}}

    </div>
    <div id="friend" class="tabcontent">
        <div class="headertab"><h2>{{ __('app.home.suggest') }}</h2></div>
        <div class="containwd my-3 p-2">
            <div class="row m-0">
                <div class="col-lg-6 col-md-6 col-sm-12 p-0">
                    <div class="containleftfriend01">
                        <div class="detailwd frienddetail">
                            <div class="headerinput text-center m-0 mb-1"><span>{{ __('app.con.detail') }}</span></div>
                            <table align="center">
                                <tr>
                                    <td class="text-center">
                                        <img src="{{ Storage::url('bank_img/' . $userdata->bank->filepic) }}">
                                    </td>
                                    <td class="tbfriendleft">
                                        {{ __('app.profile.bank') }}: {{ $userdata->bank->name_th }} <br>
                                        {{ __('app.profile.account') }}: {{ $userdata->acc_no }}
                                        <h5><span>{{ __('app.con.money') }}:</span><br> <bonus class="faststart_amount">{{ number_format($userdata->faststart,0) }} </bonus></h5>
                                    </td>
                                </tr>
                            </table>
                            <div class="btnwd mt-1 text-center">
                                <button class="colorbtn01 p-2 mcolor js-promotion-apply"
                                        onclick="openPopup('FASTSTART','{{ __('app.bonus.faststart') }}')">{{ __('app.con.get') }}
                                </button>
                            </div>
                        </div>
                        <div class="porela containlinkfriend text-left">
                            <label for="exampleInputEmail1">{{ __('app.con.link') }}</label>
                            <div class="iconlogin">
                                <i class="fad fa-copy" style="font-size: 20px;"></i>
                            </div>
                            <input type="text" onclick="copylink()" id="friendlink" class="loginform01 copylink" readonly
                                   value="{{ route('customer.contributor.register',$userdata->code) }}">
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 col-md-6 col-sm-12 p-0">
                    <div class="wrapgrid001">
                        <div class="inwrapgrid001">
                            <div class="ininwrapgrid001 active" onclick="openfriendtab(event, 'allfriend')"
                                 id="tabfriendopen">
                                <i class="far fa-handshake"></i><br>
                                {{ __('app.con.overview') }}
                            </div>
                        </div>
                        <div class="inwrapgrid001">
                            <div class="ininwrapgrid001" onclick="openfriendtab(event, 'friendtabs')">
                                <i class="far fa-handshake"></i><br>
                                {{ __('app.con.friend') }}
                            </div>
                        </div>
                        <div class="inwrapgrid001">
                            <div class="ininwrapgrid001" onclick="openfriendtab(event, 'moneyfriendtabs')">
                                <i class="far fa-handshake"></i><br>
                                {{ __('app.con.income') }}
                            </div>
                        </div>
                    </div>
                    <div class="containfriendwd" id="allfriend">
                        <div class="headerinput"><span>{{ __('app.con.desc') }}</span></div>
                        <table class="mt-2 levelfriend">
                            <tbody>
                            <tr>
                                <td class="text-left">
                                    <i class="fad fa-coins" style="font-size: 20px;"></i>
                                    <span>{{ __('app.con.money') }} </span><br>
                                    <small>* {{ __('app.con.remark') }}</small>
                                </td>
                                <td class="text-right">
                                    <span style="font-size: 20px;" class="friend_percent">0 %</span>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                        <div style=" padding:0 30px; padding-top: 10px; text-align: center; ">
                            <span class="detailaf">{{ __('app.con.descshort') }}</span>
                            <div role="alert" class="indetail">
                                <div class="row m-0" style="padding-top: 5px;">
                                    <div class="col-6 p-0 text-left">
                                        <span>{{ __('app.con.friendall') }}</span>
                                    </div>
                                    <div class="col-4 p-0 text-right friend_sum">0</div>
                                    <div class="col-2 p-0">{{ __('app.con.kon') }}</div>
                                </div>
                                <div class="row m-0" style="padding-top: 5px;">
                                    <div class="col-6 p-0 text-left">
                                        <span>{{ __('app.con.friend_deposit') }}</span>
                                    </div>
                                    <div class="col-4 p-0 text-right friend_sum_deposit">0</div>
                                    <div class="col-2 p-0">{{ __('app.con.kon') }}</div>
                                </div>
                                <div class="row m-0" style="padding-top: 5px;">
                                    <div class="col-6 p-0 text-left">
                                        <span>{{ __('app.con.income') }}</span>
                                    </div>
                                    <div class="col-4 p-0 text-right friend_sum_faststart">0.00</div>
                                    <div class="col-2 p-0"></div>
                                </div>
                                {{--                            <div class="row m-0" style="padding-top: 5px;">--}}
                                {{--                                <div class="col-6 p-0 text-left">--}}
                                {{--                                    <span>ยอดแทงเสีย</span>--}}
                                {{--                                </div>--}}
                                {{--                                <div class="col-4 p-0 text-right">0.00</div>--}}
                                {{--                                <div class="col-2 p-0">C</div>--}}
                                {{--                            </div>--}}
                            </div>
                        </div>
                    </div>
                    <div class="containfriendwd containerhis" id="friendtabs">
                        <div style="margin-top: 15px; position: relative;text-align: center;"><span class="detailaf">{{ __('app.con.friend_name') }}</span>
                        </div>
                        <div class="divoffriends friendzone">

                        </div>
                    </div>
                    <div class="containfriendwd" id="moneyfriendtabs">
                        <div style="padding:10px;">{{ __('app.con.month') }}</div>
                        <div class="divoffriends containerhis">
                            <input type="month" id="month" name="month" class="form-control">
                            <div class="friendmoneyzone"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div id="account" class="tabcontent">
        <div class="accountdetail">
            <div class="headertab"><h2>{{ __('app.con.account') }}</h2></div>

            <div class="containwd my-3 p-2 detailwd accountct">
                <table align="center">
                    <tbody>
                    <tr>
                        <td>
                            <img src="{{ Storage::url('bank_img/' . $userdata->bank->filepic) }}">
                        </td>
                        <td>
                            {{ __('app.profile.bank') }}: {{ $userdata->bank->name_th }} <br>
                            <span>{{ __('app.profile.account') }}: {{ $userdata->acc_no }}</span><br>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
            <table align="center" class="accountofuser mt-3">
                <tbody>
                <tr class="trofaccount">
                    <td class="headeraccount"><i class="fad fa-user"></i></td>
                    <td>{{ __('app.profile.kun') }}<br> <span>{{ $userdata->name }}</span></td>
                    <td class="headeraccount"><i class="fad fa-user-circle"></i></td>
                    <td>{{ __('app.profile.username') }} <br> <span>{{ $userdata->user_name }}</span></td>
                </tr>
                <tr class="trofaccount">
                    <td class="headeraccount"><i class="fad fa-lock"></i></td>
                    <td>{{ __('app.login.password') }} <br> <span>xxxxxxxx</span></td>
                    <td class="headeraccount"><i class="fal fa-edit"></i></td>
                    <td class="cursorp" onclick="changepassword()"><span>{{ __('app.home.changepass') }}</span></td>
                </tr>
                </tbody>
            </table>

        </div>
        <div class="containcpass">
            <div class="backaccount cursorp"><i class="far fa-chevron-left"></i> {{ __('app.home.back') }}</div>
            <form name="changepass" method="post" id="frmchangepass" onsubmit="return false;">
                <div class="headertab"><h2>{{ __('app.home.changepass') }}</h2></div>
                <div class=" form-group">
                    <div>
                        <label> {{ __('app.home.new_password') }}</label>
                        <div class="el-input mt-1">
                            <!----><input type="text" placeholder="รหัสผ่านใหม่" class="inputstyle" name="password" id="password" minlength="6" required><!----><!----><!---->
                            <!---->
                        </div>
                        <!---->
                    </div>
                </div>
                <div class=" form-group">
                    <div>
                        <label> {{ __('app.home.new_password_confirm') }}</label>
                        <div class="el-input mt-1">
                            <!----><input type="text" placeholder="ยืนยันรหัสผ่านใหม่" class="inputstyle" name="password_confirmation" id="password_confirmation" minlength="6" required><!----><!---->
                            <!----><!---->
                        </div>
                        <!---->
                    </div>
                </div>

                <button type="submit" class="loginbtn changepassbtn">
                    <!----><!----><span>
      {{ __('app.home.changepass') }}
      </span>
                </button>
            </form>
        </div>
    </div>
    <div id="event" class="tabcontent">
        <div class="gridicontop">
            <div class="ingridicontop" onclick="openTab(event, 'fortune')">
                <img src="/assets/pgslot/images/icon/wheel.png">
                {{ __('app.home.wheel') }}
            </div>
        </div>
    </div>
    <div id="fortune" class="tabcontent">
        <div>
            <div class="headertab"><h2>{{ __('app.home.wheel') }}</h2></div>
            <div class="containwd my-3 p-2">
                <wheel :items="{{ json_encode($spins) }}" :spincount="{{ $userdata->diamond }}"></wheel>
            </div>
        </div>
    </div>
    <div id="flag" class="tabcontent">
        <div>
            <div class="headertab"><h2>{{ __('app.login.language') }}</h2></div>
            <div class="containwd my-3 p-2">
                <div class="row -wrapper-box">
                    <div class="col"><a style="color:black"
                                        href="{{ route('customer.home.lang', ['lang' => 'th']) }}"><img
                                    src="/images/flag/th.png" class="img img-fluid" loading="lazy"
                                    fetchpriority="low"></a></div>
                    <div class="col"><a style="color:black"
                                        href="{{ route('customer.home.lang', ['lang' => 'en']) }}"><img
                                    src="/images/flag/en.png" class="img img-fluid" loading="lazy"
                                    fetchpriority="low"></a></div>
                    <div class="col"><a style="color:black"
                                        href="{{ route('customer.home.lang', ['lang' => 'kh']) }}"><img
                                    src="/images/flag/kh.png" class="img img-fluid" loading="lazy"
                                    fetchpriority="low"></a></div>
                    <div class="col"><a style="color:black"
                                        href="{{ route('customer.home.lang', ['lang' => 'la']) }}"><img
                                    src="/images/flag/la.png" class="img img-fluid" loading="lazy"
                                    fetchpriority="low"></a></div>
                </div>
            </div>
        </div>
    </div>
</div>



@push('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/latest/TweenMax.min.js"></script>
    <template id="history-theme" style="display:none">

        <div class="listht">
            <table>
                <tr>
                    <td>
                        {method} <small style="color:red">( {status} )</small><br>
                        <span class="timehis">{billid}</span>
                    </td>
                    <td>
                        {amount} <br>
                        <span class="timehis">{datetime}</span>
                    </td>
                </tr>
            </table>
        </div>
    </template>
    <template id="friend-theme" style="display:none">

        <div class="listht">
            <table>
                <tr>
                    <td>
                        {id}<br>
                        <span class="timehis"></span>
                    </td>
                    <td>
                        {datetime}<br>
                        <span class="timehis">{{ __('app.profile.register_date') }}</span>
                    </td>
                </tr>
            </table>
        </div>
    </template>
    <template id="friendmoney-theme" style="display:none">

        <div class="listht">
            <table>
                <tr>
                    <td>
                        {id}<br>
                        <span class="timehis">{{ __('app.profile.refill_first_date') }}</span>
                    </td>
                    <td>
                        {{ __('app.profile.amount') }} {amount} <br>
                        <span class="timehis">{datetime}</span>
                    </td>
                </tr>
            </table>
        </div>
    </template>
    <script type="text/x-template" id="topup-content-top-template">
        <div class="containhis pb-5">

            <div class="row m-0 mt-3">
                <div class="col-2 p-0 leftdps">
                    <div class="nav flex-column nav-pills" id="v-pills-tab" role="tablist" aria-orientation="vertical">
                        <a class="nav-link" :class="[{ active: bank.isActive } , bank.tabcolor ]"  v-for="(bank, index) in banks" @click="selectTab(bank)" :key="index" :id="bank.tabid" data-toggle="pill" :href="bank.tabhref" role="tab" aria-controls="v-pills-dps" :aria-selected="bank.tabselect"  :title="bank.name"
                           v-text="bank.name"></a>
                    </div>
                </div>
                <div class="col-10 p-0 containhislist">
                    <div class="tab-content" id="v-pills-tabContent">
                        <slot></slot>
                    </div>
                </div>
            </div>

        </div>

    </script>
    <script type="text/x-template" id="topup-content-down-template">
        <div class="tab-pane fade" :class="[{ active : isActive } , { show : isActive}]"  role="tabpanel" aria-labelledby="v-pills-dps-tab" v-show="isActive" :id="tabname">
            <div class="containerhis">
                <!--  Loop list DPS -->
                <div :class="[ tabname === 'deposit' ? 'listhtwd' : 'listht']" v-for="(item, index) in list" :key="index">
                    <span class="badge rounded-pill" :class="item.status_color" v-text="item.status_display" v-if="item.status_display"></span>
                    <table>
                        <tbody>
                        <tr>
                            <td>
                                <span v-text="item.id"></span>
                            </td>
                            <td>
                                <span v-text="item.amount"></span> <br>
                                <span class="timehis" v-text="item.date_create"></span>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
                <!--  END Loop list DPS -->
            </div>
        </div>

    </script>
    <script>

        Vue.component('historys', {
            'template': '#topup-content-top-template',
            data: function () {
                return {
                    banks: [],
                    start: {},
                    end: {},
                    method: null,
                    startDate: null,
                    endDate: null,
                    daterangepicker: null,
                    money:0

                }
            },
            created() {
                this.banks = this.$children;

            },
            // watch: {
            //     startDate: function (event) {
            //         // console.log('startDAte '+event);
            //         this.loadData();
            //     },
            // },
            mounted() {
                this.method = this.banks[0].method;
                this.loadData();
            },
            provide() {
                return {
                    banks: this
                };
            },
            methods: {

                selectTab(selectedTab) {
                    let this_this = this;
                    this.banks.forEach(bank => {
                        bank.isActive = (bank.method == selectedTab.method);
                        if (bank.isActive == true) {
                            this.method = selectedTab.method;
                            this_this.loadData();
                        }
                    });
                },
                loadData: function () {
                    // console.log('Clicked evemt');

                    this.$http.post("{{ route('customer.history.store') }}", {
                        'id': this.method
                    })
                        .then(response => {
                            if (response.status) {
                                this.banks.forEach(bank => {
                                    bank.isActive = (bank.method == this.method);
                                    if (bank.isActive == true) {
                                        bank.list = response.data.data;
                                        bank.tabname = this.method;
                                    } else {
                                        bank.list = '';
                                    }
                                });

                            }
                        })
                        .catch(exception => {
                            console.log('error');
                        });
                },
            }
        })

        Vue.component('history', {
            'template': '#topup-content-down-template',
            props: {
                item: {},
                selected: {
                    default: false
                }
            },

            data() {
                return {
                    isActive: false,
                    list: [],
                    tabname: '',
                    tabid: "",
                    tabhref: "",
                    tabcolor: "",
                    tabselect: "",
                    status: 0,

                };
            },

            mounted() {
                this.isActive = this.selected;
                this.name = this.item.name;
                this.method = this.item.method;
                this.tabname = this.item.method;
                this.tabid = this.item.id;
                this.tabcolor = this.item.color;
                this.tabhref = this.item.href;
                this.tabselect = this.item.select;
                this.status = this.item.status;
            },
            applyFilter: function (field, date) {
                this[field] = date;

                // window.location.href = "?start=" + this.start + '&end=' + this.end;
            }
        })


    </script>
{{--    <script>--}}
{{--        document.addEventListener('DOMContentLoaded', async () => {--}}
{{--            // สร้าง Swiper instance--}}
{{--            const swiper = new Swiper('.mypromotion', {--}}
{{--                slidesPerView: "auto",--}}
{{--                spaceBetween: 30,--}}
{{--                loop: false,--}}
{{--                pagination: {--}}
{{--                    el: ".swiper-pagination",--}}
{{--                    clickable: true,--}}
{{--                },--}}
{{--                navigation: {--}}
{{--                    nextEl: ".swiper-button-next",--}}
{{--                    prevEl: ".swiper-button-prev",--}}
{{--                },--}}
{{--            });--}}

{{--            // ฟังก์ชันโหลดรูปภาพผ่าน AJAX--}}
{{--            const loadSlides = async () => {--}}
{{--                try {--}}
{{--                    // ดึงข้อมูลจาก API--}}
{{--                    const response = await fetch("{{ route('customer.promotion.loadPromotion') }}");--}}
{{--                    const data = await response.json();--}}
{{--                    console.log(data.data.promotions);--}}
{{--                    const pro = data.data.promotions;--}}
{{--                    // เพิ่ม slides ลงใน swiper-wrapper--}}
{{--                    pro.forEach((item) => {--}}
{{--                        const newSlide = document.createElement('div');--}}
{{--                        newSlide.className = 'swiper-slide';--}}
{{--                        newSlide.innerHTML = `--}}
{{--              <img src="${item.filepic}" alt="${item.name_th}" />--}}
{{--              <button class="getpro">รับโปรโมชั่น</button>--}}
{{--            `;--}}
{{--                        document.querySelector('.swiper-wrapper').appendChild(newSlide);--}}
{{--                    });--}}

{{--                    // อัปเดต Swiper หลังเพิ่ม slide--}}
{{--                    swiper.update();--}}
{{--                    new Swiper('.mypromotion', {--}}
{{--                        slidesPerView: "auto",--}}
{{--                        spaceBetween: 30,--}}
{{--                        loop: false,--}}
{{--                        pagination: {--}}
{{--                            el: ".swiper-pagination",--}}
{{--                            clickable: true,--}}
{{--                        },--}}
{{--                        navigation: {--}}
{{--                            nextEl: ".swiper-button-next",--}}
{{--                            prevEl: ".swiper-button-prev",--}}
{{--                        },--}}
{{--                    });--}}
{{--                } catch (error) {--}}
{{--                    console.error('เกิดข้อผิดพลาดในการโหลดรูปภาพ:', error);--}}
{{--                }--}}
{{--            };--}}

{{--            // เรียกใช้ฟังก์ชันโหลดรูปทันทีที่หน้าเว็บโหลด--}}
{{--            await loadSlides();--}}
{{--        });--}}
{{--    </script>--}}
{{--    <script type="text/x-template" id="promotion-content-top-template">--}}
{{--        <div class="swiper mypromotion">--}}
{{--            <div class="swiper-wrapper">--}}
{{--                <div class="swiper-slide" v-for="(item, index) in items" :key="index">--}}
{{--                    <img :src="item.filepic">--}}
{{--                    <button :data-id="item.code" class="getpro" v-if="getpro">รับโปรโมชั่น</button>--}}
{{--                    <button disabled style="opacity:0.4;" v-else>รับโปรโมชั่น</button>--}}

{{--                </div>--}}
{{--            </div>--}}
{{--            <div class="swiper-button-next"></div>--}}
{{--            <div class="swiper-button-prev"></div>--}}
{{--            <div class="swiper-pagination"></div>--}}
{{--        </div>--}}

{{--    </script>--}}
{{--    <script>--}}

{{--        Vue.component('promotions', {--}}
{{--            'template': '#promotion-content-top-template',--}}
{{--            data: function () {--}}
{{--                return {--}}
{{--                    swiper: [],--}}
{{--                    items: {},--}}
{{--                    getpro: false--}}
{{--                }--}}
{{--            },--}}
{{--            created() {--}}
{{--                this.create();--}}
{{--            },--}}
{{--            mounted() {--}}

{{--                this.loadData();--}}
{{--            },--}}
{{--            provide() {--}}
{{--                return {--}}
{{--                    promotions: this--}}
{{--                };--}}
{{--            },--}}
{{--            methods: {--}}
{{--                create() {--}}
{{--                    this.swiper = new Swiper(".mypromotion", {--}}
{{--                        slidesPerView: "auto",--}}
{{--                        spaceBetween: 30,--}}
{{--                        loop: true,--}}
{{--                        pagination: {--}}
{{--                            el: ".swiper-pagination",--}}
{{--                            clickable: true,--}}
{{--                        },--}}
{{--                        navigation: {--}}
{{--                            nextEl: ".swiper-button-next",--}}
{{--                            prevEl: ".swiper-button-prev",--}}
{{--                        },--}}
{{--                    });--}}
{{--                },--}}
{{--                async loadData() {--}}

{{--                    const response = await axios.get(`${this.$root.baseUrl}/member/promotion/api`);--}}
{{--                    this.$nextTick(() => {--}}
{{--                        this.items = response.data.promotions--}}
{{--                        this.getpro = response.data.getpro;--}}
{{--                    })--}}
{{--                },--}}

{{--            }--}}
{{--        })--}}

{{--    </script>--}}
@endpush