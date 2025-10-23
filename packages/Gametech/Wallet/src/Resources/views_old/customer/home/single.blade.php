<section class="main-menu">
    <div class="card card-trans">
        <div class="card-body py-1">
            <div class="row">
                @if($config->pompay === 'Y')
                    <div class="col-4 main-menu-item px-0">
                        <a href="{{ route('customer.topup.index_pompay') }}"><i
                                    class="fal fa-wallet fa-2x"></i><br>
                            <span class="text-main"> เติมเงิน PomPay</span>
                        </a>
                    </div>
                @endif
                @if($config->hengpay === 'Y')
                    <div class="col-4 main-menu-item px-0">
                        <a href="{{ route('customer.topup.index_hengpay') }}"><i
                                    class="fal fa-wallet fa-2x"></i><br>
                            <span class="text-main"> เติมเงิน HengPay</span>
                        </a>
                    </div>
                @endif
                @if($config->luckypay === 'Y')
                    {{--                        @auth--}}
                    {{--                            @if($userdata->user_name == 'boatjunior')--}}
                    <div class="col-4 main-menu-item px-0">
                        <a href="{{ route('customer.topup.index_luckypay') }}"><i
                                    class="fal fa-wallet fa-2x"></i><br>
                            <span class="text-main"> เติมเงิน LuckyPay</span>
                        </a>
                    </div>
                    {{--                                @endif--}}
                    {{--                        @endauth--}}
                @endif
                @if($config->papayapay === 'Y')
                    {{--                        @auth--}}
                    {{--                            @if($userdata->user_name == 'boatjunior')--}}
                    <div class="col-4 main-menu-item px-0">
                        <a href="{{ route('customer.topup.index_papayapay') }}"><i
                                    class="fal fa-wallet fa-2x"></i><br>
                            <span class="text-main"> เติมเงิน QR พร้อมเพย์</span>
                        </a>
                    </div>
                    {{--                                @endif--}}
                    {{--                        @endauth--}}
                @endif
                <div class="col-4 main-menu-item px-0">
                    <a href="{{ route('customer.topup.index') }}"><i
                                class="fal fa-wallet fa-2x"></i><br>
                        <span class="text-main"> เติมเงิน</span>
                    </a>
                </div>
                <div class="col-4 main-menu-item px-0">
                    <a href="{{ route('customer.withdraw.index') }}"><i
                                class="fas fa-hand-holding-usd fa-2x"></i><br>
                        <span class="text-main"> ถอนเงิน</span>
                    </a>
                </div>
                @if($config->freecredit_open === 'Y' && $profile->freecredit === 'Y')
                    <div class="col-4 main-menu-item px-0">
                        <a href="{{ route('customer.credit.index') }}"><i
                                    class="fas fa-coins fa-2x"></i><br>
                            <span class=" text-main"> Cashback</span>
                        </a>
                    </div>
                @endif
                <div class="col-4 main-menu-item px-0">
                    <a href="{{ route('customer.history.index') }}"><i
                                class="fal fa-history fa-2x"></i><br>
                        <span class="text-main"> ประวัติธุรกรรม</span>
                    </a>
                </div>

                @if($config->pro_onoff === 'Y')
                    <div class="col-4 main-menu-item px-0">
                        <a href="{{ route('customer.promotion.index') }}"><i
                                    class="fal fa-gift fa-2x"></i><br>
                            <span class="text-main"> โปรโมชั่น</span>
                        </a>
                    </div>
                @endif


                <div class="col-4 main-menu-item px-0">
                    <a href="{{ route('customer.profile.index') }}"><i
                                class="fal fa-user fa-2x"></i><br>
                        <span class="text-main"> บัญชี</span>
                    </a>
                </div>


                <div class="col-4 main-menu-item px-0">
                    <a href="{{ route('customer.contributor.index') }}"><i
                                class="fas fa-hands-helping fa-2x"></i><br>
                        <span class="text-main"> แนะนำเพื่อน</span>
                    </a>
                </div>


                @if($config->wheel_open === 'Y')
                    <div class="col-4 main-menu-item px-0">
                        <a href="{{ route('customer.spin.index') }}"><i
                                    class="fas fa-bullseye fa-2x"></i><br>
                            <span class="text-main"> หมุนวงล้อ</span>
                        </a>
                    </div>
                    <div class="col-4 main-menu-item px-0">
                        <a href="{{ route('customer.spin_history.index') }}"><i
                                    class="fas fa-history fa-2x"></i><br>
                            <span class="text-main"> ประวัติวงล้อ</span>
                        </a>
                    </div>
                @endif

                @if($config->point_open === 'Y' && $config->reward_open === 'Y')
                    <div class="col-4 main-menu-item px-0">
                        <a href="{{ route('customer.reward.index') }}"><i
                                    class="fal fa-treasure-chest fa-2x"></i><br>
                            <span class="text-main"> แลกรางวัล</span>
                        </a>
                    </div>
                    <div class="col-4 main-menu-item px-0">
                        <a href="{{ route('customer.reward_history.index') }}"><i
                                    class="fal fa-history fa-2x"></i><br>
                            <span class="text-main"> ประวัติการแลก</span>
                        </a>
                    </div>
                @endif

                <couponlist></couponlist>
                <bonuslist ref="bonus"></bonuslist>

                {{--                                <div class="col-4 main-menu-item px-0">--}}
                {{--                                    <a href="{{ route('customer.manual.index') }}"><i--}}
                {{--                                            class="fas fa-book fa-2x"></i><br>--}}
                {{--                                        <span class="text-main"> คู่มือ</span>--}}
                {{--                                    </a>--}}
                {{--                                </div>--}}

                {{--                <div class="col-4 main-menu-item px-0">--}}
                {{--                    <a href="{{ route('customer.download.index') }}"><i--}}
                {{--                            class="fas fa-download fa-2x"></i><br>--}}
                {{--                        <span class="text-main"> ดาวน์โหลด</span>--}}
                {{--                    </a>--}}
                {{--                </div>--}}

                @if($games['game']['autologin'] == 'Y')
                    <div class="col-4 main-menu-item px-0">
                        <a href="{{ route('customer.game.login') }}" target="_blank"><i
                                    class="fal fa-sign-in fa-2x"></i><br>
                            <span class="text-main"> เข้าเล่นเกม</span>
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>


@push('styles')
    <style>
        .card {
            position: relative;
            display: flex;
            flex-direction: column;
            min-width: 0;
            word-wrap: break-word;
            background-color: #fff;
            background-clip: border-box;
            border: 1px solid rgba(0, 0, 0, .125);
            border-radius: 2px;
        }

        .card-title {
            float: unset;
            font-size: 1.1rem;
            font-weight: 400;
            margin: 0;
        }

        .card-text {
            clear: both;
            font-size: small;
        }

        .border-primary {
            border-color: #f22662 !important;
        }
    </style>
@endpush

@push('scripts')
    <script type="text/x-template" id="coupon-template">
        <div class="col-4 main-menu-item px-0">
            <a href="javascript:void(0)" v-on:click="couponModal"><i
                        class="fal fa-code fa-2x"></i><br>
                <span class="text-main"> กรอกโค๊ด</span>
            </a>
        </div>
    </script>

    <script type="text/x-template" id="bonus-template">
        <div class="col-4 main-menu-item px-0">
            <a href="javascript:void(0)" v-on:click="bonusModal"><i
                        class="fal fa-gift fa-2x"></i><br>
                <span class="text-main"> โบนัสฟรี</span>
            </a>
        </div>
    </script>
    <script>
        {{--function getBonus(code){--}}
        {{--    (async () => {--}}
        {{--        const ipAPI = "{{ route('customer.credit.transfer.load.game') }}";--}}
        {{--        const response = await fetch(ipAPI);--}}
        {{--        const data = await response.json();--}}
        {{--        const inputOptions = data.game;--}}


        {{--        var options = {};--}}
        {{--        $.map(inputOptions,--}}
        {{--            function (o) {--}}
        {{--                options[o.value] = o.text;--}}
        {{--            });--}}
        {{--        const {value: formValues} = await Swal.fire({--}}
        {{--            title: "รับฟรีโบนัส",--}}
        {{--            input: "select",--}}
        {{--            inputOptions: options,--}}
        {{--            inputPlaceholder: "เลือกค่ายเกมที่ต้องการรับ",--}}
        {{--            preConfirm: async (selectedOption) => {--}}


        {{--                if (!selectedOption) {--}}
        {{--                    Swal.showValidationMessage(`เเลือกค่ายเกมที่ต้องการรับฟรีโบนัส`)--}}
        {{--                } else {--}}
        {{--                    return new Promise(function (resolve) {--}}
        {{--                        resolve({game: selectedOption, code: code})--}}
        {{--                    });--}}

        {{--                }--}}


        {{--            },--}}

        {{--            didOpen: function () {--}}

        {{--                Swal.getPopup()?.querySelector('input')?.focus()--}}
        {{--            },--}}
        {{--            showCancelButton: true--}}
        {{--        });--}}


        {{--        if (formValues) {--}}

        {{--axios.post("{{ route('customer.credit.transfer.game.checkpro') }}", formValues)--}}
        {{--    .then(response => {--}}
        {{--        if (response.data.success) {--}}

        {{--            Swal.fire(--}}
        {{--                'สำเร็จ',--}}
        {{--                'โยกเงินเข้าเกมสำเร็จแล้ว',--}}
        {{--                'success'--}}
        {{--            );--}}

        {{--        } else {--}}

        {{--            Swal.fire(--}}
        {{--                'พบข้อผิดพลาด',--}}
        {{--                response.data.message,--}}
        {{--                'error'--}}
        {{--            );--}}
        {{--        }--}}
        {{--    })--}}
        {{--    .catch(response => {--}}

        {{--        Swal.fire(--}}
        {{--            'การเชื่อมต่อระบบ มีปัญหา',--}}
        {{--            response.data.message,--}}
        {{--            'error'--}}
        {{--        );--}}
        {{--    });--}}


        {{--        }--}}


        {{--    })()--}}

        {{--}--}}

        function getBonus(code) {
            axios.post("{{ route('customer.coupon.getbonus') }}", {id: code})
                .then(response => {
                    if (response.data.success) {

                        Swal.fire(
                            'สำเร็จ',
                            response.data.message,
                            'success'
                        );

                    } else {

                        Swal.fire(
                            'พบข้อผิดพลาด',
                            response.data.message,
                            'error'
                        );
                    }
                })
                .catch(response => {

                    Swal.fire(
                        'พบข้อผิดพลาด',
                        response.data.message,
                        'error'
                    );
                });

        }
    </script>

    <script type="module">
        Vue.component('couponlist', {
            template: '#coupon-template',
            methods: {
                couponModal: function (event) {
                    (async () => {
                        const {value: coupon} = await Swal.fire({
                            input: "text",
                            inputLabel: "กรอกโค๊ด",
                            inputPlaceholder: "กรุณากรอกโค๊ด",
                            inputValidator: (value) => {
                                if (!value) {
                                    return "กรุณากรอกโค๊ด";
                                }
                            }
                        });
                        if (coupon) {
                            this.$http.post(`${this.$root.baseUrl}/member/redeem`, {coupon: coupon})
                                .then(response => {
                                    $('.modal').modal('hide');

                                    if (response.data.success) {
                                        Swal.fire(
                                            'การดำเนินการ',
                                            response.data.message,
                                            'success'
                                        )

                                    } else {
                                        Swal.fire(
                                            'การดำเนินการ',
                                            response.data.message,
                                            'error'
                                        )
                                    }
                                })
                                .catch(exception => {
                                    $('.modal').modal('hide');
                                    Swal.fire(
                                        'การดำเนินการ',
                                        'เกิดข้อผิดพลาดบางประการ โปรดลองใหม่อีกครั้ง',
                                        'error'
                                    );
                                });

                        }

                    })()
                }
            }
        });

        Vue.component('bonuslist', {
            template: '#bonus-template',
            methods: {
                bonusModal: function (event) {
                    (async () => {
                        const ipAPI = '{{ route('customer.coupon.bonuslist') }}';
                        const response = await fetch(ipAPI);
                        const data = await response.json();
                        const htmls = data.html;
                        await Swal.fire({
                            title: "โบนัสฟรี",
                            html: htmls,
                            showConfirmButton: false,
                            willOpen: () => {


                            }
                        });


                    })()
                },

            }
        });

        {{--window.vm = new Vue({--}}
        {{--    el: '#app',--}}
        {{--    methods: {--}}
        {{--        getBonus: function (code){--}}
        {{--            this.$bvModal.msgBoxConfirm('ยืนยันการรับโบนัส.', {--}}
        {{--                title: 'โปรดยืนยันการทำรายการ',--}}
        {{--                size: 'sm',--}}
        {{--                buttonSize: 'sm',--}}
        {{--                okVariant: 'danger',--}}
        {{--                okTitle: 'ตกลง',--}}
        {{--                cancelTitle: 'ยกเลิก',--}}
        {{--                footerClass: 'p-2',--}}
        {{--                hideHeaderClose: false,--}}
        {{--                centered: true--}}
        {{--            })--}}
        {{--                .then(value => {--}}
        {{--                    if (value) {--}}
        {{--                        this.$http.post("{{ url($menu->currentRoute.'/gen') }}", {--}}
        {{--                            id: code--}}
        {{--                        })--}}
        {{--                            .then(response => {--}}
        {{--                                this.$bvModal.msgBoxOk(response.data.message, {--}}
        {{--                                    title: 'ผลการดำเนินการ',--}}
        {{--                                    size: 'sm',--}}
        {{--                                    buttonSize: 'sm',--}}
        {{--                                    okVariant: 'success',--}}
        {{--                                    headerClass: 'p-2 border-bottom-0',--}}
        {{--                                    footerClass: 'p-2 border-top-0',--}}
        {{--                                    centered: true--}}
        {{--                                });--}}
        {{--                                // this.$refs.gamelog.refresh()--}}
        {{--                                window.LaravelDataTables["dataTableBuilder"].draw(false);--}}
        {{--                            })--}}
        {{--                            .catch(exception => {--}}
        {{--                                console.log('error');--}}
        {{--                            });--}}
        {{--                    }--}}
        {{--                })--}}
        {{--                .catch(err => {--}}
        {{--                    // An error occurred--}}
        {{--                })--}}
        {{--        }--}}
        {{--    }--}}
        {{--})--}}
    </script>
@endpush
