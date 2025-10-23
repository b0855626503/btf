
<div class="containcbox">

    <div class="creditbox">
        <div class="headercredit">
            <div class="flexcredit cleft">
            <span>
            <i class="fa fa-user" onclick="openTab(event, 'account')"></i> {{ $userdata->user_name }}
         </span>
            </div>
            <div class="flexcredit">
                <i class="fa fa-parking-circle"></i>
                <point class="point_amount">{{ number_format($userdata->point_deposit,0) }}</point>

            </div>
            <div class="flexcredit cright">
                <i class="fa fa-coins"></i>
                <money class="wallet_amount">{{ $userdata->balance }}</money>
                <a class="-btn-balance -btn-balance-normal -in-box btnbalance">
                    <i class="fa fa-sync-alt ml-2 text-highlight"></i>
                </a>
            </div>
        </div>
        <div class="row m-0">
            <div class="col-6 left js-promotion-apply" onclick="openPopup('BONUS','{{ __('app.bonus.wheel') }}')">
                <div class="boxheadc">
                    <div class="row col-12 p-0 mb-2">
                        <div class="col-8"><span class="text-white" style="font-size: 10px">{{ __('app.bonus.wheel') }}</span></div>
                        <div class="col-4"><span class="text-white" style="font-size: 10px"></span></div>
                    </div>
                    <span class="text-center"><i class="far fa-usd-circle"></i> <bonus class="bonus_amount">{{ number_format($userdata->bonus,0) }}</bonus></span>
                </div>
            </div>
            <div class="col-6 right js-promotion-apply"
                 onclick="openPopup('CASHBACK','{{ __('app.bonus.cashback') }}')">
                <div class="boxheadc">
                    <div class="row col-12 p-0 mb-2">
                        <div class="col-8"><span class="text-white" style="font-size: 10px">{{ __('app.bonus.cashback') }}</span></div>
                        <div class="col-4"><span class="text-white" style="font-size: 10px"></span></div>
                    </div>
                    <span class="text-center"><i class="far fa-usd-circle"></i> <bonus class="cashback_amount">{{ number_format($userdata->cashback,0) }}</bonus></span>
                </div>
            </div>
        </div>
        <div class="row m-0">
            <div class="col-6 left js-promotion-apply"
                 onclick="openPopup('FASTSTART','{{ __('app.bonus.faststart') }}')">
                <div class="boxheadc">
                    <div class="row col-12 p-0 mb-2">
                        <div class="col-8"><span class="text-white" style="font-size: 10px">{{ __('app.bonus.faststart') }}</span></div>
                        <div class="col-4"><span class="text-white" style="font-size: 10px"></span></div>
                    </div>
                    <span class="text-center"><i class="far fa-usd-circle"></i> <bonus class="faststart_amount">{{ number_format($userdata->faststart,0) }}</bonus></span>
                </div>
            </div>
            <div class="col-6 right js-promotion-apply" onclick="openPopup('IC','{{ __('app.bonus.ic') }}')">
                <div class="boxheadc">
                    <div class="row col-12 p-0 mb-2">
                        <div class="col-8"><span class="text-white" style="font-size: 10px">{{ __('app.bonus.ic') }}</span></div>
                        <div class="col-4"><span class="text-white" style="font-size: 10px"></span></div>
                    </div>
                    <span class="text-center"><i class="far fa-usd-circle"></i> <bonus class="ic_amount">{{ number_format($userdata->ic,0) }}</bonus></span>
                </div>
            </div>
        </div>
    </div>
</div>
