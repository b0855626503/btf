<div id="homepage" class="tabcontent ">
    @if(request()->routeIs('customer.home.index'))
    @include('wallet::customer.promotion.index')
    @include('wallet::customer.game.gamehome')
    @include('wallet::customer.game.gamelast')
    @else
        @include('wallet::customer.game.gamelist')
    @endif
</div>

