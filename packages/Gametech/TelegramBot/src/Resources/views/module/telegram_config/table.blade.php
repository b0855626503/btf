
@push('styles')
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-colorpicker/css/bootstrap-colorpicker.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/summernote/summernote-lite.min.css') }}">
    <style>
        .card-title {
            margin-bottom: .75rem !important;
        }
    </style>
@endpush



<telegram :formaddedit='@json($configs)'></telegram>

@push('scripts')
    <script src="{{ asset('/vendor/bootstrap-colorpicker/js/bootstrap-colorpicker.min.js') }}"></script>
    <script src="{{ asset('vendor/summernote/summernote-lite.min.js') }}"></script>

    <script type="text/x-template" id="telegram-configs-template">

        <b-container class="bv-example-row" v-if="show">
            <b-form @submit.stop.prevent="addEditSubmitNew" id="frmaddedit">

                <b-form-row>
                    <b-col>

                        <b-card border-variant="success"
                                header="Telegram Bot Token"
                                header-bg-variant="success"
                                header-text-variant="black">
                            <b-card-text>
                                <b-form-group
                                    id="input-group-lineid"
                                    label="Telegram Bot Token :"
                                    label-for="bot_token"
                                    description="">
                                    <b-form-input
                                        id="bot_token"
                                        name="bot_token"
                                        v-model="formaddedit.bot_token"
                                        type="text"
                                        size="sm"
                                        placeholder=""
                                        autocomplete="off"
                                    ></b-form-input>
                                </b-form-group>
                                <b-form-group
                                    id="input-group-register_code"
                                    label="Register Code:"
                                    label-for="register_code"
                                    description="">
                                    <b-form-input
                                        id="register_code"

                                        v-model="formaddedit.register_code"
                                        type="text"
                                        size="sm"
                                        placeholder=""
                                        autocomplete="off"
                                        readonly
                                    ></b-form-input>
                                </b-form-group>
                            </b-card-text>
                        </b-card>
                    </b-col>
                </b-form-row>

                <b-button type="submit" variant="primary">บันทึก</b-button>

            </b-form>
        </b-container>
    </script>

    <script type="module">

        Vue.component('telegram', {
            template : "#telegram-configs-template",
            props: {
                formaddedit: {},

            },
            data() {
                return {
                    show: false,
                    fields: [],
                    items: [],
                    isBusy: false,
                    code: 1,
                    trigger: 0,
                    triggernew: 0,
                    fileupload: '',
                    fileuploadnew: '',

                    option: {
                        multigame_open: [{value: 'Y', text: 'เปิด'}, {value: 'N', text: 'ปิด'}],
                        pro_onoff: [{value: 'Y', text: 'เปิด'}, {value: 'N', text: 'ปิด'}],
                        point_open: [{value: 'Y', text: 'เปิด'}, {value: 'N', text: 'ปิด'}],
                        reward_open: [{value: 'Y', text: 'เปิด'}, {value: 'N', text: 'ปิด'}],
                        diamond_open: [{value: 'Y', text: 'เปิด'}, {value: 'N', text: 'ปิด'}],
                        money_tran_open: [{value: 'Y', text: 'เปิด'}, {value: 'N', text: 'ปิด'}],
                        freecredit_open: [{value: 'Y', text: 'เปิด'}, {value: 'N', text: 'ปิด'}],
                        freecredit_all: [{value: 'Y', text: 'เปิด'}, {value: 'N', text: 'ปิด'}],
                        diamond_per_bill: [{value: 'Y', text: 'เปิด'}, {value: 'N', text: 'ปิด'}],
                        point_per_bill: [{value: 'Y', text: 'เปิด'}, {value: 'N', text: 'ปิด'}],
                        diamond_transfer_in: [{value: 'Y', text: 'เปิด'}, {value: 'N', text: 'ปิด'}],
                        wheel_open: [{value: 'Y', text: 'เปิด'}, {value: 'N', text: 'ปิด'}],
                        verify_open: [{value: 'Y', text: 'เปิด'}, {value: 'N', text: 'ปิด'}],
                        verify_sms: [{value: 'Y', text: 'เปิด'}, {value: 'N', text: 'ปิด'}],
                        auto_wallet: [{value: 'Y', text: 'เปิด'}, {value: 'N', text: 'ปิด'}],
                        wallet_withdraw_all: [{value: 'Y', text: 'เปิด'}, {value: 'N', text: 'ปิด'}],
                        seamless: [{value: 'Y', text: 'เปิด'}, {value: 'N', text: 'ปิด'}],
                        pompay: [{value: 'Y', text: 'เปิด'}, {value: 'N', text: 'ปิด'}],
                        hengpay: [{value: 'Y', text: 'เปิด'}, {value: 'N', text: 'ปิด'}],
                        luckypay: [{value: 'Y', text: 'เปิด'}, {value: 'N', text: 'ปิด'}],
                        papayapay: [{value: 'Y', text: 'เปิด'}, {value: 'N', text: 'ปิด'}],
                        superrich: [{value: 'Y', text: 'เปิด'}, {value: 'N', text: 'ปิด'}],
                        qrscan: [{value: 'Y', text: 'เปิด'}, {value: 'N', text: 'ปิด'}],
                        cashback_open: [{value: 'Y', text: 'เปิด'}, {value: 'N', text: 'ปิด'}],
                        ic_open: [{value: 'Y', text: 'เปิด'}, {value: 'N', text: 'ปิด'}],
                        faststart_open: [{value: 'Y', text: 'เปิด'}, {value: 'N', text: 'ปิด'}],
                        admin_navbar_color: [
                            {value: 'navbar-white navbar-light', text: 'สีขาว'},
                            {value: 'navbar-gray-dark', text: 'สีเทาดำ'},
                            {value: 'navbar-dark navbar-primary', text: 'สีฟ้า'},
                            {value: 'navbar-dark navbar-success', text: 'สีเขียว'},
                            {value: 'navbar-dark navbar-info', text: 'สีเขียว2'},
                            {value: 'navbar-dark navbar-indigo', text: 'สีม่วง'},
                            {value: 'navbar-dark navbar-warning', text: 'สีเหลือง'},
                            {value: 'navbar-dark navbar-orange', text: 'สีส้ม'},
                            {value: 'navbar-dark navbar-danger', text: 'สีแดง'},

                        ],
                        admin_brand_color: [
                            {value: 'navbar-gray-dark', text: 'สีเทาดำ'},
                            {value: 'navbar-primary', text: 'สีฟ้า'},
                            {value: 'navbar-success', text: 'สีเขียว'},
                            {value: 'navbar-info', text: 'สีเขียว2'},
                            {value: 'navbar-indigo', text: 'สีม่วง'},
                            {value: 'navbar-warning', text: 'สีเหลือง'},
                            {value: 'navbar-orange', text: 'สีส้ม'},
                            {value: 'navbar-danger', text: 'สีแดง'},
                        ],
                    },
                    imgpath: '/storage/img/',
                    formaddedit: {
                        bot_token: '',
                        register_code: '',
                    }

                };
            },

            mounted() {

                this.code = null;
                this.show = false;
                this.fileupload = '';
                this.fileuploadnew = '';

                this.$nextTick(() => {
                    this.show = true;
                    this.code = 1;

                })

            },
            methods: {
                addEditSubmitNew(event) {
                    event.preventDefault();
                    // this.toggleButtonDisable(true);
                    let url = "{{ route('admin.'.$menu->currentRoute.'.update') }}/" + this.code;

                    let form = $('#frmaddedit')[0];
                    let formData = new FormData(form);

                    const config = {headers: {'Content-Type': `multipart/form-data; boundary=${formData._boundary}`}};

                    axios.post(url, formData, config)
                        .then(response => {
                            if (response.data.success === true) {
                                this.$bvModal.msgBoxOk(response.data.message, {
                                    title: 'ผลการดำเนินการ',
                                    size: 'sm',
                                    buttonSize: 'sm',
                                    okVariant: 'success',
                                    headerClass: 'p-2 border-bottom-0',
                                    footerClass: 'p-2 border-top-0',
                                    centered: true
                                });
                                // window.LaravelDataTables["dataTableBuilder"].draw(false);
                            } else {
                                $.each(response.data.message, function (index, value) {
                                    document.getElementById(index).classList.add("is-invalid");
                                });
                                $('input').on('focus', function (event) {
                                    event.preventDefault();
                                    // this.toggleButtonDisable(true);
                                    event.stopPropagation();
                                    var id = $(this).attr('id');
                                    document.getElementById(id).classList.remove("is-invalid");
                                });
                            }
                        })
                        .catch(errors => {
                            this.toggleButtonDisable(false);
                            Toast.fire({
                                icon: 'error',
                                title: errors.response.data
                            })
                        });

                }
            }

        })

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

                autoCnt(draw) {
                    const self = this;
                    this.toast = window.Toasty;
                    this.loadCnt();

                    setInterval(function () {
                        self.loadCnt();
                        self.loopcnts++;
                        // self.$refs.deposit.loadData();
                    }, 50000);

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
                    const response = await axios.get("{{ url('loadcnt') }}");
                    if (document.getElementById('badge_bank_in')) {
                        document.getElementById('badge_bank_in').textContent = response.data.bank_in_today + ' / ' + response.data.bank_in;
                    }
                    if (document.getElementById('badge_bank_out')) {
                        document.getElementById('badge_bank_out').textContent = response.data.bank_out;
                    }
                    if (document.getElementById('badge_withdraw')) {
                        document.getElementById('badge_withdraw').textContent = response.data.withdraw;
                    }
                    if (document.getElementById('badge_withdraw_free')) {
                        document.getElementById('badge_withdraw_free').textContent = response.data.withdraw_free;
                    }
                    if (document.getElementById('badge_confirm_wallet')) {
                        document.getElementById('badge_confirm_wallet').textContent = response.data.payment_waiting;
                    }
                    if (document.getElementById('badge_member_confirm')) {
                        document.getElementById('badge_member_confirm').textContent = response.data.member_confirm;
                    }
                    if (this.loopcnts === 0) {
                        document.getElementById('announce').textContent = response.data.announce;
                        this.runMarquee();
                    } else {
                        if (response.data.announce_new === 'Y') {
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
