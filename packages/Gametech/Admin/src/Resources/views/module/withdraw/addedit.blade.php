<b-modal ref="approve" id="approve" centered size="md" title="ทำรายการอนุมัติการโอนเงิน" :no-stacking="true"
         :no-close-on-backdrop="true"
         :hide-footer="true" :lazy="true">
    <b-container class="bv-example-row">
        <b-form @submit.prevent="approveSubmit" v-if="show">
            <b-form-row>
                <div class="info-box">
                    <span class="info-box-icon"><b-img :src="formaddedit.member_bank_pic"></b-img></span>

                    <div class="info-box-content">
                        <span class="info-box-text" v-text="formaddedit.member_bank"></span>
                        <span class="info-box-number" v-text="formaddedit.member_account"></span>
                    </div>
                    <!-- /.info-box-content -->
                </div>
                <table class="table">
                    <tbody>
                    <tr>
                        <td width="50%">User :</td>
                        <td width="50%" align="right" v-text="formaddedit.member_username"></td>
                    </tr>
                    <tr>
                        <td>ชื่อลูกค้า :</td>
                        <td align="right" v-text="formaddedit.member_name"></td>
                    </tr>
                    <tr>
                        <td>จำนวนเงิน :</td>
                        <td align="right" v-text="formaddedit.amount"></td>
                    </tr>
                    </tbody>
                </table>
            </b-form-row>


            <b-form-row>
                <b-col cols="12">

                    <b-form-group
                            id="input-group-account_code"
                            label="บัญชีที่ใช้ดำเนินการ:"
                            label-for="account_code"
                            description="">

                        <b-form-select
                                id="account_code"
                                name="account_code"
                                v-model="formaddedit.account_code"
                                :options="option.account_code"
                                size="sm"
                                required
                        ></b-form-select>

                    </b-form-group>

                    <b-form-group
                            id="input-group-1"
                            label="ค่าธรรมเนียม:"
                            label-for="fee"
                            description="">
                        <b-form-input
                                id="fee"
                                v-model="formaddedit.fee"
                                type="number"
                                size="sm"
                                placeholder=""
                                autocomplete="off"
                        ></b-form-input>
                    </b-form-group>
                </b-col>
                <b-col cols="12">

                    <b-form-group
                            id="input-group-2"
                            label="วันที่โอน:"
                            label-for="date_bank"
                            description="">
                        <b-form-datepicker
                                id="date_bank"
                                v-model="formaddedit.date_bank"
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
                <b-col cols="12">
                    <b-form-group
                            id="input-group-3"
                            label="เวลาที่โอน:"
                            label-for="time_bank"
                            description="">
                        <b-form-timepicker
                                id="time_bank"
                                v-model="formaddedit.time_bank"
                                type="text"
                                size="sm"
                                placeholder=""
                                autocomplete="off"
                                :hour12="false"
                        ></b-form-timepicker>
                    </b-form-group>
                </b-col>

            </b-form-row>

            <b-button type="submit" variant="primary" :disabled="!userFound.withdraw || submittingApprove">
                <span v-if="submittingApprove"><b-spinner small class="mr-1"></b-spinner>กำลังบันทึก...</span>
                <span v-else>บันทึก</span>
            </b-button>

        </b-form>
    </b-container>
</b-modal>


<b-modal ref="withdraw" id="withdraw" centered size="xl" title="เพิ่มรายการถอน" :no-stacking="true"
         :no-close-on-backdrop="true" :hide-footer="true" :lazy="true">
    <b-container class="bv-example-row">
        <b-form @submit.prevent="withdrawSubmit" v-if="show">
            <input type="hidden" id="id" :value="formwithdraw.id" required>
            <b-form-row>
                <b-col>
                    <b-form-row>
                        <b-col>
                            <b-form-group
                                    id="input-group-user_name"
                                    label="User Name:"
                                    label-for="user_name"
                                    description="ระบุ User ID ที่ต้องการ เติมเงินรายการนี้">
                                <b-input-group>
                                    <b-form-input
                                            id="user_name"
                                            v-model.trim="formwithdraw.user_name"
                                            type="text"
                                            size="sm"
                                            placeholder="User ID"
                                            autocomplete="off"
                                    ></b-form-input>
                                    <b-input-group-append>
                                        <b-button variant="success" @click="loadUserWithdraw">ค้นหา</b-button>
                                    </b-input-group-append>
                                </b-input-group>
                            </b-form-group>
                        </b-col>
                    </b-form-row>

                    <b-form-row>
                        <b-col>
                            <b-form-group
                                    id="input-group-name"
                                    label="ชื่อลูกค้า:"
                                    label-for="name"
                                    description="">
                                <b-form-input
                                        id="name"
                                        v-model="formwithdraw.name"
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
                                    id="input-group-balance"
                                    label="ยอดเงินปัจจุบัน:"
                                    label-for="balance"
                                    description="">
                                <b-form-input
                                        id="balance"
                                        v-model="formwithdraw.balance"
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
                            <b-form-group id="input-group-1" label="จำนวนเงินถอน:" label-for="amount"
                                          description="ระบุจำนวนเงิน ที่ต้องการเติม">
                                <b-form-input
                                        id="amount"
                                        v-model.number="formwithdraw.amount"
                                        type="number"
                                        size="sm"
                                        placeholder="จำนวนเงินที่ต้องการ ถอน"
                                        min="1"
                                        step="1"
                                        autocomplete="off"
                                        required
                                ></b-form-input>
                            </b-form-group>
                        </b-col>
                    </b-form-row>

                    <b-form-row>
                        <b-col>
                            <b-form-group id="input-group-bankm" label="ธนาคารที่รับโอน:" label-for="bankm">
                                <b-form-select
                                        id="bankm"
                                        v-model="formwithdraw.bankm"
                                        :options="bankm"
                                        size="sm"
                                        required
                                ></b-form-select>
                            </b-form-group>
                        </b-col>
                    </b-form-row>

                </b-col>

                <b-col>
                    <p class="text-center">ข้อมูลการถอนเงิน 5 รายการล่าสุด</p>

                    <b-overlay
                            :show="isBusy"
                            opacity="0.4"
                            blur="2px"
                            rounded
                            spinner-variant="primary"
                    >
                        <b-table
                                striped hover small outlined sticky-header show-empty
                                :items="items" :fields="fields" :busy="isBusy"
                                ref="tbdatalog" v-if="show"
                        >
                            <template #table-busy>
                                <div class="text-center text-danger my-2">
                                    <b-spinner class="align-middle"></b-spinner>
                                    <strong>Loading...</strong>
                                </div>
                            </template>
                            <template #cell(transfer)="data"><span v-html="data.value"></span></template>
                            <template #cell(credit_type)="data"><span v-html="data.value"></span></template>
                            <template #cell(status)="data"><span v-html="data.value"></span></template>
                            <template #cell(action)="data"><span v-html="data.value"></span></template>
                            <template #cell(changepass)="data"><span v-html="data.value"></span></template>
                        </b-table>

                        <!-- (ออปชัน) ใส่ empty-state เวลาข้อมูลว่าง -->
                        <template #overlay>
                            <div class="text-center">
                                <b-spinner class="mb-2"></b-spinner>
                                <div>กำลังโหลดรายการล่าสุด…</div>
                            </div>
                        </template>
                    </b-overlay>
                </b-col>
            </b-form-row>

            <b-button type="submit" variant="primary" :disabled="!userFound.withdraw || submittingWithdraw">
                <span v-if="submittingWithdraw"><b-spinner small class="mr-1"></b-spinner>กำลังบันทึก...</span>
                <span v-else>บันทึก</span>
            </b-button>
        </b-form>
    </b-container>
</b-modal>


<b-modal ref="clear" id="clear" centered size="md" title="โปรดระบุหมายเหตุ ในการทำรายการ" :no-stacking="true"
         :no-close-on-backdrop="true"
         :hide-footer="true" :lazy="true">
    <b-form @submit.prevent.once="clearSubmit" v-if="show" id="frmclear" ref="frmclear">
        <b-form-group
                id="input-group-remark"
                label="หมายเหตุ:"
                label-for="remark"
                description="">
            <b-form-input
                    id="remark"
                    v-model="formclear.remark"
                    type="text"
                    size="sm"
                    placeholder=""
                    autocomplete="off"
                    required
            ></b-form-input>
        </b-form-group>

        <b-button type="submit" variant="primary">บันทึก</b-button>
    </b-form>
</b-modal>

@push('scripts')
    <script type="text/javascript">
        function clearModal(id) {
            window.app.clearModal(id);
        }

        function fixModal(id) {
            window.app.fixSubmit(id);
        }

        function withdrawModal() {
            window.app.withdrawModal();
        }

        function DeductModal(id) {
            window.app.DeductModal(id);
        }

        function ApproveModal(id) {
            window.app.ApproveModal(id);
        }

        $(document).ready(function () {
            $("body").tooltip({
                selector: '[data-toggle="tooltip"]',
                container: 'body'
            });
        });
    </script>
    <script type="module">
        window.app = new Vue({
            el: '#app',
            data() {
                return {
                    show: false,
                    formatted: '',
                    selected: '',
                    trigger: 0,
                    method: 'withdraw',
                    formmethod: 'edit',
                    userFound: {addedit: false, withdraw: false},
                    userTimer: null,

                    submittingApprove: false,
                    submittingWithdraw: false,
                    submittingClear: false,
                    formaddedit: {
                        fee: 0,
                        date_bank: '',
                        time_bank: '',
                        member_username: '',
                        member_code: '',
                        member_name: '',
                        member_account: '',
                        member_bank: '',
                        member_bank_pic: '',
                        amount: 0,
                        account_code: 0,
                    },
                    formaddeditnew: {
                        member_code: '',
                        member_username: '',
                        member_gameuser: '',
                        member_name: '',
                        member_account: '',
                        member_bank: '',
                        member_bank_pic: '',
                        amount: 0,
                        balance: 0,
                        date_record: '',
                        timedept: '',
                    },
                    formclear: {
                        remark: ''
                    },
                    formwithdraw: {
                        id: '',
                        user_name: '',
                        name: '',
                        amount: 0,
                        account_code: '',
                        remark_admin: '',
                        date_bank: '',
                        time_bank: '',
                        bankm: '',
                        balance: 0,
                    },
                    fields: [
                        {key: 'time', label: 'วันที่แจ้งถอน'},
                        {key: 'time_topup', label: 'วันที่โอน'},
                        {key: 'bank', label: 'ช่องทางที่โอน', class: 'text-center'},
                        {key: 'amount', label: 'จำนวนเงิน', class: 'text-right'},
                        {key: 'user_id', label: 'ผู้ทำรายการ', class: 'text-center'},
                    ],
                    items: [],
                    caption: null,
                    isBusy: false,

                    option: {
                        banks: [],
                        bankm: [],
                        account_code: []
                    },
                    bankm: [{value: '', text: '== ธนาคาร =='}],
                };
            },
            created() {
                this.audio = document.getElementById('alertsound');
                this.autoCnt(true);
            },
            mounted() {
                this.loadBank();
            },
            beforeDestroy() {
                if (this.userTimer) {
                    clearTimeout(this.userTimer);
                    this.userTimer = null;
                }
            },
            destroyed() {
                if (this.userTimer) {
                    clearTimeout(this.userTimer);
                    this.userTimer = null;
                }
            },
            methods: {
                onContext(ctx) {
                    // The date formatted in the locale, or the `label-no-date-selected` string
                    this.formatted = ctx.selectedFormatted
                    // The following will be an empty string until a valid date is entered
                    this.selected = ctx.selectedYMD
                },
                clearModal(code) {
                    this.code = null;
                    this.formclear = {
                        remark: '',

                    }
                    this.formmethod = 'clear';

                    this.show = false;
                    this.$nextTick(() => {
                        this.show = true;
                        this.code = code;
                        this.$refs.clear.show();

                    })
                },
                editModal(code) {
                    this.code = null;
                    this.formaddedit = {
                        fee: 0,
                        date_bank: '',
                        time_bank: '',
                        remark_admin: '',
                        member_username: '',
                        member_code: '',
                        member_name: '',
                        member_account: '',
                        member_bank: '',
                        member_bank_pic: '',
                        amount: 0,
                        account_code: 0,
                        member_qr_pic: '',
                    }
                    this.formmethod = 'edit';

                    this.show = false;
                    this.$nextTick(() => {
                        this.show = true;
                        this.code = code;
                        this.loadData();
                        this.$refs.addedit.show();

                    })
                },
                addModal() {
                    this.code = null;
                    this.formaddeditnew = {
                        member_code: '',
                        member_username: '',
                        member_gameuser: '',
                        member_name: '',
                        member_account: '',
                        member_bank: '',
                        member_bank_pic: '',
                        amount: '',
                        balance: 0,
                        date_record: '',
                        timedept: '',
                    }
                    this.formmethod = 'add';

                    this.show = false;
                    this.$nextTick(() => {
                        this.show = true;
                        this.$refs.addeditnew.show();

                    })
                },
                withdrawModal() {
                    this.code = null;
                    const now = new Date();
                    const yyyy = now.getFullYear();
                    const mm = String(now.getMonth() + 1).padStart(2, '0');
                    const dd = String(now.getDate()).padStart(2, '0');
                    const HH = String(now.getHours()).padStart(2, '0');
                    const II = String(now.getMinutes()).padStart(2, '0');

                    this.formwithdraw = {
                        id: '',
                        user_name: '',
                        name: '',
                        amount: 0,
                        balance: 0,
                        bankm: '',
                    };
                    this.userFound.withdraw = false;
                    this.method = 'withdraw';

                    this.show = false;
                    this.$nextTick(() => {
                        this.show = true;
                        this.$refs.withdraw.show();
                    });
                },
                ApproveModal(code) {
                    this.code = null;
                    const now = new Date();
                    const yyyy = now.getFullYear();
                    const mm = String(now.getMonth() + 1).padStart(2, '0');
                    const dd = String(now.getDate()).padStart(2, '0');
                    const HH = String(now.getHours()).padStart(2, '0');
                    const II = String(now.getMinutes()).padStart(2, '0');

                    this.formaddedit = {
                        fee: 0,
                        date_bank: `${yyyy}-${mm}-${dd}`,
                        time_bank: `${HH}:${II}`,
                        member_username: '',
                        member_code: '',
                        member_name: '',
                        member_account: '',
                        member_bank: '',
                        member_bank_pic: '',
                        amount: 0,
                        account_code: 0,
                    },


                    this.userFound.withdraw = false;
                    this.method = 'withdraw';

                    this.show = false;
                    this.$nextTick(() => {
                        this.code = code;
                        this.loadData();
                        this.show = true;
                        this.$refs.approve.show();
                    });
                },
                async loadUser_() {
                    const response = await axios.post("{{ url($menu->currentRoute.'/loaduser') }}", {id: this.formaddeditnew.user_name});
                    this.formaddeditnew = {
                        member_code: response.data.data.member_code,
                        member_username: response.data.data.member_username,
                        member_gameuser: response.data.data.member_gameuser,
                        member_name: response.data.data.member_name,
                        member_account: response.data.data.member_account,
                        member_bank: response.data.data.member_bank,
                        member_bank_pic: '/storage/bank_img/' + response.data.data.member_bank_pic,
                        balance: response.data.data.balance,
                        date_record: moment().format('YYYY-MM-DD'),
                        timedept: moment().format('HH:mm')
                    }
                },
                async loadData() {
                    const resp = await axios.post("{{ url($menu->currentRoute.'/loaddata') }}", {id: this.code});
                    const ok = resp?.data?.success === true;
                    this.userFound['withdraw'] = ok;
                    this.formaddedit = {
                        member_username: resp.data.data.member.user_name,
                        member_code: resp.data.data.member.code,
                        member_name: resp.data.data.member.name,
                        member_account: resp.data.data.member.acc_no,
                        member_bank: response.data.data.bank.name_th,

                        member_bank_pic: '/storage/bank_img/' + response.data.data.bank.filepic,
                        member_qr_pic: (response.data.data.member.pic_id ? '/storage/' + response.data.data.member.pic_id + '?v={{ time() }}' : ''),
                        amount: response.data.data.amount,
                        account_code: (response.data.data.account_code == 0 ? 9 : response.data.data.account_code),
                        fee: 0,
                        date_bank: moment().format('YYYY-MM-DD'),
                        time_bank: moment().format('HH:mm'),
                    };

                },
                async loadBank() {
                    const response = await axios.post("{{ url($menu->currentRoute.'/loadbank') }}");
                    this.option = {
                        account_code: response.data.banks
                    };
                },
                async loadUser(context = 'addedit') {
                    let id;
                    if (context === 'addedit') {
                        id = this.formaddedit.tranferer?.trim();
                    } else {
                        id = this.formwithdraw.user_name?.trim();
                    }

                    if (!id) {
                        this.userFound[context] = false;
                        return;
                    }

                    try {
                        const resp = await axios.post(
                            "{{ route('admin.'.$menu->currentRoute.'.loaduser') }}",
                            {id}
                        );
                        const ok = resp?.data?.success === true;
                        this.userFound[context] = ok;

                        if (context === 'withdraw' && ok) {
                            this.formwithdraw.name = resp?.data?.data?.member?.me?.name ?? '';
                            this.formwithdraw.id = resp?.data?.data?.member?.user ?? '';
                            this.formwithdraw.balance = resp?.data?.data?.balance?.credit ?? 0;


                            await this.myLog();
                            await this.loadBankAccountUser();
                        }


                    } catch (err) {
                        console.error('loadUser error:', err);
                        this.userFound[context] = false;
                    }
                },

                // debounce เวอร์ชันเดียว ใช้ได้ทั้งสอง modal
                debouncedLoadUser(context = 'addedit') {
                    if (this.userTimer) clearTimeout(this.userTimer);
                    this.userTimer = setTimeout(() => this.loadUser(context), 400);
                },
                async loadUserWithdraw() {
                    // ใช้ loader ปกติ เพื่อรวม logic เดิม
                    await this.loadUser('withdraw');
                },
                async myLog() {
                    this.items = [];
                    this.isBusy = true;
                    try {
                        const response = await axios.get("{{ route('admin.member.gamelog') }}", {
                            params: {id: this.formwithdraw.id, method: this.method}
                        });
                        this.caption = response?.data?.name || '';
                        this.items = response?.data?.list || [];
                    } finally {
                        // กันแฟลช: ดีเลย์ 150–250ms
                        setTimeout(() => {
                            this.isBusy = false;
                        }, 200);
                    }
                },

                async loadBankAccount() {

                    try {

                        const response = await axios.get("{{ route('admin.member.loadbankaccount') }}");
                        this.banks = response?.data?.banks || this.banks;
                    } catch (e) {
                        // คงค่า default ต่อไป
                    }
                },
                async loadBankAccountUser() {

                    try {

                        const response = await axios.post("{{ route('admin.member.loadbankaccountuser') }}", {id: this.formwithdraw.id});
                        this.bankm = response?.data?.banks || this.banks;
                    } catch (e) {
                        // คงค่า default ต่อไป
                    }
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
                        fee: this.formaddedit.fee,
                        date_bank: this.formaddedit.date_bank,
                        time_bank: this.formaddedit.time_bank,
                        remark_admin: this.formaddedit.remark_admin,
                        account_code: this.formaddedit.account_code,
                    });

                    formData.append('data', json);
                    // formData.append('filepic', $('input[name="filepic[image_0]"]')[1].files[0]);

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
                                $.each(response.data.message, function (index, value) {
                                    document.getElementById(index).classList.add("is-invalid");
                                });
                                $('input').on('focus', function (event) {
                                    event.preventDefault();
                                    this.toggleButtonDisable(true);
                                    event.stopPropagation();
                                    var id = $(this).attr('id');
                                    document.getElementById(id).classList.remove("is-invalid");
                                });
                            }

                        })
                        .catch(errors => console.log(errors));
                },
                addEditSubmit(event) {
                    event.preventDefault();
                    this.toggleButtonDisable(true);

                    var url = "{{ url($menu->currentRoute.'/create') }}";

                    let formData = new FormData();
                    const json = JSON.stringify({
                        member_code: this.formaddeditnew.member_code,
                        amount: this.formaddeditnew.amount,
                        date_record: this.formaddeditnew.date_record,
                        timedept: this.formaddeditnew.timedept,
                    });

                    formData.append('data', json);
                    // formData.append('filepic', $('input[name="filepic[image_0]"]')[1].files[0]);

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
                                $.each(response.data.message, function (index, value) {
                                    document.getElementById(index).classList.add("is-invalid");
                                });
                                $('input').on('focus', function (event) {
                                    event.preventDefault();
                                    this.toggleButtonDisable(true);
                                    event.stopPropagation();
                                    var id = $(this).attr('id');
                                    document.getElementById(id).classList.remove("is-invalid");
                                });
                            }

                        })
                        .catch(errors => console.log(errors));
                },
                clearSubmit(event) {
                    event.preventDefault();
                    this.toggleButtonDisable(true);

                    this.$http.post("{{ url($menu->currentRoute.'/clear') }}", {
                        id: this.code,
                        remark: this.formclear.remark
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
                            this.toggleButtonDisable(false);
                        });
                },
                fixSubmit(id) {
                    this.$bvModal.msgBoxConfirm('รายการนี้ กำลังประมวลผลการถอนออโต้ ถ้ามั่นใจว่าเป็นรายการ ค้าง โปรดกด Yes เพื่อแก้ไข', {
                        title: 'โปรดแน่ใจ ว่ารายการนี้ ค้างแน่นอน',
                        size: 'sm',
                        buttonSize: 'sm',
                        okVariant: 'danger',
                        okTitle: 'YES',
                        cancelTitle: 'NO',
                        footerClass: 'p-2',
                        hideHeaderClose: false,
                        centered: true
                    })
                        .then(value => {
                            if (value) {

                                this.$http.post("{{ url($menu->currentRoute.'/fix') }}", {
                                    id: id
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
                                        this.toggleButtonDisable(false);
                                    });

                            }
                        })
                        .catch(err => {
                            // An error occurred
                        })
                },
                async withdrawSubmit(event) {
                    event.preventDefault();
                    this.submittingWithdraw = true;
                    this.toggleButtonDisable(true);

                    try {
                        const resp = await this.$http.post("{{ route('admin.'.$menu->currentRoute.'.create') }}", this.formwithdraw);
                        const data = resp?.data || {};
                        this.showAlert(data);
                    } catch (e) {
                        console.log('withdraw error', e);
                        this.$bvModal.msgBoxOk('เกิดข้อผิดพลาดระหว่างเชื่อมต่อเซิร์ฟเวอร์', {
                            title: 'เชื่อมต่อไม่สำเร็จ',
                            okVariant: 'danger',
                            size: 'sm',
                            buttonSize: 'sm',
                            centered: true
                        });
                    } finally {
                        this.submittingWithdraw = false;
                        this.toggleButtonDisable(false);
                        if (window.LaravelDataTables?.["withdrawtable"]) {
                            window.LaravelDataTables["withdrawtable"].draw(false);
                        }
                    }
                },
                toggleButtonDisable(disabled) {
                    // hook สำหรับปุ่มอื่น ๆ ที่อยู่นอก scope Vue (ถ้ามี)
                    // เวอร์ชันนี้คุมผ่านแฟล็ก submitting เป็นหลักแล้ว
                    try {
                        const btn = document.getElementById('btnchecking');
                        if (btn) btn.disabled = !!disabled;
                    } catch (_) {
                    }
                },

                showAlert(data) {
                    const ok = data?.success === true;
                    this.$bvModal.msgBoxOk(data?.message || (ok ? 'ทำรายการสำเร็จ' : 'ทำรายการไม่สำเร็จ'), {
                        title: 'สถานะการทำรายการ',
                        okVariant: ok ? 'success' : 'danger',
                        size: 'sm',
                        buttonSize: 'sm',
                        centered: true
                    });
                },

                async DeductModal(code) {
                    try {
                        const response = await axios.post("{{ route('admin.'.$menu->currentRoute.'.loaddata') }}", {id: code});
                        const data = response?.data?.data || {};
                        const user = data?.member_user ? `แจ้งถอน ให้กับ ไอดี : ${data.member_user}` : 'ไม่พบข้อมูล';
                        const info = data?.amount ? `จำนวนเงิน : ${data.amount}` : 'ไม่พบข้อมูล';

                        const h = this.$createElement;
                        const messageVNode = h('div', [
                            h('p', {class: 'text-left'}, [
                                'ถ้าข้อมูลไอดีถูกต้องแล้ว ให้กด ',
                                h('strong', 'ตัดเครดิต'),
                                ' เพื่อทำการหักยอดเงิน ของลูกค้าในเกม.',
                            ]),
                            h('p', {class: 'text-left'}, [
                                'ถ้าข้อมูลรายการไม่ถูก หรือ มีแจ้งซ้ำ ให้กด ',
                                h('strong', 'ยกเลิกรายการ'),
                                ' เพื่อลบรายการ แจ้งถอนนนี้.',
                            ]),
                            h('p', {class: 'text-info mt-2'}, user),
                            h('p', {class: 'text-info mt-2'}, info)
                        ]);

                        const confirmed = await this.$bvModal.msgBoxConfirm([messageVNode], {
                            title: 'จัดการรายการถอน',
                            size: 'sm',
                            buttonSize: 'sm',
                            okTitle: '✅ ตัดเครดิต',
                            cancelTitle: '❌ ยกเลิกรายการ',
                            okVariant: 'success',
                            cancelVariant: 'danger',
                            centered: true,

                            // สำคัญ: กันกดด้านนอก/กด ESC แล้วไปต่อโดยไม่ได้ตั้งใจ
                            noCloseOnBackdrop: true,
                            noCloseOnEsc: true,

                            // ให้มีปุ่ม X ที่หัว modal
                            hideHeaderClose: false,
                            // ปิดแล้วให้คืนโฟกัสปุ่มเดิม (กัน Enter เคลื่อน focus เพี้ยน)
                            returnFocus: true,
                        });

                        if (confirmed === true) {
                            // ไปคอนเฟิร์มรอบสุดท้าย
                            await this.DeductWithdraw(code);
                        } else if (confirmed === false) {
                            // ผู้ใช้กด "User ไม่ถูกต้อง" → ย้อนขั้นตอน
                            // this.cancelDeposit(code);
                        }
                        // กรณีอื่น (เช่น programmatic close) → ไม่ทำอะไร
                    } catch (err) {
                        console.error('load data error:', err);
                        this.$bvModal.msgBoxOk('ไม่สามารถโหลดข้อมูลได้', {
                            title: 'ข้อผิดพลาด',
                            size: 'sm',
                            buttonSize: 'sm',
                            okVariant: 'danger',
                            centered: true,
                            noCloseOnBackdrop: true,
                            noCloseOnEsc: true,
                            hideHeaderClose: false,
                        });
                    }
                },

                async DeductWithdraw(code) {

                    const really = await this.$bvModal.msgBoxConfirm('โปรดยืนยันอีกครั้งเพื่อทำรายการ "ตัดเครดิต"', {
                        title: 'ยืนยันการทำรายการ',
                        size: 'sm',
                        buttonSize: 'sm',
                        okTitle: '✅ ยืนยัน',
                        cancelTitle: 'ยกเลิก',
                        okVariant: 'success',
                        cancelVariant: 'secondary',
                        centered: true,

                        // กันกดด้านนอก/ESC
                        noCloseOnBackdrop: true,
                        noCloseOnEsc: true,
                        hideHeaderClose: false,
                        returnFocus: true,
                    });

                    if (really !== true) {
                        // ผู้ใช้กดปิด/ยกเลิก → ไม่ทำอะไร
                        return;
                    }

                    try {
                        this.approving = true;
                        this._approving = true;

                        const resp = await this.$http.post("{{ route('admin.'.$menu->currentRoute.'.update') }}", {id: code});
                        const data = resp?.data || {};
                        this.showAlert(data);
                    } catch (err) {
                        console.error('approve error:', err);
                        this.$bvModal.msgBoxOk('เกิดข้อผิดพลาดระหว่างเชื่อมต่อเซิร์ฟเวอร์', {
                            title: 'เชื่อมต่อไม่สำเร็จ',
                            okVariant: 'danger',
                            size: 'sm',
                            buttonSize: 'sm',
                            centered: true,
                            noCloseOnBackdrop: true,
                            noCloseOnEsc: true,
                            hideHeaderClose: false,
                        });
                    } finally {
                        this.approving = false;
                        this._approving = false;
                        if (window.LaravelDataTables?.["withdrawtable"]) {
                            window.LaravelDataTables["withdrawtable"].draw(false);
                        }
                    }
                },

                async approveSubmit(event) {
                    event.preventDefault();
                    this.submittingWithdraw = true;
                    this.toggleButtonDisable(true);

                    const h = this.$createElement;
                    const messageVNode = h('div', [
                        h('p', {class: 'text-left'}, [
                            'ถ้าผู้ทำรายการ เลือกธนาคารที่ใช้ดำเนินการ เป็นประเภท ',
                            h('strong', 'Payment GateWay'),
                            ' ระบบจะส่งยอดถอนไปยัง ช่องทางที่เลือก.',
                        ]),
                    ]);

                    const confirmed = await this.$bvModal.msgBoxConfirm([messageVNode], {
                        title: 'ยืนยันการโอนเงิน',
                        size: 'sm',
                        buttonSize: 'sm',
                        okTitle: '✅ ยืนยันรายการ',
                        okVariant: 'success',
                        cancelVariant: 'danger',
                        centered: true,

                        // สำคัญ: กันกดด้านนอก/กด ESC แล้วไปต่อโดยไม่ได้ตั้งใจ
                        noCloseOnBackdrop: true,
                        noCloseOnEsc: true,

                        // ให้มีปุ่ม X ที่หัว modal
                        hideHeaderClose: false,
                        // ปิดแล้วให้คืนโฟกัสปุ่มเดิม (กัน Enter เคลื่อน focus เพี้ยน)
                        returnFocus: true,
                    });

                    if (confirmed === true) {
                        // ไปคอนเฟิร์มรอบสุดท้าย
                        await this.DeductWithdraw(code);
                    } else if (confirmed === false) {
                        // ผู้ใช้กด "User ไม่ถูกต้อง" → ย้อนขั้นตอน
                        // this.cancelDeposit(code);
                    }
                },

                async withdrawApprove(code) {
                    try {
                        const response = await axios.post("{{ route('admin.'.$menu->currentRoute.'.loaddata') }}", {id: code});
                        const data = response?.data?.data || {};
                        const user = data?.member_user ? `โอนเงินให้กับ ไอดี : ${data.member_user}` : 'ไม่พบข้อมูล';
                        const info = data?.amount ? `จำนวนเงิน : ${data.amount}` : 'ไม่พบข้อมูล';

                        const h = this.$createElement;
                        const messageVNode = h('div', [
                            h('p', {class: 'text-left'}, [
                                'ถ้าข้อมูลไอดีถูกต้องแล้ว ให้กด ',
                                h('strong', 'ตัดเครดิต'),
                                ' เพื่อทำการหักยอดเงิน ของลูกค้าในเกม.',
                            ]),
                            h('p', {class: 'text-left'}, [
                                'ถ้าข้อมูลรายการไม่ถูก หรือ มีแจ้งซ้ำ ให้กด ',
                                h('strong', 'ยกเลิกรายการ'),
                                ' เพื่อลบรายการ แจ้งถอนนนี้.',
                            ]),
                            h('p', {class: 'text-info mt-2'}, user),
                            h('p', {class: 'text-info mt-2'}, info)
                        ]);

                        const confirmed = await this.$bvModal.msgBoxConfirm([messageVNode], {
                            title: 'จัดการรายการถอน',
                            size: 'sm',
                            buttonSize: 'sm',
                            okTitle: '✅ ตัดเครดิต',
                            cancelTitle: '❌ ยกเลิกรายการ',
                            okVariant: 'success',
                            cancelVariant: 'danger',
                            centered: true,

                            // สำคัญ: กันกดด้านนอก/กด ESC แล้วไปต่อโดยไม่ได้ตั้งใจ
                            noCloseOnBackdrop: true,
                            noCloseOnEsc: true,

                            // ให้มีปุ่ม X ที่หัว modal
                            hideHeaderClose: false,
                            // ปิดแล้วให้คืนโฟกัสปุ่มเดิม (กัน Enter เคลื่อน focus เพี้ยน)
                            returnFocus: true,
                        });

                        if (confirmed === true) {
                            // ไปคอนเฟิร์มรอบสุดท้าย
                            await this.DeductWithdraw(code);
                        } else if (confirmed === false) {
                            // ผู้ใช้กด "User ไม่ถูกต้อง" → ย้อนขั้นตอน
                            // this.cancelDeposit(code);
                        }
                        // กรณีอื่น (เช่น programmatic close) → ไม่ทำอะไร
                    } catch (err) {
                        console.error('load data error:', err);
                        this.$bvModal.msgBoxOk('ไม่สามารถโหลดข้อมูลได้', {
                            title: 'ข้อผิดพลาด',
                            size: 'sm',
                            buttonSize: 'sm',
                            okVariant: 'danger',
                            centered: true,
                            noCloseOnBackdrop: true,
                            noCloseOnEsc: true,
                            hideHeaderClose: false,
                        });
                    }
                },
            }
        });
    </script>
@endpush

