@extends('admin::layouts.master')

{{-- page title --}}
@section('title','Dashboard')


@section('content')
    <!--suppress ALL -->
    <section class="content text-xs">
        {{--        <div class="container-fluid">--}}

{{--        <div class="row">--}}
{{--            @php--}}
{{--                $prem = bouncer()->hasPermission('dashboard.deposit');--}}
{{--            @endphp--}}
{{--            @if($prem)--}}
{{--                <div class="col-lg-3 col-6">--}}
{{--                    <deposit-slot ref="deposit"></deposit-slot>--}}
{{--                </div>--}}
{{--            @endif--}}

{{--            @php--}}
{{--                $prem = bouncer()->hasPermission('dashboard.withdraw');--}}
{{--            @endphp--}}
{{--            @if($prem)--}}
{{--                <div class="col-lg-3 col-6">--}}
{{--                    <withdraw-slot ref="withdraw"></withdraw-slot>--}}
{{--                </div>--}}
{{--            @endif--}}

{{--            @php--}}
{{--                $prem = bouncer()->hasPermission('dashboard.bonus');--}}
{{--            @endphp--}}
{{--            @if($prem)--}}
{{--                <div class="col-lg-3 col-6">--}}
{{--                    <bonus-slot ref="bonus"></bonus-slot>--}}
{{--                </div>--}}
{{--            @endif--}}

{{--            @php--}}
{{--                $prem = bouncer()->hasPermission('dashboard.balance');--}}
{{--            @endphp--}}
{{--            @if($prem)--}}
{{--                <div class="col-lg-3 col-6">--}}
{{--                    <balance-slot ref="balance"></balance-slot>--}}
{{--                </div>--}}
{{--            @endif--}}
{{--        </div>--}}

{{--        <div class="row">--}}
{{--            <div class="col">--}}
{{--                <div class="info-box">--}}
{{--                    <span class="info-box-icon bg-danger elevation-1"><i class="fas fa-users"></i></span>--}}

{{--                    <div class="info-box-content">--}}
{{--                        <span class="info-box-text">ออนไลน์</span>--}}
{{--                        <span class="info-box-number">--}}
{{--                   {{ OnlineUsers::getActiveUsers() ?? 0 }}--}}
{{--                  <small>คน</small>--}}
{{--                </span>--}}
{{--                    </div>--}}
{{--                    <!-- /.info-box-content -->--}}
{{--                </div>--}}
{{--            </div>--}}
{{--            @php--}}
{{--                $prem = bouncer()->hasPermission('dashboard.deposit_wait');--}}
{{--            @endphp--}}
{{--            @if($prem)--}}
{{--                <div class="col">--}}
{{--                    <deposit_wait-slot ref="deposit_wait"></deposit_wait-slot>--}}
{{--                </div>--}}
{{--            @endif--}}

{{--            @php--}}
{{--                $prem = bouncer()->hasPermission('dashboard.setdeposit');--}}
{{--            @endphp--}}
{{--            @if($prem)--}}
{{--                <div class="col">--}}
{{--                    <setdeposit-slot ref="setdeposit"></setdeposit-slot>--}}
{{--                </div>--}}
{{--            @endif--}}

{{--            @php--}}
{{--                $prem = bouncer()->hasPermission('dashboard.setwithdraw');--}}
{{--            @endphp--}}
{{--            @if($prem)--}}
{{--                <div class="col">--}}
{{--                    <setwithdraw-slot ref="setwithdraw"></setwithdraw-slot>--}}
{{--                </div>--}}
{{--            @endif--}}

{{--        </div>--}}

{{--        <div class="row">--}}
{{--            @php--}}
{{--                $prem = bouncer()->hasPermission('dashboard.income');--}}
{{--            @endphp--}}
{{--            @if($prem)--}}
{{--                <div class="col-lg-6">--}}
{{--                    <income-slot ref="income"></income-slot>--}}
{{--                </div>--}}
{{--            @endif--}}

{{--            @php--}}
{{--                $prem = bouncer()->hasPermission('dashboard.topup');--}}
{{--            @endphp--}}
{{--            @if($prem)--}}
{{--                <div class="col-lg-6">--}}
{{--                    <topup-slot ref="topup"></topup-slot>--}}
{{--                </div>--}}
{{--            @endif--}}
{{--        </div>--}}

{{--        <div class="row">--}}


{{--            @php--}}
{{--                $prem = bouncer()->hasPermission('dashboard.regis');--}}
{{--            @endphp--}}
{{--            @if($prem)--}}
{{--                <div class="col-lg-12">--}}
{{--                    <regis-slot ref="regis"></regis-slot>--}}
{{--                </div>--}}
{{--            @endif--}}

{{--        </div>--}}

        <div class="row">


                <div class="col-lg-6" style="min-height:350px">
                    <login-slot ref="login"></login-slot>
                </div>



                <div class="col-lg-6" style="min-height:350px">
                    <logout-slot ref="logout"></logout-slot>
                </div>

        </div>

        <div class="row">

            @php
                $prem = bouncer()->hasPermission('dashboard.bankin');
            @endphp
            @if($prem)
                <div class="col-lg-6" style="min-height:350px">
                    <bankin-slot ref="bankin"></bankin-slot>
                </div>
            @endif

            @php
                $prem = bouncer()->hasPermission('dashboard.bankout');
            @endphp
            @if($prem)
                <div class="col-lg-6" style="min-height:350px">
                    <bankout-slot ref="bankout"></bankout-slot>
                </div>
            @endif
            {{--            </div>--}}
        </div>
    </section>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('/vendor/chart.js/Chart.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('/vendor/chart.js/Chart.js') }}"></script>

    <script type="text/x-template" id="setdeposit-slot-template">

        <div class="info-box">
            <span class="info-box-icon bg-info elevation-1"><i class="fas fa-plus-circle"></i></span>

            <div class="info-box-content">
                <span class="info-box-text">ทีมงานเพิ่ม ยอดเงิน</span>
                <span class="info-box-number">
                  @{{ sum }}
                  <small>บาท</small>
                </span>
            </div>
            <!-- /.info-box-content -->
        </div>

    </script>

    <script type="text/x-template" id="setwithdraw-slot-template">

        <div class="info-box">
            <span class="info-box-icon bg-danger elevation-1"><i class="fas fa-minus-circle"></i></span>

            <div class="info-box-content">
                <span class="info-box-text">ทีมงานลด ยอดเงิน</span>
                <span class="info-box-number">
                  @{{ sum }}
                  <small>บาท</small>
                </span>
            </div>
            <!-- /.info-box-content -->
        </div>

    </script>

    <script type="text/x-template" id="deposit-slot-template">

        <div class="info-box">
            <span class="info-box-icon bg-info elevation-1"><i class="fas fa-plus-circle"></i></span>

            <div class="info-box-content">
                <span class="info-box-text">ยอดฝาก</span>
                <span class="info-box-number">
                  @{{ sum }}
                  <small>บาท</small>
                </span>
            </div>
            <!-- /.info-box-content -->
        </div>

    </script>



    <script type="text/x-template" id="deposit_wait-slot-template">

        <div class="info-box">
            <span class="info-box-icon bg-warning elevation-1"><i class="fas fa-times-circle"></i></span>

            <div class="info-box-content">
                <span class="info-box-text">ยอดฝาก (มีปัญหา)</span>
                <span class="info-box-number">
                  @{{ sum }}
                  <small>บาท</small>
                </span>
            </div>
            <!-- /.info-box-content -->
        </div>

    </script>

    <script type="text/x-template" id="withdraw-slot-template">
        <div class="info-box">
            <span class="info-box-icon bg-danger elevation-1"><i class="fas fa-minus-circle"></i></span>

            <div class="info-box-content">
                <span class="info-box-text">ยอดถอน</span>
                <span class="info-box-number">
                  @{{ sum }}
                  <small>บาท</small>
                </span>
            </div>
            <!-- /.info-box-content -->
        </div>

    </script>

    <script type="text/x-template" id="bonus-slot-template">
        <div class="info-box">
            <span class="info-box-icon bg-warning elevation-1"><i class="fas fa-gift"></i></span>

            <div class="info-box-content">
                <span class="info-box-text">ยอดรับโปร</span>
                <span class="info-box-number">
                  @{{ sum }}
                  <small>บาท</small>
                </span>
            </div>
            <!-- /.info-box-content -->
        </div>

    </script>

    <script type="text/x-template" id="balance-slot-template">
        <div class="info-box">
            <span class="info-box-icon bg-success elevation-1"><i class="fas fa-dollar-sign"></i></span>

            <div class="info-box-content">
                <span class="info-box-text">ยอดคงเหลือ</span>
                <span class="info-box-number">
                  @{{ sum }}
                  <small>บาท</small>
                </span>
            </div>
            <!-- /.info-box-content -->
        </div>

    </script>

    <script type="text/x-template" id="income-slot-template">
        <div class="card">
            <div class="card-header border-0">
                <div class="d-flex justify-content-between">
                    <h3 class="card-title">รายได้ 7 วันหลังสุด</h3>
                </div>
            </div>
            <div class="card-body">
                <div class="position-relative mb-4">
                    <canvas id="income-chart" height="200"></canvas>
                </div>
            </div>
        </div>

    </script>

    <script type="text/x-template" id="topup-slot-template">
        <div class="card">
            <div class="card-header border-0">
                <div class="d-flex justify-content-between">
                    <h3 class="card-title">เติมเงิน 7 วันหลังสุด</h3>
                </div>
            </div>
            <div class="card-body">
                <div class="position-relative mb-4">
                    <canvas id="topup-chart" height="200"></canvas>
                </div>
            </div>
        </div>

    </script>

    <script type="text/x-template" id="regis-slot-template">
        <div class="card">
            <div class="card-header border-0">
                <div class="d-flex justify-content-between">
                    <h3 class="card-title">สมาชิกใหม่ ย้อนหลัง 7 วัน</h3>
                </div>
            </div>
            <div class="card-body">
                <div class="position-relative mb-4">
                    <canvas id="regis-chart" height="100"></canvas>
                </div>
            </div>
        </div>

    </script>

    <script type="text/x-template" id="bankin-slot-template">
        <div class="card">
            <div class="card-header border-0">
                <div class="d-flex justify-content-between">
                    <h3 class="card-title">บัญชีเงินเข้า
                        <br> <small class="text-danger">* แสดงรายการที่เปิดใช้งาน และ เปิดดึงยอด</small>
                        <br> <small class="text-danger">* SCB Api ไม่ดึงรายการ ลองเข้าแอป กดดูรายการ แล้วกดออก / ถ้าขึ้นให้ติดต่อธนาคารก็ โปรดติดต่อ / อื่นๆ ติดต่อ Support </small>
                        <br> <small class="text-danger">* Kbank Kbiz ปกติ ไม่ดึงรายการ โปรดเข้าเวบ Kbiz เพื่อตรวจสอบ ว่าติด Capcha หรือ มีอะไรแปลกไหม / หรือโปรดกด ปุ่มเขียวตรง รีเฟรช หรือ รอสักพัก ก็จะดึงตามปกติ</small>

                    </h3>

                </div>
            </div>
            <div class="card-body">
                <b-table striped hover small outlined show-empty v-bind:items="loadData" :fields="fields" :busy="isBusy"
                         ref="tbdatalog" v-if="show">
                    <template #table-busy>
                        <div class="text-center text-danger my-2">
                            <b-spinner class="align-middle"></b-spinner>
                            <strong>Loading...</strong>
                        </div>
                    </template>
                    <template #cell(bank)="data">
                        <span v-html="data.value"></span>
                    </template>
                    <template #cell(login)="data">
                        <span v-html="data.value"></span>
                    </template>
                </b-table>
            </div>
        </div>

    </script>

    <script type="text/x-template" id="bankout-slot-template">
        <div class="card">
            <div class="card-header border-0">
                <div class="d-flex justify-content-between">
                    <h3 class="card-title">บัญชีเงินออก
                        <br> <small class="text-danger">* ยอดเงินจะแสดงเมื่อ ทำรายการถอนออโต้</small>
                        <br> <small class="text-danger">* ต้องการอัพเดทยอดเงิน กดปุ่มที่ช่อง รีเฟรช</small>
                    </h3>
                </div>
            </div>
            <div class="card-body">
                <b-table striped hover small outlined show-empty v-bind:items="loadData" :fields="fields" :busy="isBusy"
                         ref="tbdatalog" v-if="show">
                    <template #table-busy>
                        <div class="text-center text-danger my-2">
                            <b-spinner class="align-middle"></b-spinner>
                            <strong>Loading...</strong>
                        </div>
                    </template>
                    <template #cell(bank)="data">
                        <span v-html="data.value"></span>
                    </template>
                    <template #cell(login)="data">
                        <span v-html="data.value"></span>
                    </template>
                </b-table>
            </div>
        </div>

    </script>

    <script type="text/x-template" id="login-slot-template">
        <div class="card">
            <div class="card-header border-0">
                <div class="d-flex justify-content-between">
                    <h3 class="card-title">Admin Login
                    </h3>
                </div>
            </div>
            <div class="card-body">
                <b-table striped hover small outlined show-empty v-bind:items="loadData" :fields="fields" :busy="isBusy"
                         ref="tbdatalog" v-if="show">
                    <template #table-busy>
                        <div class="text-center text-danger my-2">
                            <b-spinner class="align-middle"></b-spinner>
                            <strong>Loading...</strong>
                        </div>
                    </template>
                </b-table>
            </div>
        </div>

    </script>

    <script type="text/x-template" id="logout-slot-template">
        <div class="card">
            <div class="card-header border-0">
                <div class="d-flex justify-content-between">
                    <h3 class="card-title">Admin Logout
                    </h3>
                </div>
            </div>
            <div class="card-body">
                <b-table striped hover small outlined show-empty v-bind:items="loadData" :fields="fields" :busy="isBusy"
                         ref="tbdatalog" v-if="show">
                    <template #table-busy>
                        <div class="text-center text-danger my-2">
                            <b-spinner class="align-middle"></b-spinner>
                            <strong>Loading...</strong>
                        </div>
                    </template>
                </b-table>
            </div>
        </div>

    </script>

    <script>
        function editdata(id, status, method) {
            window.app.editdata(id, status, method);
        }
    </script>
    <script type="module">

        import to from "./js/toPromise.js";

        Vue.component('setdeposit-slot', {
            template: '#setdeposit-slot-template',
            data: function () {
                return {
                    sum: 0
                }
            },
            mounted() {
                this.loadData();
            },
            methods: {
                async loadData() {
                    let err, result;
                    [err, result] = await to(axios.post("{{ route('admin.dashboard.loadsum') }}", {method: 'setdeposit'}));
                    if (err) {
                        return 0;
                    }
                    this.sum = result.data.sum;
                    return this.sum;

                }
            }
        });

        Vue.component('setwithdraw-slot', {
            template: '#setwithdraw-slot-template',
            data: function () {
                return {
                    sum: 0
                }
            },
            mounted() {
                this.loadData();
            },
            methods: {
                async loadData() {
                    let err, result;
                    [err, result] = await to(axios.post("{{ route('admin.dashboard.loadsum') }}", {method: 'setwithdraw'}));
                    if (err) {
                        return 0;
                    }
                    this.sum = result.data.sum;
                    return this.sum;
                }
            }
        });


        Vue.component('deposit-slot', {
            template: '#deposit-slot-template',
            data: function () {
                return {
                    sum: 0
                }
            },
            mounted() {
                this.loadData();
            },
            methods: {
                async loadData() {
                    let err, result;
                    [err, result] = await to(axios.post("{{ route('admin.dashboard.loadsum') }}", {method: 'deposit'}));
                    if (err) {
                        return 0;
                    }
                    this.sum = result.data.sum;
                    return this.sum;
                }
            }
        });

        Vue.component('deposit_wait-slot', {
            template: '#deposit_wait-slot-template',
            data: function () {
                return {
                    sum: 0
                }
            },
            mounted() {
                this.loadData();
            },
            methods: {
                async loadData() {
                    let err, result;
                    [err, result] = await to(axios.post("{{ route('admin.dashboard.loadsum') }}", {method: 'deposit_wait'}));
                    if (err) {
                        return 0;
                    }
                    this.sum = result.data.sum;
                    return this.sum;
                }
            }
        });

        Vue.component('withdraw-slot', {
            template: '#withdraw-slot-template',

            data: function () {
                return {
                    sum: 0
                }
            },
            mounted() {
                this.loadData();
            },
            methods: {
                async loadData() {
                    let err, result;
                    [err, result] = await to(axios.post("{{ route('admin.dashboard.loadsum') }}", {method: 'withdraw'}));
                    if (err) {
                        return 0;
                    }
                    this.sum = result.data.sum;
                    return this.sum;
                }
            }
        });

        Vue.component('bonus-slot', {
            template: '#bonus-slot-template',

            data: function () {
                return {
                    sum: 0
                }
            },
            mounted() {
                this.loadData();
            },
            methods: {
                async loadData() {
                    let err, result;
                    [err, result] = await to(axios.post("{{ route('admin.dashboard.loadsum') }}", {method: 'bonus'}));
                    if (err) {
                        return 0;
                    }
                    this.sum = result.data.sum;
                    return this.sum;
                }
            }
        });

        Vue.component('balance-slot', {
            template: '#balance-slot-template',

            data: function () {
                return {
                    sum: 0
                }
            },
            mounted() {
                this.loadData();
            },
            methods: {
                async loadData() {
                    let err, result;
                    [err, result] = await to(axios.post("{{ route('admin.dashboard.loadsum') }}", {method: 'balance'}));
                    if (err) {
                        return 0;
                    }
                    this.sum = result.data.sum;
                    return this.sum;
                }
            }
        });

        Vue.component('income-slot', {
            template: '#income-slot-template',

            data: function () {
                return {
                    chart: '',
                }
            },
            mounted() {
                this.chart = $('#income-chart');
                this.loadData();
            },
            methods: {
                async loadData() {
                    let err, res;
                    [err, res] = await to(axios.post("{{ route('admin.dashboard.loadsumall') }}", {method: 'income'}));
                    if (err) {
                        return 0;
                    }

                    let ctx = this.chart;
                    new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: res.data.label,
                            datasets: [{
                                label: 'ฝาก ',
                                data: res.data.line_deposit,

                                borderColor: [
                                    'rgba(0,51,255,1)',
                                    'rgba(0,51,255,1)',
                                    'rgba(0,51,255,1)',
                                    'rgba(0,51,255,1)',
                                    'rgba(0,51,255,1)',
                                    'rgba(0,51,255,1)',
                                    'rgba(0,51,255,1)'
                                ],
                                borderWidth: 1
                            }, {
                                label: 'ถอน ',
                                data: res.data.line_withdraw,

                                borderColor: [
                                    'rgba(255,0,0,1)',
                                    'rgba(255,0,0,1)',
                                    'rgba(255,0,0,1)',
                                    'rgba(255,0,0,1)',
                                    'rgba(255,0,0,1)',
                                    'rgba(255,0,0,1)',
                                    'rgba(255,0,0,1)'
                                ],
                                borderWidth: 1
                            }, {
                                label: 'โปร ',
                                data: res.data.line_bonus,

                                borderColor: [
                                    'rgba(255,193,0,1)',
                                    'rgba(255,193,0,1)',
                                    'rgba(255,193,0,1)',
                                    'rgba(255,193,0,1)',
                                    'rgba(255,193,0,1)',
                                    'rgba(255,193,0,1)',
                                    'rgba(255,193,0,1)',
                                ],
                                borderWidth: 1
                            }, {
                                label: 'คงเหลือ ',
                                data: res.data.line_balance,

                                borderColor: [
                                    'rgba(0,153,0,1)',
                                    'rgba(0,153,0,1)',
                                    'rgba(0,153,0,1)',
                                    'rgba(0,153,0,1)',
                                    'rgba(0,153,0,1)',
                                    'rgba(0,153,0,1)',
                                    'rgba(0,153,0,1)'
                                ],
                                borderWidth: 1
                            }]
                        },
                        options: {
                            scales: {
                                yAxes: [{
                                    ticks: {
                                        beginAtZero: true
                                    }
                                }]
                            }
                        }
                    });

                }
            }
        });

        Vue.component('topup-slot', {
            template: '#topup-slot-template',

            data: function () {
                return {
                    chart: '',
                }
            },
            mounted() {
                this.chart = $('#topup-chart');
                this.loadData();
            },
            methods: {
                async loadData() {
                    let err, res;
                    [err, res] = await to(axios.post("{{ route('admin.dashboard.loadsumall') }}", {method: 'topup'}));
                    if (err) {
                        return 0;
                    }
                    let ctx = this.chart;
                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: res.data.label,
                            datasets: [{
                                label: 'เติมเงิน ',
                                data: res.data.bar,

                                backgroundColor: [
                                    'rgba(0,51,255,1)',
                                    'rgba(0,51,255,1)',
                                    'rgba(0,51,255,1)',
                                    'rgba(0,51,255,1)',
                                    'rgba(0,51,255,1)',
                                    'rgba(0,51,255,1)',
                                    'rgba(0,51,255,1)'
                                ],
                                borderWidth: 1
                            }]
                        },
                        options: {
                            scales: {
                                yAxes: [{
                                    ticks: {
                                        beginAtZero: true
                                    }
                                }]
                            }
                        }
                    });

                }
            }
        });

        Vue.component('regis-slot', {
            template: '#regis-slot-template',

            data: function () {
                return {
                    chart: '',
                }
            },
            mounted() {
                this.chart = $('#regis-chart');
                this.loadData();
            },
            methods: {
                async loadData() {
                    let err, res;
                    [err, res] = await to(axios.post("{{ route('admin.dashboard.loadsumall') }}", {method: 'register'}));
                    if (err) {
                        return 0;
                    }
                    let ctx = this.chart;
                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: res.data.label,
                            datasets: [{
                                label: 'สมาชิกใหม่ ',
                                data: res.data.bar,

                                backgroundColor: [
                                    'rgba(0,51,0,1)',
                                    'rgba(0,51,0,1)',
                                    'rgba(0,51,0,1)',
                                    'rgba(0,51,0,1)',
                                    'rgba(0,51,0,1)',
                                    'rgba(0,51,0,1)',
                                    'rgba(0,51,0,1)'
                                ],
                                borderWidth: 1
                            }]
                        },
                        options: {
                            scales: {
                                yAxes: [{
                                    ticks: {
                                        beginAtZero: true
                                    }
                                }]
                            }
                        }
                    });
                }
            }
        });

        Vue.component('bankin-slot', {
            template: '#bankin-slot-template',

            data: function () {
                return {
                    show: true,
                    isBusy: false,
                    fields: [],
                    items: [],
                }
            },
            mounted() {

            },
            methods: {
                async loadData() {
                    let err, res;
                    [err, res] = await to(axios.post("{{ route('admin.dashboard.loadbank') }}", {method: 'bankin'}));
                    if (err) {
                        return 0;
                    }
                    this.fields = [
                        {key: 'bank', label: 'ธนาคาร'},
                        {key: 'acc_name', label: 'ชื่อบัญชี'},
                        {key: 'acc_no', label: 'เลขที่บัญชี'},
                        {key: 'balance', label: 'ยอดเงิน', class: 'text-right'},
                        {key: 'status', label: 'สถานะ', class: 'text-center'},
                        {key: 'date_update', label: 'อัพเดทเมื่อ', class: 'text-center'},
                        {key: 'login', label: 'รีเฟรช', class: 'text-center'},
                    ];

                    this.items = res.data.list;
                    return this.items;
                }
            }
        });

        Vue.component('bankout-slot', {
            template: '#bankout-slot-template',

            data: function () {
                return {
                    show: true,
                    isBusy: false,
                    fields: [],
                    items: [],
                }
            },
            mounted() {

            },
            methods: {
                async loadData() {
                    let err, res;
                    [err, res] = await to(axios.post("{{ route('admin.dashboard.loadbank') }}", {method: 'bankout'}));
                    if (err) {
                        return 0;
                    }

                    this.fields = [
                        {key: 'bank', label: 'ธนาคาร'},
                        {key: 'acc_name', label: 'ชื่อบัญชี'},
                        {key: 'acc_no', label: 'เลขที่บัญชี'},
                        {key: 'balance', label: 'ยอดเงิน', class: 'text-right'},
                        {key: 'date_update', label: 'อัพเดทเมื่อ', class: 'text-center'},
                        {key: 'login', label: 'รีเฟรช', class: 'text-center'},
                    ];

                    this.items = res.data.list;
                    return this.items;
                }
            }
        });

        Vue.component('login-slot', {
            template: '#login-slot-template',

            data: function () {
                return {
                    show: true,
                    isBusy: false,
                    fields: [],
                    items: [],
                }
            },
            mounted() {

            },
            methods: {
                async loadData() {
                    let err, res;
                    [err, res] = await to(axios.post("{{ route('admin.dashboard.loadlogin')  }}", {method: 'login'}));
                    if (err) {
                        return 0;
                    }

                    this.fields = [
                        {key: 'user_name', label: 'Admin'},
                        {key: 'date_update', label: 'วัน-เวลา', class: 'text-center'},
                        {key: 'ip', label: 'IP', class: 'text-center'},
                    ];

                    this.items = res.data.list;
                    return this.items;
                }
            }
        });

        Vue.component('logout-slot', {
            template: '#logout-slot-template',

            data: function () {
                return {
                    show: true,
                    isBusy: false,
                    fields: [],
                    items: [],
                }
            },
            mounted() {

            },
            methods: {
                async loadData() {
                    let err, res;
                    [err, res] = await to(axios.post("{{ route('admin.dashboard.loadlogin') }}", {method: 'logout'}));
                    if (err) {
                        return 0;
                    }

                    this.fields = [
                        {key: 'user_name', label: 'Admin'},
                        {key: 'date_update', label: 'วัน-เวลา', class: 'text-center'},
                        {key: 'ip', label: 'IP', class: 'text-center'},
                    ];

                    this.items = res.data.list;
                    return this.items;
                }
            }
        });

    </script>
    <script>
        window.app = new Vue({
            data: function () {
                return {
                    loopcnts: 0,
                    announce: '',
                    pushmenu: '',
                    toast: '',
                    withdraw_cnt: 0,
                    played: false
                }
            },
            created() {
                const self = this;
                self.autoCnt(false);
            },
            watch: {
                withdraw_cnt: function (event) {
                    if (event > 0) {
                        this.ToastPlay();
                    }
                }
            },
            methods: {
                editdata(code, status, method) {

                    this.$bvModal.msgBoxConfirm('ต้องการดำเนินการ ใช่หรือไม่.', {
                        title: 'โปรดยืนยันการทำรายการ',
                        size: 'sm',
                        buttonSize: 'sm',
                        okVariant: 'danger',
                        okTitle: 'ตกลง',
                        cancelTitle: 'ยกเลิก',
                        footerClass: 'p-2',
                        hideHeaderClose: false,
                        centered: true
                    })
                        .then(value => {
                            if (value) {
                                this.$http.post("{{ url($menu->currentRoute.'/edit') }}", {
                                    id: code,
                                    status: status,
                                    method: method
                                })
                                    .then(response => {
                                        this.$bvModal.msgBoxOk(response.data.message, {
                                            title: 'ผลการดำเนินการ',
                                            size: 'sm',
                                            buttonSize: 'sm',
                                            okVariant: 'success',
                                            headerClass: 'p-2 border-bottom-0',
                                            footerClass: 'p-2 border-top-0',
                                            centered: true
                                        });
                                        window.LaravelDataTables["dataTableBuilder"].draw(false);
                                    })
                                    .catch(exception => {
                                        console.log('error');
                                    });
                            }
                        })
                        .catch(err => {
                            // An error occurred
                        })

                },
                autoCnt(draw) {
                    const self = this;
                    this.toast = window.Toasty;
                    // this.toast = window.Toasty({
                    //     classname: "toast",
                    //     transition: "fade",
                    //     insertBefore: true,
                    //     duration: 1000,
                    //     enableSounds: true,
                    //     autoClose: true,
                    //     progressBar: true,
                    //     sounds: {
                    //         info: "storage/sound/alert.mp3",
                    //         success: "storage/sound/alert.mp3",
                    //         warning: "storage/sound/alert.mp3",
                    //         error: "storage/sound/alert.mp3",
                    //     }
                    // });
                    this.loadCnt();

                    // setInterval(function () {
                    //     self.loadCnt();
                    //     self.loopcnts++;
                    //     // self.$refs.deposit.loadData();
                    // }, 100000);

                },

                runMarquee() {
                    this.announce = $('#announce');
                    this.announce.marquee({
                        duration: 20000,
                        startVisible: false
                    });
                },
                ToastPlay() {

                    this.toast.error('<span class="text-danger">มีการถอนรายการใหม่</span>');
                },
                async loadCnt() {
                    let err, response;
                    [err, response] = await axios.get("{{ route('admin.home.loadcnt') }}").then(data => {
                        return [null, data];
                    }).catch(err => [err]);
                    if (err) {
                        return 0;
                    }

                    if (document.getElementById('badge_bank_in')) {
                        document.getElementById('badge_bank_in').textContent =  response.data.bank_in;
                    }
                    if (document.getElementById('badge_bank_out')) {
                        document.getElementById('badge_bank_out').textContent = response.data.bank_out;
                    }
                    if (document.getElementById('badge_withdraw')) {
                        document.getElementById('badge_withdraw').textContent = response.data.withdraw;
                    }
                    if (document.getElementById('badge_withdraw_seamless')) {
                        document.getElementById('badge_withdraw_seamless').textContent = response.data.withdraw;
                    }
                    if (document.getElementById('badge_withdraw_free')) {
                        document.getElementById('badge_withdraw_free').textContent = response.data.withdraw_free;
                    }
                    if (document.getElementById('badge_withdraw_seamless_free')) {
                        document.getElementById('badge_withdraw_seamless_free').textContent = response.data.withdraw_free;
                    }
                    if (document.getElementById('badge_confirm_wallet')) {
                        document.getElementById('badge_confirm_wallet').textContent = response.data.payment_waiting;
                    }
                    if (document.getElementById('badge_member_confirm')) {
                        document.getElementById('badge_member_confirm').textContent = response.data.member_confirm;
                    }

                    if (this.loopcnts == 0) {
                        document.getElementById('announce').textContent = response.data.announce;
                        this.runMarquee();
                    } else {
                        if (response.data.announce_new == 'Y') {
                            this.announce.on('finished', (event) => {
                                document.getElementById('announce').textContent = response.data.announce;
                                this.announce.trigger('destroy');
                                this.announce.off('finished');
                                this.runMarquee();
                            });

                        }
                    }

                    this.withdraw_cnt = response.data.withdraw;

                }
            }
        });
    </script>
@endpush



