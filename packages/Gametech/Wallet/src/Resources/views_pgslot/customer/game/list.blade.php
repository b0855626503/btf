@extends('wallet::layouts.master')

{{-- page title --}}
@section('title','')



@section('content')


    <div id="homepage" class="tabcontent ">


        @if($config->seamless == 'Y')
            @include('wallet::customer.game.gamelist')
        @else
            @include('wallet::customer.game.gamelistsingle')
        @endif

    </div>

@endsection

