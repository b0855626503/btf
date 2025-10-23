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
        <h3 class="text-center text-light">ฝากเงิน QR พร้อมเพย์</h3>
        <p class="text-center text-color-fixed">Deposit by QR พร้อมเพย์</p>
        <div class="row">
            <div class="col-md-8 offset-md-2 col-sm-12">
                <div class="card card-trans">
                    <div class="card-header text-center">
                        <p class="text-center text-color-fixed m-0">** ใช้ธนาคารที่สมัคร ตามรายละเอียดข้างล่างนี้ สแกนจ่ายมาเท่านั้น **</p>
                        <small>*** ถ้าสมัครมาเป็น Truewallet ไม่สามารถใช้บริการนี้ได้ ***</small>
                        <br>
                        <div class="card card-trans profile">
                            <div class="card-body">
                                <div class="align-items-center">
                                    <div class="row">
                                        <div class="col-6 col-sm-6">
                                            {!! (!is_null($profile->bank) ? core()->showImg($profile->bank->filepic,'bank_img','48px','48px','img-thumbnail rounded-circle m-2') : '') !!}
                                            <span>{{ (!is_null($profile->bank) ? $profile->bank->shortcode : '') }}</span>

                                        </div>
                                        <div class="col-5 col-sm-6">
                                            <div class="profile-txt mb-0">
                                                <p class="text-center mt-2 mb-0 align-middle text-color-fixed">
                                                    <i class="far fa-user-check"></i> เลขที่บัญชี</p>
                                                <h6 class="text-center mb-0 align-middle ">{{ $profile->acc_no }}</h6>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-2">
                        <form target="_blank" novalidate="" id="frmdeposit" name="deposit" method="post"
                              action="{{ route('customer.topup.superrich') }}">
                            @csrf
                            <input required
                                   type="number"
                                   step="0.01"
                                   min="100"
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
                    <p>ฝากขั้นต่ำ 100 บาท ถ้าต้องการน้อยกว่านั้น โปรดใช้ช่องทางเติมเงิน ธนาคาร</p>
                    <p>ใช้ธนาคารอื่นสแกน เงินจะไม่เข้า</p>
                    <p>QR Code เมื่อใช้งานแล้ว ห้ามใช้ซ้ำ</p>
                </div>

            </div>
        </div>
    </div>
@endsection





