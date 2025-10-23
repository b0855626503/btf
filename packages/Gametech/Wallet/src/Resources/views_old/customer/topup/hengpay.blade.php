@extends('wallet::layouts.master')

{{-- page title --}}
@section('title','')

@section('back')
    <a class="nav-link p-2 text-light mx-auto hand-point" href="{{ route('customer.home.index') }}">
        <i class="fas fa-chevron-left"></i> กลับ</a>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('vendor/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css') }}">
    <style>
        .section {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .img-select {
            border: 2px solid #fff;
            border-radius: 10%;
            background-color: #ffffff3a;
        }

        .img-bank {
            width: 40px;
            height: 40px;
        }

        .hidden {
            display: none;
        }

        a {
            color: #007bff;
            text-decoration: none;
            background-color: transparent;
        }
    </style>
@endpush

@section('content')
    <div class="container text-light mt-5">
        <h3 class="text-center text-light">ฝากเงิน QR HengPay</h3>
        <p class="text-center text-color-fixed">Deposit by QR HengPay</p>
        <div class="row">
            <div class="col-md-8 offset-md-2 col-sm-12">
                <div class="card card-trans">
                    <div class="card-header text-center">
                        <p class="text-center text-color-fixed m-0">กรอกจำนวนเงินที่ต้องการเติม</p>
                    </div>
                    <div class="card-body p-2">
                        <form target="_blank" novalidate="" id="frmdeposit" name="deposit" method="post"
                              action="{{ route('customer.topup.hengpay') }}">
                            @csrf
                            <input required
                                   type="number"
                                   step="0.01"
                                   min="1"
                                   class="form-control"
                                   :class="[errors.has('amount') ? 'is-invalid' : '']"
                                   id="amount" name="amount"
                                   data-vv-as="&quot;Amount&quot;"
                                   placeholder="จำนวนเงิน" autocomplete="off"
                                   value="100.00">
                            <br>
                            <button class="btn btn-primary btn-block shadow-box">ยืนยัน
                            </button>
                        </form>
                    </div>
                </div>

                <div class="card card-trans text-center">
                    <p>QR Code มีอายุ 15 นาที ถ้าหมดอายุโปรด ทำรายการใหม่</p>
                    <p>QR Code เมื่อใช้งานแล้ว ห้ามใช้ซ้ำ</p>
                </div>

            </div>
        </div>
    </div>
@endsection





