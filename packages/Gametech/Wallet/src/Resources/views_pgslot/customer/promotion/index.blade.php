@if($config->seamless == 'Y')
    @include('wallet::customer.promotion.seamless')
@else
    @include('wallet::customer.promotion.normal')
@endif