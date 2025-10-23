<nav class="header-dashboard">
    <div class="btnleft">
        <button class="dpshead">
            <img class="coincredit" src="/assets/pgslot/images/icon/coin.png">
            <span>{{ number_format($userdata->balance,2) }}</span>
            <a class="-btn-balances -btn-balance-normal -in-box btnbalance">
            <i class="fa fa-sync-alt"></i>
            </a>
        </button>
    </div>
    <div class="bandcenter">
        <img src="{{ url(core()->imgurl($config->logo,'img')) }}">
    </div>

    <div class="menuright">
        <!-- <div class="col-auto text-right">
           <div style="text-align: right;font-size: 0.7rem;    --tw-space-x-reverse: 0;
           margin-left: calc(0.5rem * (1 - var(--tw-space-x-reverse)));
           margin-right: calc(0.5rem * var(--tw-space-x-reverse));">
              <span>เล่นค้าง</span> <span class="sp_outstanding text-success">0.00</span><br>
              <span>ยอดเทิร์นที่ต้องทำ</span> <span class="sp_turn_withdrawal text-danger">0.00</span><br>
              <span>ยอดเทิร์นล่าสุด</span> <span class="sp_turn_total text-warning">0.00</span><br>
           </div>
        </div> -->
        <button class="wdhead">
            <img class="coincredit" src="/assets/pgslot/images/icon/diamond.png">
            <span class="diamond_amount">{{ number_format($userdata->diamond,0) }}</span>
        </button>
        <div class="col-auto text-right">

        </div>
    </div>
    <div class="x-hamburger js-hamburger-toggle sidebarbtn sidebarCollapse ">
        <span></span>
        <span></span>
        <span></span>
    </div>
    <div class="menuslidebox animate__animated animate__fadeInDown">
        <div class="containbox">
            <div>
                <div class="flex flex-row justify-between text-white mb-3">
                    <div class="flex flex-row justify-between text-yellow">
                        <!-- <p style="margin-top: -25px;"><i class="fa-regular fa-user"></i> 0944989545</p> -->
                        <a href="javascript:void(0)" class="closebtn text-app_green hover:text-app_green">
                            <i class="fa-solid fa-angles-left"></i>
                        </a>
                    </div>
                </div>
                <!-- <div class="flex flex-row justify-between text-white">
                    <div class="flex flex-row justify-between">
                        <div>
                            <p>Main Menu</p>
                        </div>
                    </div>
                </div> -->
                <div class="divide-y divide-app_gray">
                    <div id="user-data" href="#" class="app__user-login"><i
                                class="fa fa-user"></i> {{ $userdata->user_name }}                    </div>
                    <div class="py-2">
                        <div class="user-info">

                            <img src="/assets/pgslot/images/icon/ranking_6.png" alt="rankingImg"
                                 class="amount-show-coin">
                            <div class="box-info ml-3 text-white">
                                <div>
                                    <img src="/assets/pgslot/images/icon/diamond.png" alt="pointIcon">
                                    <span> {{ __('app.profile.diamond') }} : </span>
                                    <span class="font-primary font-weight-bold diamond_amount">{{ number_format($userdata->diamond,0) }}</span>&nbsp;&nbsp;&nbsp;&nbsp;
                                    <img src="/assets/pgslot/images/icon/point.png" alt="pointIcon">
                                    <span> {{ __('app.profile.point') }} : </span>
                                    <span class="font-primary font-weight-bold point_amount">{{ number_format($userdata->point_deposit,0) }}</span>
                                </div>

                                <div class="mt-2">
                                    <img src="/assets/pgslot/images/icon/coin.png" alt="coinIcon">
                                    <span>{{ __('app.profile.credit') }} : </span>
                                    <span class="font-primary font-weight-bold wallet_amount">{{ $userdata->balance }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="text-center my-3">
                            <button class="dpshead mr-1" onclick="openTab(event, 'dps')">
                                <span class="py-2 px-4">{{ __('app.home.deposit') }}</span>
                            </button>
                            <button class="wdhead ml-1" onclick="openTab(event, 'wd')">
                                <span class="py-2 px-4">{{ __('app.home.withdraw') }}</span>
                            </button>
                        </div>
                        <div class="grid sidebar">
                            <div class="flex flex-col items-center justify-end">
                                <a onclick="openTab(event,'dps')" class="cur hover:opacity-60">
                                    <img class="iconside" src="/assets/pgslot/images/icon/icon-deposit.png">
                                    <p class="text-white text-center text-xs">{{ __('app.home.deposit') }}</p>
                                </a>
                            </div>
                            <div class="flex flex-col items-center justify-end">
                                <a onclick="openTab(event,'wd')" class="cur hover:opacity-60">
                                    <img class="iconside" src="/assets/pgslot/images/icon/icon-withdraw.png">
                                    <p class="text-white text-center text-xs">{{ __('app.home.withdraw') }}</p>
                                </a>
                            </div>
                            <div class="flex flex-col items-center justify-end">
                                <a onclick="openTab(event,'history')" class="cur hover:opacity-60">
                                    <img class="iconside" src="/assets/pgslot/images/icon/icon-history.png">
                                    <p class="text-white text-center text-xs">{{ __('app.home.history') }}</p>
                                </a>
                            </div>
                            <div class="flex flex-col items-center justify-end">
                                <a onclick="openTab(event,'promotion')" class="cur hover:opacity-60">
                                    <img class="iconside" src="/assets/pgslot/images/icon/icn-hot.png">
                                    <p class="text-white text-center text-xs">{{ __('app.home.promotion') }}</p>
                                </a>
                            </div>
                            <div class="flex flex-col items-center justify-end">
                                <a onclick="openTab(event,'friend')" class="cur hover:opacity-60">
                                    <img class="iconside" src="/assets/pgslot/images/icon/icn-card-2.png">
                                    <p class="text-white text-center text-xs">{{ __('app.home.suggest') }}</p>
                                </a>
                            </div>
                            <div class="flex flex-col items-center justify-end">
                                <a onclick="openTab(event,'flag')"  class="cur hover:opacity-60">
                                    <img class="iconside" src="/assets/pgslot/images/icon/icn-card-2.png">
                                    <p class="text-white text-center text-xs">{{ __('app.login.language') }}</p>
                                </a>
                            </div>
                            <div class="flex flex-col items-center justify-end">
                                <a onclick="openTab(event,'account')" class="cur hover:opacity-60">
                                    <img class="iconside" src="/assets/pgslot/images/icon/icn-card-2.png">
                                    <p class="text-white text-center text-xs">{{ __('app.home.profile') }}</p>
                                </a>
                            </div>
                        </div>
                    </div>
                    <!-- <div class="py-5">
                        <div class="flex flex-row text-white pb-3">
                            <p>Invite </p>
                        </div>
                        <div class="grid grid-cols-4 md:grid-cols-4 gap-5">

                        </div>
                    </div> -->
                    <div class="app__nav-menu-linkButton">

                        <div>
                            <a href="{{ route('customer.session.destroy') }}" class="app__buttonlink-menu-signout">
                                <i class="fas fa-sign-out"></i>{{ __('app.home.logout') }} </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</nav>