<div class="header-menu">
    @if(request()->routeIs('customer.game.list'))
        <div class="navmenu home" onclick="window.location.href='{{ route('customer.home.index') }}'">
            <img src="/assets/pgslot/images/icon/icon-home.png"><br>
            {{ __('app.login.home') }}
        </div>
        <div class="navmenu play active" onclick="openTab(event, 'homepage')" id="defaultOpen">
            <img src="/assets/pgslot/images/icon/icon-game.png"><br>
            {{ __('app.game.list') }}
        </div>
    @else
        <div class="navmenu play active" onclick="openTab(event, 'homepage')" id="defaultOpen">
            <img src="/assets/pgslot/images/icon/icon-home.png"><br>
            {{ __('app.login.home') }}
        </div>
    @endif

    <div class="navmenu deposit" onclick="openTab(event, 'dps')">
        <img src="/assets/pgslot/images/icon/icon-deposit.png"><br>
        {{ __('app.home.deposit') }}
    </div>
    <div class="navmenu withdraw" onclick="openTab(event, 'wd')">
        <img src="/assets/pgslot/images/icon/icon-withdraw.png"><br>
        {{ __('app.home.withdraw') }}
    </div>
    <div class="navmenu history" onclick="openTab(event, 'history')">
        <img src="/assets/pgslot/images/icon/icon-history.png"><br>
        {{ __('app.home.history') }}
    </div>
    <div class="navmenu friend" onclick="openTab(event, 'friend')">
        <img src="/assets/pgslot/images/icon/icn-card-2.png"><br>
        {{ __('app.home.suggest') }}
    </div>
</div>