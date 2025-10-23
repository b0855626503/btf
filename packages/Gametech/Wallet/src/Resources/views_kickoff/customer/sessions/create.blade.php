{{-- extend layout --}}
@extends('wallet::layouts.app')

{{-- page title --}}
@section('title','')

@push('styles')
    <style>
        .homelogin {
            display: none !important;
        }
    </style>
@endpush

@section('content')

    <div class="bg-member sub-page sub-footer vhm-100"
         style="display: flex; justify-content: center; align-items: center;">
        <div class="login-container card mt-2">
            <h3 class="text-center pt-3">{{ __('app.login.login') }}</h3>
            <img src="{{ url(core()->imgurl($config->logo,'img')) }}" class="card-img-top px-1 w-100"
                 style="object-fit: contain; height: 7em;" alt="เข้าสู่ระบบ">
            <div class="card-body pt-0 px-0">
                <div>
                    <span class="text-content d-block text-center mb-2">{{ __('app.login.promote') }}</span>
                    <form class="theme-form" method="POST" action="{{ route('customer.session.create') }}" onsubmit="return;">
                        @csrf
                        <div class="input-group mb-1">
                            <span class="input-group-text"><i class="bi bi-person-fill bi-1-5x"></i></span>
                            <input class="form-control" type="tel" placeholder="{{ __('app.login.username') }}"
                                   id="user_name" name="user_name" maxlength="10" required="">
                        </div>
                        <div class="input-group mb-2">
                            <span class="input-group-text"><i class="bi bi-key-fill bi-1-5x"></i></span>
                            <input class="form-control" type="password" inputmode="tel" id="password" name="password"
                                   minlength="6" maxlength="10" required="">
                        </div>

                        <button class="btn btn-custom-primary w-100 mt-3 rounded-pill fw-bolder" id="btnLog"
                                type="submit">{{ __('app.login.login') }}</button>
                        <div class="d-inline-flex w-100 mt-3 justify-content-between">
                            <div>
                                <a href="{{ route('customer.session.store') }}"
                                   class="btn btn-link btn-sm">{{ __('app.login.register') }}</a>
                            </div>
                            <div>
                                <a href="{{ $config->linelink }}" target="_blank"
                                   class="btn btn-link btn-sm text-white">{{ __('app.login.help') }}</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

@endsection
