
<b-modal ref="addedit" id="addedit" centered size="md" title="เพิ่ม สมาชิกใหม่" :no-stacking="true"
         :no-close-on-backdrop="true" :hide-footer="true" :lazy="true">
    <b-container class="bv-example-row">
        <b-form @submit.prevent="addEditSubmitNew" v-if="show">
            <b-form-row>
                <b-col>
                    <b-form-row>
                        <b-col>
                            <b-form-group
                                    id="input-group-date_regis"
                                    label="วันที่สมัคร:"
                                    label-for="date_regis"
                                    description="">
                                <b-form-datepicker
                                        id="date_regis"
                                        v-model="formaddedit.date_regis"
                                        size="sm"
                                        placeholder=""
                                        autocomplete="off"
                                        locale="th-TH"
                                        :date-format-options="{ year: 'numeric', month: '2-digit', day: '2-digit' }"
                                        @context="onContext"
                                ></b-form-datepicker>
                            </b-form-group>
                        </b-col>
                    </b-form-row>

                    <b-form-row>
                        <b-col>
                            <b-form-group
                                    id="input-group-userid"
                                    label="รหัสสมาชิก:"
                                    label-for="userid"
                                    description="จะออกรหัสสมาชิก โดยระบบ อัตโนมัติ">
                                <b-form-input
                                        id="userid"
                                        v-model="formaddedit.userid"
                                        type="text"
                                        size="sm"
                                        placeholder=""
                                        autocomplete="off"
                                        required
                                        plaintext
                                ></b-form-input>
                            </b-form-group>
                        </b-col>
                    </b-form-row>

                    <b-form-row>
                        <b-col>
                            <b-form-group
                                    id="input-group-name"
                                    label="ชื่อ - นามสกุล * :"
                                    label-for="name"
                                    description="">
                                <b-form-input
                                        id="name"
                                        v-model="formaddedit.name"
                                        type="text"
                                        size="sm"
                                        placeholder="ไม่ต้องใส่ คำนำหน้า"
                                        autocomplete="off"
                                        required
                                ></b-form-input>
                            </b-form-group>
                        </b-col>
                    </b-form-row>

                    <b-form-row>
                        <b-col>
                            <b-form-group
                                    id="input-group-line"
                                    label="ไอดีไลน์:"
                                    label-for="line"
                                    description="">
                                <b-form-input
                                        id="line"
                                        v-model="formaddedit.line"
                                        type="text"
                                        size="sm"
                                        placeholder=""
                                        autocomplete="off"

                                ></b-form-input>
                            </b-form-group>
                        </b-col>
                    </b-form-row>

                    <b-form-row>
                        <b-col>
                            <b-form-group
                                    id="input-group-tel"
                                    label="เบอร์โทร * :"
                                    label-for="tel"
                                    description="">
                                <b-form-input
                                        id="tel"
                                        v-model="formaddedit.tel"
                                        type="text"
                                        size="sm"
                                        maxlength="10"
                                        placeholder=""
                                        autocomplete="off"
                                        required
                                ></b-form-input>
                            </b-form-group>
                        </b-col>
                    </b-form-row>

                    <b-form-row>
                        <b-col>
                            <b-form-group
                                    id="input-group-email"
                                    label="Email:"
                                    label-for="email"
                                    description="">
                                <b-form-input
                                        id="email"
                                        v-model="formaddedit.email"
                                        type="text"
                                        size="sm"
                                        placeholder=""
                                        autocomplete="off"
                                ></b-form-input>
                            </b-form-group>
                        </b-col>
                    </b-form-row>

                    <b-form-row>
                        <b-col>
                            <b-form-group
                                    id="input-group-refers"
                                    label="รู้จักเราจาก * :"
                                    label-for="refers"
                                    description="">

                                <b-form-select
                                        id="refers"
                                        v-model="formaddedit.refers"
                                        :options="option.refers"
                                        size="sm"
                                        required
                                ></b-form-select>
                            </b-form-group>
                        </b-col>
                    </b-form-row>

                    <b-form-row>
                        <b-col>
                            <b-form-group
                                    id="input-group-remark"
                                    label="หมายเหตุ:"
                                    label-for="remark"
                                    description="">

                                <b-form-textarea
                                        id="remark"
                                        v-model="formaddedit.remark"
                                        placeholder="Enter something..."
                                        rows="3"
                                        max-rows="6"
                                ></b-form-textarea>
                            </b-form-group>
                        </b-col>
                    </b-form-row>

                    <b-form-row>
                        <b-col>
                            <b-form-group
                                    id="input-group-level"
                                    label="ระดับสมาชิก:"
                                    label-for="level"
                                    description="">

                                <b-form-radio-group
                                        v-model="formaddedit.level"
                                        :options="option.level"
                                        name="Level"
                                />
                            </b-form-group>
                        </b-col>
                    </b-form-row>

                    <b-form-row>
                        <b-col>
                            <b-form-group
                                    id="input-group-enable"
                                    label="การใช้งาน:"
                                    label-for="enable"
                                    description="">

                                <b-form-checkbox
                                        id="enable"
                                        v-model="formaddedit.enable"
                                        value="Y"
                                        unchecked-value="N">
                                    <small>การใช้งาน</small>
                                </b-form-checkbox>
                            </b-form-group>
                        </b-col>
                    </b-form-row>


                </b-col>

{{--                <b-col>--}}
{{--                    <p class="text-center">ข้อมูลการถอนเงิน 5 รายการล่าสุด</p>--}}

{{--                    <b-overlay--}}
{{--                            :show="isBusy"--}}
{{--                            opacity="0.4"--}}
{{--                            blur="2px"--}}
{{--                            rounded--}}
{{--                            spinner-variant="primary"--}}
{{--                    >--}}
{{--                        <b-table--}}
{{--                                striped hover small outlined sticky-header show-empty--}}
{{--                                :items="items" :fields="fields"--}}
{{--                                ref="tbdatalog" v-if="show"--}}
{{--                        >--}}
{{--                            <template #table-busy>--}}
{{--                                <div class="text-center text-danger my-2">--}}
{{--                                    <b-spinner class="align-middle"></b-spinner>--}}
{{--                                    <strong>Loading...</strong>--}}
{{--                                </div>--}}
{{--                            </template>--}}
{{--                            <template #cell(transfer)="data"><span v-html="data.value"></span></template>--}}
{{--                            <template #cell(credit_type)="data"><span v-html="data.value"></span></template>--}}
{{--                            <template #cell(user_id)="data"><span v-html="data.value"></span></template>--}}
{{--                            <template #cell(status)="data"><span v-html="data.value"></span></template>--}}
{{--                            <template #cell(action)="data"><span v-html="data.value"></span></template>--}}
{{--                            <template #cell(changepass)="data"><span v-html="data.value"></span></template>--}}
{{--                        </b-table>--}}

{{--                        <template #overlay>--}}
{{--                            <div class="text-center">--}}
{{--                                <b-spinner class="mb-2"></b-spinner>--}}
{{--                                <div>กำลังโหลดรายการล่าสุด…</div>--}}
{{--                            </div>--}}
{{--                        </template>--}}
{{--                    </b-overlay>--}}
{{--                </b-col>--}}
            </b-form-row>

            <b-form-row class="mb-sm-3">
                <b-button type="submit"
                          variant="primary"
                          class="btn-block">
                    บันทึก</span>
                </b-button>
            </b-form-row>
        </b-form>
    </b-container>
</b-modal>


@push('scripts')
    <script>
        function showModalNew(id, method) {
            window.app.showModalNew(id, method);
        }

        $(document).ready(function () {
            $('body').addClass('sidebar-collapse');
        });

    </script>
    <script type="module">

        window.app = new Vue({
            el: '#app',
            data() {
                return {
                    show: false,
                    showsub: false,
                    showremark: false,
                    fieldsRemark: [],
                    fields: [
                        {key: 'time', label: 'ธนาคาร'},
                        {key: 'amount', label: 'ชื่อบัญชี', class: 'text-right'},
                        {key: 'user_id', label: 'เลขที่บัญชี', class: 'text-center'},
                    ],
                    items: [],
                    caption: null,
                    isBusy: false,
                    isBusyRemark: false,
                    formmethodsub: 'edit',
                    formatted: '',
                    selected: '',
                    formsub: {
                        remark: ''
                    },
                    formchange: {
                        id: null,
                        password: ''
                    },
                    formmethod: 'edit',
                    formaddedit: {
                        level: 'N',
                        date_regis: '',
                        userid: '',
                        name: '',
                        refers: '',
                        tel: '',
                        line: '',
                        enable: 'Y',
                        email: '',
                        remark: '',
                    },
                    option: {
                        refers: [/* ... */],
                        level: [
                            {text: 'ปกติ', value: 'N'},
                            {text: 'VIP', value: 'Y'}
                        ],
                    },
                    formrefill: {
                        id: null,
                        amount: 0,
                        account_code: '',
                        remark_admin: '',
                        one_time_password: ''
                    },
                    formmoney: {
                        id: null,
                        amount: 0,
                        type: 'D',
                        remark: '',
                        one_time_password: ''
                    },
                    formpoint: {
                        id: null,
                        amount: 0,
                        type: 'D',
                        remark: ''
                    },
                    formdiamond: {
                        id: null,
                        amount: 0,
                        type: 'D',
                        remark: ''
                    },
                    banks: [{value: '', text: '== ธนาคาร =='}],
                    typesmoney: [{value: 'D', text: 'เพิ่ม ยอดเงิน'}, {value: 'W', text: 'ลด ยอดเงิน'}],
                    typespoint: [{value: 'D', text: 'เพิ่ม Point'}, {value: 'W', text: 'ลด Point'}],
                    typesdiamond: [{value: 'D', text: 'เพิ่ม Diamond'}, {value: 'W', text: 'ลด Diamond'}]
                };
            },
            created() {
                this.audio = document.getElementById('alertsound');
                this.autoCnt(false);
            },
            mounted() {
                this.loadRefer();
                // this.loadBank();
                // this.loadBankAccount();
            },
            methods: {
                onContext(ctx) {
                    this.formatted = ctx.selectedFormatted
                    this.selected = ctx.selectedYMD
                },
                editModal(code) {
                    this.code = null;
                    this.formaddedit = {
                        firstname: '',
                        lastname: '',
                        bank_code: '',
                        user_name: '',
                        user_pass: '',
                        acc_no: '',
                        wallet_id: '',
                        lineid: '',
                        pic_id: '',
                        tel: '',
                    }
                    this.formmethod = 'edit';

                    this.show = false;
                    this.$nextTick(async () => {
                        this.show = true;
                        this.code = code;
                        await this.loadData();
                        this.$refs.addedit.show();
                    });
                },
                addModal() {
                    this.code = null;
                    const now = new Date();
                    const yyyy = now.getFullYear();
                    const mm = String(now.getMonth() + 1).padStart(2, '0');
                    const dd = String(now.getDate()).padStart(2, '0');
                    const HH = String(now.getHours()).padStart(2, '0');
                    const II = String(now.getMinutes()).padStart(2, '0');


                    this.formaddedit = {
                        date_regis: `${yyyy}-${mm}-${dd}`,
                        name: '',
                        tel: '',
                        email: '',
                        userid: '',
                        line: '',
                        level: 'N',
                        enable: 'Y',
                        refers: '',
                        remark: '',
                    };
                    this.formmethod = 'add';

                    this.show = false;
                    this.$nextTick(async () => {
                        this.show = true;
                        await this.loadConfig();      // เติม userid ใส่ช่อง
                        this.$refs.addedit.show();
                    });
                },
                async loadConfig() {
                    const response = await axios.get("{{ route('admin.'.$menu->currentRoute.'.loadconfig') }}");
                    const d = response?.data?.data || {};
                    console.log(d.userid)
                    this.formaddedit.userid = d.userid;
                },
                async loadData() {
                    const response = await axios.get("{{ url($menu->currentRoute.'/loaddata') }}", {
                        params: {
                            id: this.code
                        }
                    });
                    const u = response.data.data;

                    this.formaddedit = {
                        firstname: response.data.data.firstname,
                        lastname: response.data.data.lastname,
                        bank_code: response.data.data.bank_code,
                        user_name: response.data.data.user_name,
                        user_pass: '',
                        acc_no: response.data.data.acc_no,
                        wallet_id: response.data.data.wallet_id,
                        lineid: response.data.data.lineid,
                        pic_id: response.data.data.pic_id,
                        tel: response.data.data.tel,
                    }
                    if (u.pic_id) {
                        const fileName = u.pic_id.split('/').pop();     // "0855626577.webp"
                        const fileUrl = this.fileUrl(u.pic_id);        // แปลง path -> URL ที่เบราว์เซอร์โหลดได้

                        this.currentPic = {
                            id: this.code,          // ใช้ code เป็น serverId เวลา delete (ถ้าลบตาม code)
                            name: fileName,
                            url: fileUrl,
                            size: 12345,            // ใส่คร่าว ๆ พอให้ Dropzone แสดงผล
                            isExisting: true
                        };
                    } else {
                        this.currentPic = null;
                    }
                },
                fileUrl(path) {
                    // ถ้าไฟล์อยู่ใน storage/public -> /storage/qr/0855626577.webp
                    return `{{ url('/storage') }}/${path}`;
                    // ถ้าเก็บ S3/R2 ให้ backend ส่ง URL มาแทนจะชัวร์กว่า
                },
                async loadBank() {
                    const response = await axios.get("{{ url($menu->currentRoute.'/loadbank') }}");
                    this.option.bank_code = response.data.banks;
                },
                async loadRefer() {
                    const response = await axios.get("{{ url($menu->currentRoute.'/loadrefer') }}");
                    this.option.refers = response.data.banks;
                },
                async loadBankAccount() {
                    const response = await axios.get("{{ url($menu->currentRoute.'/loadbankaccount') }}");
                    this.banks = response.data.banks;
                },
                async myLog() {
                    let self = this;
                    self.items = [];
                    const response = await axios.get("{{ url($menu->currentRoute.'/gamelog') }}", {
                        params: {
                            id: this.code,
                            method: this.method
                        }
                    });


                    this.caption = response.data.name;
                    if (this.method === 'transfer') {
                        this.fields = [
                            {key: 'date_create', label: 'วันที่'},
                            {key: 'id', label: 'บิลเลขที่'},
                            {key: 'transfer', label: 'ประเภท'},
                            {key: 'game_name', label: 'เกม'},
                            {key: 'amount', label: 'จำนวนเงิน', class: 'text-right'},
                            {key: 'status', label: 'สถานะบิล', class: 'text-center'},

                        ];
                        this.items = response.data.list;
                    } else if (this.method === 'gameuser') {

                        this.fields = [
                            {key: 'game', label: 'เกม'},
                            {key: 'user_name', label: 'บัญชีเกม'},
                            {key: 'user_pass', label: 'รหัสผ่าน'},
                            {key: 'status', label: 'ข้อมูลจาก', class: 'text-center'},
                            {key: 'balance', label: 'ยอดคงเหลือ', class: 'text-right'},
                            {key: 'promotion', label: 'โปรที่รับมา', class: 'text-left'},
                            {key: 'turn', label: 'Turn', class: 'text-center'},
                            {key: 'amount_balance', label: 'ยอดเทินขั้นต่ำ', class: 'text-right'},
                            {key: 'withdraw_limit', label: 'ถอนได้รับไม่เกิน', class: 'text-right'},
                            {key: 'action', label: 'ยกเลิก ID', class: 'text-center'},
                            {key: 'changepass', label: 'เปลี่ยนรหัส', class: 'text-center'},
                        ];


                        $.each(response.data.list, function (key, value) {
                            self.getbalancenew(key, value);
                        });

                    } else if (this.method === 'deposit') {
                        @if($config->multigame_open == 'Y')
                            this.fields = [
                            {key: 'date_create', label: 'วันที่'},
                            {key: 'id', label: 'บิลเลขที่'},
                            {key: 'amount', label: 'จำนวนเงิน', class: 'text-right'},
                            {key: 'credit_before', label: 'ก่อนฝาก', class: 'text-right'},
                            {key: 'credit_after', label: 'หลังฝาก', class: 'text-right'},

                        ];
                        @else
                            this.fields = [
                            {key: 'date_create', label: 'วันที่'},
                            {key: 'id', label: 'บิลเลขที่'},
                            {key: 'amount', label: 'จำนวนเงิน', class: 'text-right'},
                            {key: 'credit_bonus', label: 'ได้รับโบนัส', class: 'text-right'},
                            {key: 'credit_before', label: 'ก่อนฝาก', class: 'text-right'},
                            {key: 'credit_after', label: 'หลังฝาก', class: 'text-right'},

                        ];
                        @endif
                            this.items = response.data.list;
                    } else if (this.method === 'withdraw') {
                        this.fields = [
                            {key: 'date_create', label: 'วันที่'},
                            {key: 'id', label: 'บิลเลขที่'},
                            {key: 'status_display', label: 'สถานะ'},
                            {key: 'amount', label: 'จำนวนเงิน', class: 'text-right'},
                            {key: 'credit_before', label: 'ก่อนถอน', class: 'text-right'},
                            {key: 'credit_after', label: 'หลังถอน', class: 'text-right'}
                        ];
                        this.items = response.data.list;
                    } else if (this.method === 'setwallet') {

                        @if($config->multigame_open == 'Y')
                            this.fields = [
                            {key: 'date_create', label: 'วันที่'},
                            {key: 'credit_type', label: 'ประเภทรายการ'},
                            {key: 'remark', label: 'หมายเหตุ'},
                            {key: 'credit_amount', label: 'จำนวน Wallet', class: 'text-right'},
                            {key: 'credit_before', label: 'Wallet ก่อนหน้า', class: 'text-right'},
                            {key: 'credit_balance', label: 'รวม Wallet', class: 'text-right'}
                        ];
                        @else
                            this.fields = [
                            {key: 'date_create', label: 'วันที่'},
                            {key: 'credit_type', label: 'ประเภทรายการ'},
                            {key: 'remark', label: 'หมายเหตุ'},
                            {key: 'credit_amount', label: 'จำนวน Credit', class: 'text-right'},
                            {key: 'credit_before', label: 'Credit ก่อนหน้า', class: 'text-right'},
                            {key: 'credit_balance', label: 'รวม Credit', class: 'text-right'}
                        ];
                        @endif

                            this.items = response.data.list;
                    } else if (this.method === 'setpoint') {
                        this.fields = [
                            {key: 'date_create', label: 'วันที่'},
                            {key: 'credit_type', label: 'ประเภทรายการ'},
                            {key: 'remark', label: 'หมายเหตุ'},
                            {key: 'credit_amount', label: 'จำนวน Point', class: 'text-right'},
                            {key: 'credit_before', label: 'Point ก่อนหน้า', class: 'text-right'},
                            {key: 'credit_balance', label: 'รวม Point', class: 'text-right'}
                        ];
                        this.items = response.data.list;
                    } else if (this.method === 'setdiamond') {
                        this.fields = [
                            {key: 'date_create', label: 'วันที่'},
                            {key: 'credit_type', label: 'ประเภทรายการ'},
                            {key: 'remark', label: 'หมายเหตุ'},
                            {key: 'credit_amount', label: 'จำนวน Diamond', class: 'text-right'},
                            {key: 'credit_before', label: 'Diamond ก่อนหน้า', class: 'text-right'},
                            {key: 'credit_balance', label: 'รวม Diamond', class: 'text-right'}
                        ];
                        this.items = response.data.list;
                    } else {
                        this.fields = [];
                        this.items = [];
                    }


                    // Object.keys(response.data.list).map(function(key) {
                    //     // console.log(response.data.list[key].game_id);
                    //     // this.$set(this.options, key, response.body[key]);
                    //     self.getbalance(response.data.list[key].game_code, response.data.list[key].member_code);
                    // });

                    // this.items = response.data.list;
                    // const game = this.items;
                    //
                    // console.log(game);
                    //
                    // if (this.method === 'gameuser') {
                    //
                    //     $.each(response.data.list, function (key, value) {
                    //          self.getbalancenew(key,value);
                    //     });
                    //
                    // }

                },
                async getbalance(key, value) {

                    let game = [];
                    const response = await axios.get("{{ url($menu->currentRoute.'/balance') }}", {
                        params: {
                            game_code: value.game_code,
                            member_code: value.member_code
                        }
                    })

                    this.items = game;

                },
                getbalancenew(key, value) {

                    var game = this.items;
                    axios.get("{{ url($menu->currentRoute.'/balance') }}", {
                        params: {
                            game_code: value.game_code,
                            member_code: value.member_code
                        }
                    }).then(function (response) {
                        if (response.data.success) {
                            game.push(response.data.list);
                        } else {
                            game.push(value);
                        }
                    }).catch(error => {
                        game.push(value);
                    });

                },
                async myRemark() {
                    const response = await axios.get("{{ url($menu->currentRoute.'/remark') }}", {
                        params: {
                            id: this.code
                        }
                    });


                    this.fieldsRemark = [
                        {key: 'date_create', label: 'วันที่'},
                        {key: 'remark', label: 'หมายเหตุ'},
                        {key: 'emp_code', label: 'ผู้เพิ่มรายการ'},
                        {key: 'action', label: '', class: 'text-center'}
                    ];

                    this.items = response.data.list;
                    return this.items;

                },
                addSubModal() {

                    this.formsub = {
                        remark: ''
                    }
                    this.formmethodsub = 'add';

                    this.showsub = false;
                    this.$nextTick(() => {
                        this.showsub = true;
                        this.$refs.addeditsub.show();

                    })
                },
                delSub(code, table) {
                    this.$bvModal.msgBoxConfirm('ต้องการดำเนินการ ลบข้อมูลหรือไม่.', {
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
                                this.$http.post("{{ url($menu->currentRoute.'/deletesub') }}", {
                                    id: code, method: table
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
                                        this.$refs.tbdata.refresh();

                                    })
                                    .catch(errors => console.log(errors));
                            }
                        })
                        .catch(errors => console.log(errors));
                },

                showErrorMessage(response) {
                    let message = response?.data?.message || 'เกิดข้อผิดพลาดที่ไม่ทราบสาเหตุ';

                    // ถ้าเป็น object เช่น { field: [msg1, msg2], ... }
                    if (typeof message === 'object') {
                        try {
                            message = Object.values(message).flat().join('\n');
                        } catch (e) {
                            message = [].concat(...Object.values(message)).join('\n');
                        }
                    }

                    // ถ้าเป็น array เช่น ["msg1", "msg2"]
                    if (Array.isArray(message)) {
                        message = message.join('\n');
                    }

                    this.$bvModal.msgBoxOk(message, {
                        title: 'ผลการดำเนินการ',
                        size: 'sm',
                        buttonSize: 'sm',
                        okVariant: 'danger',
                        headerClass: 'p-2 border-bottom-0',
                        footerClass: 'p-2 border-top-0',
                        centered: true
                    });

                },

                addEditSubmitNew(event) {
                    event.preventDefault();
                    this.toggleButtonDisable(true);
                    if (this.formmethod === 'add') {
                        var url = "{{ url($menu->currentRoute.'/create') }}";


                    } else if (this.formmethod === 'edit') {
                        var url = "{{ url($menu->currentRoute.'/update') }}/" + this.code;

                    }


                    let formData = new FormData();
                    const json = JSON.stringify({
                        firstname: this.formaddedit.firstname,
                        lastname: this.formaddedit.lastname,
                        bank_code: this.formaddedit.bank_code,
                        user_name: this.formaddedit.user_name,
                        user_pass: this.formaddedit.user_pass,
                        acc_no: this.formaddedit.acc_no,
                        wallet_id: this.formaddedit.wallet_id,
                        lineid: this.formaddedit.lineid,
                        pic_id: this.formaddedit.pic_id,
                        tel: this.formaddedit.tel,
                        one_time_password: this.formaddedit.one_time_password
                    });

                    formData.append('data', json);

                    // formData.append('fileupload', this.fileupload);

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
                                window.LaravelDataTables["dataTableBuilder"].draw(false);
                            } else {
                                this.showErrorMessage(response);
                            }
                        })
                        .catch(errors => {
                            this.toggleButtonDisable(false);
                            Toast.fire({
                                icon: 'error',
                                title: errors.data.message
                            })
                        });

                },
                addEditSubmitNewSub(event) {
                    event.preventDefault();
                    this.toggleButtonDisable(true);

                    var url = "{{ url($menu->currentRoute.'/createsub') }}";

                    this.$http.post(url, {id: this.code, data: this.formsub})
                        .then(response => {
                            this.$bvModal.hide('addeditsub');
                            this.$bvModal.msgBoxOk(response.data.message, {
                                title: 'ผลการดำเนินการ',
                                size: 'sm',
                                buttonSize: 'sm',
                                okVariant: 'success',
                                headerClass: 'p-2 border-bottom-0',
                                footerClass: 'p-2 border-top-0',
                                centered: true
                            });

                            this.$refs.tbdataremark.refresh()

                        })
                        .catch(errors => console.log(errors));

                },
            },
        })
        ;


    </script>
@endpush

