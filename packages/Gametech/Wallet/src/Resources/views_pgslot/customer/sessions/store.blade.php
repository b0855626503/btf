{{-- extend layout --}}
@extends('wallet::layouts.app')

{{-- page title --}}
@section('title','')


@section('content')

    <div class="containerlogin">
        <div class="incontainlogin">
            <div class="row m-0">
                <div class="col-12 col-md-6 p-0 px-4 pb-2 logoleftlogin">
                    <img src="{{ url(core()->imgurl($config->logo,'img')) }}">
                </div>
                <div class="col-12 col-md-6 p-0 pt-4 px-4">
                    <div>
                        <div class="headerlogin">
                            <h2>สมัครสมาชิก</h2>
                        </div>
                        <div class="stepregister">
                            <div class="stepregis step01 active">1</div>
                            <div class="stepregis step02">2</div>
                            <div class="stepregis step03">3</div>
                            <div class="stepregis step04"><i class="far fa-check"></i></div>
                        </div>
                        <form id="frmregister">
                            <div class="regisstep re01">
                                <div class=" form-group">
                                    <div>
                                        <label> เบอร์มือถือ</label>
                                        <div class="el-input mt-1">
                                            <!----><input type="text" placeholder="เบอร์โทร" class="inputstyle" id="user_name" name="user_name" minlength="10" maxlength="10" required><!----><!----><!----><!---->
                                        </div>
                                        <!---->
                                    </div>
                                </div>
                                <button type="button" class="loginbtn" id="btnstep01">
                                    ถัดไป
                                </button>
                            </div>
                            <div class="regisstep re02">
                                <div class="form-group">
                                    <div>
                                        <label>รหัส ผู้ใช้งาน </label>
                                        <div class="el-input mt-1">
                                            <!----><input type="password" minlength="4" maxlength="10" placeholder="กรอก รหัส ผู้ใช้งาน" class="inputstyle" id="password" name="password" required><!----><!----><!----><!---->
                                        </div>
                                        <!---->
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div>
                                        <label>ยืนยัน รหัส ผู้ใช้งาน</label>
                                        <div class="el-input mt-1">
                                            <!----><input type="password" minlength="4" maxlength="10" placeholder="ยืนยัน รหัส ผู้ใช้งาน" class="inputstyle" id="password_confirmation" name="password_confirmation" required><!----><!----><!----><!---->
                                        </div>
                                        <!---->
                                    </div>
                                </div>
                                <button type="button" class="loginbtn" id="btnstep02">
                                    ถัดไป
                                </button>
                            </div>
                            <div class="regisstep re03">
                                <div class=" form-group">
                                    <div>
                                        <label> เลือกธนาคาร</label>
                                        <div class="x-bank-choices-type mt-1 mb-2">
                                            <div class="-outer-wrapper">
                                                @foreach($banks as $i => $bank)
                                                <input type="radio" class="-input-radio" id="bank-acc-{{ $bank->code }}" name="bank" value="{{ $bank->code }}">
                                                <label class="-label" for="bank-acc-{{ $bank->code }}">
                                                    <img class="-logo" src="{{ Storage::url('bank_img/' . $bank->filepic) }}" alt="">
                                                    <i class="fas fa-check"></i>
                                                </label>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class=" form-group">
                                    <div>
                                        <label> เลขบัญชีธนาคาร</label>
                                        <div class="el-input mt-1">
                                            <!----><input type="text" placeholder="เลขบัญชีธนาคาร" class="inputstyle" id="acc_no" name="acc_no" required><!----><!----><!----><!---->
                                        </div>
                                        <!---->
                                    </div>
                                </div>
                                <!-- <div class=" form-group">
                                   <div>
                                      <label> แหล่งที่มา</label>
                                      <select name="" class="inputstyle" id="">
                                         <option value="">แหล่งที่มา</option>
                                         <option value="gg">google</option>
                                         <option value="friend">เพื่อนแนะนำ</option>
                                         <option value="fb">facebook</option>
                                      </select>
                                   </div>
                                </div> -->

                                <div class=" form-group">
                                    <div>
                                        <label> ชื่อ</label>
                                        <div class="el-input mt-1">
                                            <input type="text" placeholder="ชื่อ" class="inputstyle" id="firstname" name="firstname" required>
                                        </div>
                                        <!---->
                                    </div>
                                </div>
                                <div class=" form-group">
                                    <div>
                                        <label> นามสกุล</label>
                                        <div class="el-input mt-1">
                                            <input type="text" placeholder="นามสกุล" class="inputstyle" id="lastname" name="lastname" required>
                                        </div>
                                        <!---->
                                    </div>
                                </div>
                                <button type="button" class="loginbtn" id="btnstep03">
                                    ถัดไป
                                </button>
                            </div>
                            <div class="regisstep re04 finishcontain">
                                สมัครสำเร็จ โปรดรอสักครู่..
                            </div>
                        </form>
                        <div class="wantregister">มีบัญชีผู้ใช้อยู่แล้ว? <a href="{{ route('customer.session.index') }}">เข้าสู่ระบบเลย</a></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

