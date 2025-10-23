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
                        <div class="headerlogin"><h2>เข้าสู่ระบบ</h2></div>
                        <form method="POST" action="{{ route('customer.session.create') }}">
                            @csrf
                            <div>

                                <div class=" form-group">
                                    <div>
                                        <label> รหัสผู้ใช้งาน</label>
                                        <div class="el-input mt-1">
                                            <!----><input type="text" id="user_name" name="user_name" autocomplete="off"
                                                          placeholder="รหัสผู้ใช้งาน" class="inputstyle" minlength="10" maxlength="10" required>
                                            <!----><!----><!----><!---->
                                        </div>
                                        <!---->
                                    </div>
                                </div>


                                <div class="form-group">
                                    <div>
                                        <label>Pin ผู้ใช้งาน</label>
                                        <div class="el-input mt-1">
                                            <!----><input type="password" id="password" name="password"
                                                          placeholder="กรอก Pin ผู้ใช้งาน"
                                                          class="inputstyle" required><!----><!----><!----><!---->
                                        </div>
                                        <!---->
                                    </div>
                                </div>


                            </div>
                        <button type="submit" class="loginbtn">
                            <!----><!----><span>
      เข้าสู่ระบบ
      </span>
                        </button>
                        </form>
                        <div class="wantregister">ยังไม่มีบัญชี? <a href="{{ route('customer.session.store') }}">สมัครเลย</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection
