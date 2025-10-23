@extends('admin::layouts.master')

{{-- page title --}}
@section('title')
    {{ $menu->currentName }}
@endsection


@section('content')
    <section class="content text-xs" id="content">
        {{--        <div class="row">--}}
        {{--            <div class="col-12">--}}
        {{--                <div class="card card-primary">--}}

        {{--                    <form id="frmsearch" method="post" onsubmit="return false;">--}}
        {{--                        <div class="card-body">--}}
        {{--                            <div class="row">--}}
        {{--                                <div class="form-group col-12">--}}
        {{--                                    <div class="input-group">--}}
        {{--                                        <div class="input-group-prepend">--}}
        {{--                                            <span class="input-group-text"><i class="far fa-clock"></i></span>--}}
        {{--                                        </div>--}}
        {{--                                        <input type="text" class="form-control form-control-sm float-right"--}}
        {{--                                               id="search_date" readonly>--}}
        {{--                                        <input type="hidden" class="form-control float-right" id="startDate"--}}
        {{--                                               name="startDate">--}}
        {{--                                        <input type="hidden" class="form-control float-right" id="endDate"--}}
        {{--                                               name="endDate">--}}
        {{--                                    </div>--}}
        {{--                                </div>--}}

        {{--                                <div class="form-group col-6">--}}
        {{--                                    {!! Form::select('status', ['' => '== สถานะการเติม ==' , '1' => 'เติมสำเร็จ', '2' => 'รอดำเนินการ'], '',['id' => 'status', 'class' => 'form-control form-control-sm']) !!}--}}

        {{--                                </div>--}}
        {{--                                <div class="form-group col-auto">--}}
        {{--                                    <button class="btn btn-primary btn-sm"><i class="fa fa-search"></i> Search</button>--}}
        {{--                                </div>--}}
        {{--                                <div class="col-12">--}}
        {{--                                    <span class="text-danger">* TrueWallet ไม่มีรายการใหม่ แต่ยอดเงินยังอัพเดท เวลาล่าสุด ปัญหานี้เป็นที่ SV ของ TrueWallet ซึ่งทีมงาน Gametech ไม่สามารถแก้ไขได้ เมื่อทาง ผู้ให้บริการ SV TrueWallet แก้ไขหรือดำเนินการเสร็จสิ้น รายการเติมเงินก็จะเข้าเอง อัตโนมัติ</span>--}}
        {{--                                </div>--}}
        {{--                            </div>--}}
        {{--                        </div>--}}
        {{--                    </form>--}}

        {{--                </div>--}}
        {{--                <!-- /.info-box -->--}}
        {{--            </div>--}}
        {{--        </div>--}}

        <div class="card">

            <div class="card-body">
                @includeIf('admin::module.'.$menu->currentRoute.'.create')
                @include('admin::module.'.$menu->currentRoute.'.table')
                @includeIf('admin::module.'.$menu->currentRoute.'.addedit')
            </div>
            <!-- /.card-body -->
        </div>

    </section>

@endsection

