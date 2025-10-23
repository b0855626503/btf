@extends('wallet::layouts.master')

{{-- page title --}}
@section('title','')



@section('content')

    <div class="sub-page sub-footer" style="display: flex; justify-content: center; align-items: center;">
        <div class="container promotion-member-container">
            @foreach($promotions as $i => $item)
                <div class="promotion-item card mb-3 mx-auto border-none shadow rounded overflow-hidden bg-transaparent"
                     style="width: 720px; max-width: 95%;">
                    <img src="{{  Storage::url('promotion_img/'.$item->filepic)  }}" class="w-100">
                    <div class="card-body bg-dark p-0">
                        <div class="card bg-dark-2">
                            <div class="card-body">
                                <h3>{{ $item->name_th }}</h3>
                                <hr class="m-0">
                                {!! $item->content !!}
                            </div>
                            <div class="card-footer text-center ">
                                <form method="POST" action="{{ route('customer.promotion.select') }}">
                                    @csrf
                                    <input type="hidden" name="promotion" value="{{ $item->code }}">

                                    <button class="btn -btn btn-success" type="submit"><span>{{ __('app.promotion.choose') }}</span></button>

                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach

            @foreach($pro_contents as $i => $item)
                <div class="promotion-item card mb-3 mx-auto border-none shadow rounded overflow-hidden bg-transaparent"
                     style="width: 720px; max-width: 95%;">
                    <img src="{{  Storage::url('procontent_img/'.$item->filepic)  }}" class="w-100">
                    <div class="card-body bg-dark p-0">
                        <div class="card bg-dark-2">
                            <div class="card-body">
                                <h3>{{ $item->name_th }}</h3>
                                <hr class="m-0">
                                {!! $item->content !!}
                            </div>
                            <div class="card-footer text-center ">
                                <!---->
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach

        </div>
    </div>

@endsection




