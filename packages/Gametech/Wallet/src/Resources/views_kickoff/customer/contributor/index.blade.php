@extends('wallet::layouts.master')

{{-- page title --}}
@section('title','')



@section('content')

    <div class="sub-page sub-footer" style="min-height: 100vh;">
        <div class="container" style="max-width: 720px;">
            <div class="mt-3 text-center">
                <h2>{{ __('app.con.suggest') }}</h2>
            </div>
            <div class="card bg-dark">
                <div class="card-body">
                    <div class=" card bg-dark-2 mt-2">
                        <div class="card-body p-1">
                            <div class="row g-2">
                                <div class="col-12 text-center">
                                    <div class="card bg-dark py-2">
                                        <div class="small text-muted">{{ __('app.con.percent') }}</div>
                                        <div class="text-dark text-warning fs-5 bg-light rounded-pill w-100 mx-auto" style="max-width: 14em;">{{ $faststart }} %</div>
                                    </div>
                                </div>
                                <div class="col-12 col-md-4 text-center">
                                    <div class="card bg-dark bg-circle bg-circle-danger">
                                        <div class="small text-muted">{{ __('app.con.sum_income') }}</div>
                                        <div class="text-warning fs-5">{{ is_null($profile->payments_promotion_credit_bonus_sum) ? '0.00' : $profile->payments_promotion_credit_bonus_sum }}</div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-4 text-center">
                                    <div class="card bg-dark bg-circle bg-circle-success">
                                        <div class="small text-muted">{{ __('app.con.remain') }}</div>
                                        <div class="text-warning fs-5">{{ $userdata->faststart }}</div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-4 text-center">
                                    <div class="card bg-dark bg-circle bg-circle-info">
                                        <div class="small text-muted">{{ __('app.con.count') }}</div>
                                        <div class="text-warning fs-5 fw-bold">{{ $profile->downs_count }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="theme-form mb-4">
                    <div class="text-center mt-1 mb-1">
                        <button class="btn btn-primary w-100 rounded-pill btn-custom-primary" style="max-width: 20em;">{{ __('app.con.get') }}</button>
                    </div>
                </div>
            </div>
            <div class="card bg-dark mt-2">
                <div class="card-body">
                    <div class="fs-6 fw-light">
                        <div class="w-100 link-ref-coppy">
                            <span class="link-text lh-1"> {{ route('customer.contributor.register',$profile->code) }} </span>

                            <!-- COPY LINK -->
                            <button type="button"  class="btn_copy_bankcode btn btn-custom btn-custom-primary btn_copy_ref">
                                <i aria-hidden="true" class="fa fa-clone me-1" style="font-size: 22px;"></i> COPY
                                <!-- COPY THIS -->
                                <b class="d-none">{{ route('customer.contributor.register',$profile->code) }}</b>
                                <!-- COPY THIS -->
                            </button>
                            <!-- COPY LINK -->

                            <!---->
                            <input tabindex="-1" aria-hidden="true" class="ip-copyfrom">
                        </div>
                        <hr>
                        {{ __('app.con.more') }}
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection




