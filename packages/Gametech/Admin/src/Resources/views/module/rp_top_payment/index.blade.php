@extends('admin::layouts.master')

{{--page title--}}
@section('title')
    {{ $menu->currentName }}
@endsection


@section('content')
    <section class="content text-xs">

        <div class="row">
            <div class="col-12">
                <div class="card card-primary">

                    <form id="frmsearch" method="post" onsubmit="return false;" class="frmsearch">
                        <div class="card-body">
                            <div class="row">
                                <div class="form-group col-12">
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="far fa-clock"></i></span>
                                        </div>
                                        <input type="text" class="form-control form-control-sm float-right"
                                               id="search_date" readonly>
                                        <input type="hidden" class="form-control float-right" id="startDate"
                                               name="startDate">
                                        <input type="hidden" class="form-control float-right" id="endDate"
                                               name="endDate">
                                    </div>
                                </div>

                                <div class="form-group col-6">
                                    <input type="text" class="form-control form-control-sm" id="user_name"
                                           placeholder="Username"
                                           name="user_name">
                                </div>


                                <div class="form-group col-6"></div>

                                <div class="form-group col-auto">
                                    <button class="btn btn-primary btn-sm"><i class="fa fa-search"></i> Search</button>
                                </div>
                            </div>
                        </div>
                    </form>

                </div>
                <!-- /.info-box -->
            </div>
        </div>

        <div class="row">
            <div class="col-6">
                <div class="card">
                    <div class="card-header">รายการฝาก</div>
                    <div class="card-body">
                        {{--                        @includeIf('admin::module.'.$menu->currentRoute.'.create')--}}
                        @include('admin::module.'.$menu->currentRoute.'.table')
                        {{--                        @includeIf('admin::module.'.$menu->currentRoute.'.addedit')--}}
                    </div>
                    <!-- /.card-body -->
                </div>
            </div>
            <div class="col-6">
                <div class="card">
                    <div class="card-header">รายการถอน</div>
                    <div class="card-body">
                        @include('admin::module.'.$menu->currentRoute.'.table2')
                    </div>
                    <!-- /.card-body -->
                </div>
            </div>
        </div>
    </section>

@endsection

