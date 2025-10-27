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
                                v-model.number="formaddedit.fee"
                                type="number"
                                size="sm"
                                placeholder=""
                                autocomplete="off"
                        ></b-form-input>
                    </b-form-group>
                </b-col>
                <b-col cols="12">


                </b-col>

            </b-form-row>

            <b-form-row>
                <b-col>
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
                <b-col>
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

            <b-button type="submit" class="btn-block" variant="primary" :disabled="!userFound.withdraw || submittingApprove || approvePrompting">
                <span v-if="submittingApprove"><b-spinner small class="mr-1"></b-spinner>กำลังบันทึก...</span>
                <span v-else>บันทึก</span>
            </b-button>

        </b-form>
    </b-container>
</b-modal>


<b-modal ref="withdraw" id="withdraw" centered size="xl" title="เพิ่ม รายการถอน" :no-stacking="true"
         :no-close-on-backdrop="true" :hide-footer="true" :lazy="true" @shown="onWithdrawShown">
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
                                    description="ระบุ User ID ที่ต้องการ ถอนเงิน รายการนี้">
                                <b-input-group>
                                    <b-form-input
                                            id="user_name"
                                            ref="user_name"
                                            v-model.trim="formwithdraw.user_name"
                                            :disabled="searchingWithdraw || searchedWithdraw"
                                            type="text"
                                            size="sm"
                                            placeholder="User ID"
                                            autocomplete="off"
                                    ></b-form-input>
                                    <b-input-group-append>
                                        <b-button variant="success"
                                                  @click="loadUserWithdraw"
                                                  :disabled="searchingWithdraw || !formwithdraw.user_name">
                                            <span v-if="searchingWithdraw"><b-spinner small class="mr-1"></b-spinner>กำลังค้นหา...</span>
                                            <span v-else>ค้นหา</span>
                                        </b-button>
                                    </b-input-group-append>
                                </b-input-group>
                                <small v-if="searchedWithdraw && userFound.withdraw" class="text-success d-block mt-1">
                                    ✅ พบผู้ใช้แล้ว
                                </small>
                                <small v-else-if="searchedWithdraw && !userFound.withdraw"
                                       class="text-danger d-block mt-1">
                                    ไม่พบผู้ใช้
                                </small>
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
                                          description="ระบุจำนวนเงิน ที่ต้องการถอน">
                                <b-form-input
                                        id="amount"
                                        ref="amount"
                                        v-model.number="formwithdraw.amount"
                                        type="number"
                                        size="sm"
                                        placeholder="จำนวนเงินที่ต้องการ ถอน"
                                        min="1"
                                        :max="maxWithdraw"
                                        step="1"
                                        autocomplete="off"
                                        required
                                        :disabled="!userFound.withdraw || !searchedWithdraw"
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
                                        :disabled="!userFound.withdraw || !searchedWithdraw"
                                ></b-form-select>
                            </b-form-group>
                        </b-col>
                    </b-form-row>

                    <b-form-row class="mb-sm-3">
                        <b-button type="submit"
                                  variant="primary"
                                  class="btn-block"
                                  :disabled="submittingWithdraw || searchingWithdraw || !userFound.withdraw || !searchedWithdraw || !formwithdraw.amount || !formwithdraw.bankm">
                            <span v-if="submittingWithdraw"><b-spinner small class="mr-1"></b-spinner>กำลังบันทึก...</span>
                            <span v-else>บันทึก</span>
                        </b-button>
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
                                :items="items" :fields="fields"
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
                            <template #cell(user_id)="data"><span v-html="data.value"></span></template>
                            <template #cell(status)="data"><span v-html="data.value"></span></template>
                            <template #cell(action)="data"><span v-html="data.value"></span></template>
                            <template #cell(changepass)="data"><span v-html="data.value"></span></template>
                        </b-table>

                        <template #overlay>
                            <div class="text-center">
                                <b-spinner class="mb-2"></b-spinner>
                                <div>กำลังโหลดรายการล่าสุด…</div>
                            </div>
                        </template>
                    </b-overlay>
                </b-col>
            </b-form-row>
        </b-form>
    </b-container>
</b-modal>

@push('scripts')
    <script type="text/javascript">
        // === ฟังก์ชัน global: ใส่ throttle กันคลิกรัว ===
        (function () {
            let lastCall = 0;
            window.DeductModal = function (id) {
                const now = Date.now();
                if (now - lastCall < 600) return; // throttle 600ms
                lastCall = now;
                if (window.app && typeof window.app.DeductModal === 'function') {
                    window.app.DeductModal(id);
                }
            };
        })();

        function clearModal(id) {
            window.app.clearModal(id);
        }

        function fixModal(id) {
            window.app.fixSubmit(id);
        }

        function withdrawModal() {
            window.app.withdrawModal();
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
                    maxWithdraw : 0,
                    show: false,
                    formatted: '',
                    selected: '',
                    trigger: 0,
                    method: 'withdraw',
                    formmethod: 'edit',
                    userFound: {addedit: false, withdraw: false},
                    userTimer: null,

                    // flags
                    submittingApprove: false,
                    submittingWithdraw: false,
                    submittingClear: false,
                    approving: false,        // เพิ่มให้ตรงกับที่อ้างถึงในโค้ด
                    _approving: false,       // เพิ่มให้ตรงกับที่อ้างถึงในโค้ด

                    // สถานะค้นหา/ค้นหาสำเร็จ (โมดอลถอน)
                    searchingWithdraw: false,
                    searchedWithdraw: false,

                    // === ใหม่: ล็อก/คูลดาวน์ confirm อนุมัติถอน ===
                    approvePrompting: false,
                    approveCooldownUntil: 0,

                    // === ใหม่: ล็อกกันเปิด DeductModal ซ้อน ===
                    lock: {
                        deductModal: false,
                    },

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
                        {key: 'time', label: 'วันที่รายการ'},
                        {key: 'amount', label: 'จำนวนเงิน', class: 'text-right'},
                        {key: 'user_id', label: 'ผู้ทำรายการ', class: 'text-center'},
                        {key: 'status', label: 'สถานะ', class: 'text-center'},
                    ],
                    items: [],
                    caption: '',
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
                this.autoCnt?.(true);
            },
            mounted() {

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
                    this.formatted = ctx.selectedFormatted
                    this.selected = ctx.selectedYMD
                },
                clearModal(code) {
                    this.code = null;
                    this.formclear = {remark: ''}
                    this.formmethod = 'clear';
                    this.show = false;
                    this.$nextTick(() => {
                        this.show = true;
                        this.code = code;
                        this.$refs.clear.show();
                    })
                },
                onWithdrawShown() {
                    this.$nextTick(() => {
                        const r = this.$refs.user_name
                        if (r?.focus) r.focus()                          // method ของ b-form-input
                        else if (r?.$el?.querySelector) r.$el.querySelector('input')?.focus() // fallback
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
                    this.$nextTick(async () => {
                        this.show = true;
                        this.code = code;
                        await this.loadData();
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
                    this.formwithdraw = {
                        id: '',
                        user_name: '',
                        name: '',
                        amount: '',
                        balance: 0,
                        bankm: '',
                    };
                    this.userFound.withdraw = false;
                    this.method = 'withdraw';
                    this.items = [];
                    this.isBusy = false;

                    // รีเซ็ตสถานะค้นหา
                    this.searchingWithdraw = false;
                    this.searchedWithdraw = false;

                    this.show = false;
                    this.$nextTick(() => {
                        this.show = true;
                        this.$refs.withdraw.show();
                        // this.$refs.user_name?.focus();
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
                    };

                    this.userFound.withdraw = false;
                    this.method = 'withdraw';

                    this.show = false;
                    this.$nextTick(async () => {
                        this.code = code;
                        await this.loadData();
                        await this.loadBank();
                        this.show = true;
                        this.$refs.approve.show();
                    });
                },
                async loadData() {
                    const resp = await axios.post("{{ url($menu->currentRoute.'/loaddata') }}", {id: this.code});
                    const ok = resp?.data?.success === true;
                    this.userFound['withdraw'] = ok;
                    this.formaddedit = {
                        member_username: resp.data.data.member_user,
                        member_code: resp.data.data.member_code,
                        member_name: resp.data.data.member_bank.account_name,
                        member_account: resp.data.data.member_bank.account_no,
                        member_bank: resp.data.data.member_bank.bank.name_th,
                        member_bank_pic: 'https://office.superrich69.com/img/' + resp.data.data.member_bank.bank.filepic,
                        amount: resp.data.data.amount,
                        account_code: (resp.data.data.bank),
                        fee: 0,
                        date_bank: moment().format('YYYY-MM-DD'),
                        time_bank: moment().format('HH:mm'),
                    };
                },
                async loadBank() {
                    try {
                        const response = await axios.post("{{ route('admin.'.$menu->currentRoute.'.loadbank') }}");
                        this.option.account_code = response?.data?.banks || this.banks;
                    } catch (e) { /* keep default */ }
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
                            this.maxWithdraw = this.formwithdraw.balance;
                            await this.myLog();
                            await this.loadBankAccountUser();
                            this.$nextTick(() => {
                                // สมมติ input amount มี ref="amount"
                                this.$refs.amount?.focus();
                            });
                        }

                    } catch (err) {
                        console.error('loadUser error:', err);
                        this.userFound[context] = false;
                    }
                },
                debouncedLoadUser(context = 'addedit') {
                    if (this.userTimer) clearTimeout(this.userTimer);
                    this.userTimer = setTimeout(() => this.loadUser(context), 400);
                },
                async loadUserWithdraw() {   // เดิม loadUserRefill
                    if (!this.formwithdraw.user_name) return;
                    this.searchingWithdraw = true;
                    try {
                        await this.loadUser('withdraw');                 // เซ็ต userFound.deposit
                        this.searchedWithdraw = !!this.userFound.withdraw; // ตอนนี้ช่องจะ enable แล้ว

                        // โฟกัสหลังช่องถูก enable
                        await this.$nextTick();
                        const r = this.$refs.amount;
                        if (r?.focus) r.focus();                        // BootstrapVue component method
                        else if (r?.$el?.querySelector) r.$el.querySelector('input')?.focus(); // fallback DOM
                    } finally {
                        this.searchingWithdraw = false;
                    }
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
                        setTimeout(() => {
                            this.isBusy = false;
                        }, 200);
                    }
                },
                async loadBankAccount() {
                    try {
                        const response = await axios.get("{{ route('admin.member.loadbankaccount') }}");
                        this.banks = response?.data?.banks || this.banks;
                    } catch (e) { /* keep default */ }
                },
                async loadBankAccountUser() {
                    try {
                        const response = await axios.post("{{ route('admin.member.loadbankaccountuser') }}", {id: this.formwithdraw.id});
                        this.bankm = response?.data?.banks || this.banks;
                    } catch (e) { /* keep default */ }
                },
                addEditSubmitNew(event) {
                    event.preventDefault();
                    this.toggleButtonDisable(true);

                    let url = this.formmethod === 'add'
                        ? "{{ url($menu->currentRoute.'/create') }}"
                        : "{{ url($menu->currentRoute.'/update') }}/" + this.code;

                    let formData = new FormData();
                    const json = JSON.stringify({
                        fee: this.formaddedit.fee,
                        date_bank: this.formaddedit.date_bank,
                        time_bank: this.formaddedit.time_bank,
                        remark_admin: this.formaddedit.remark_admin,
                        account_code: this.formaddedit.account_code,
                    });

                    formData.append('data', json);
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
                                window.LaravelDataTables["dataTableBuilder"]?.draw(false);
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
                                window.LaravelDataTables["dataTableBuilder"]?.draw(false);
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
                            window.LaravelDataTables["dataTableBuilder"]?.draw(false);
                        })
                        .catch(exception => {
                            console.log('error');
                            this.toggleButtonDisable(false);
                        });
                },
                async withdrawSubmit(event) {
                    event.preventDefault();
                    this.submittingWithdraw = true;
                    this.toggleButtonDisable(true);
                    this.$blockUI();
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
                        this.$unblockUI();
                        this.submittingWithdraw = false;
                        this.toggleButtonDisable(false);
                        if (window.LaravelDataTables?.["withdrawtable"]) {
                            window.LaravelDataTables["withdrawtable"].draw(false);
                        }
                    }
                },
                // ====== แก้ให้ไม่เปิดซ้อน: re-entry guard ภายใน Vue ======
                // ====== แก้ให้ยกเลิกแล้ว "ถามซ้ำ" จริง ๆ ======
                async DeductModal(code) {
                    if (this.lock.deductModal) return; // กันกดรัว/เปิดซ้อน
                    this.lock.deductModal = true;

                    try {
                        const response = await axios.post("{{ route('admin.'.$menu->currentRoute.'.loaddata') }}", { id: code });
                        const data = response?.data?.data || {};
                        const user = data?.member_user ? `แจ้งถอน ให้กับ ไอดี : ${data.member_user}` : 'ไม่พบข้อมูล';
                        const info = data?.amount ? `จำนวนเงิน : ${data.amount}` : 'ไม่พบข้อมูล';

                        const h = this.$createElement;
                        const messageVNode = h('div', [
                            h('p', { class: 'text-left' }, [
                                'ถ้าข้อมูลไอดีถูกต้องแล้ว ให้กด ', h('strong', 'ตัดเครดิต'), ' เพื่อทำการหักยอดเงิน ของลูกค้าในเกม.'
                            ]),
                            h('p', { class: 'text-left' }, [
                                'ถ้าข้อมูลรายการไม่ถูก หรือ มีแจ้งซ้ำ ให้กด ', h('strong', 'ยกเลิกรายการ'),
                                ' เพื่อลบรายการ แจ้งถอนนนี้.'
                            ]),
                            h('p', { class: 'text-info mt-2' }, user),
                            h('p', { class: 'text-info mt-2' }, info),
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
                            noCloseOnBackdrop: true,
                            noCloseOnEsc: true,
                            hideHeaderClose: false,
                            returnFocus: true,
                        });

                        if (confirmed === true) {
                            await this.DeductWithdraw(code);
                        } else if (confirmed === false) {
                            await this.cancelWithdraw(code);
                        }
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
                    } finally {
                        setTimeout(() => { this.lock.deductModal = false; }, 250);
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

                        noCloseOnBackdrop: true,
                        noCloseOnEsc: true,
                        hideHeaderClose: false,
                        returnFocus: true,
                    });

                    if (really !== true) {
                        return;
                    }

                    try {
                        this.approving = true;
                        this._approving = true;
                        this.$blockUI();

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
                        this.$unblockUI();
                        this.approving = false;
                        this._approving = false;
                        if (window.LaravelDataTables?.["withdrawtable"]) {
                            window.LaravelDataTables["withdrawtable"].draw(false);
                        }
                    }
                },

                // ====== อนุมัติถอน (สองชั้น) พร้อมกันกดรัว ======
                async approveSubmit(event) {
                    event.preventDefault();

                    // คูลดาวน์/ล็อกตอนเปิด confirm ชั้นแรก
                    const now = Date.now();
                    if (this.approvePrompting || now < this.approveCooldownUntil) return;
                    this.approvePrompting = true;

                    // snapshot กัน reactive เปลี่ยนระหว่างเปิดยืนยัน
                    const payload = {
                        id: this.code,
                        ...JSON.parse(JSON.stringify(this.formaddedit)),
                    };

                    try {
                        const h = this.$createElement;
                        const messageVNode = h('div', [
                            h('p', { class: 'text-left' }, [
                                '- ถ้าธนาคารที่ เลือก ',
                                h('strong', 'ไม่ใช่ Payment GateWay'),
                                ' นั่นหมายถึง ผู้ทำรายการ ต้องดำเนินการ โอนเงินให้ลูกค้าเอง.',
                            ]),
                            h('p', { class: 'text-left' }, [
                                '- ถ้าธนาคารที่ เลือก เป็น ',
                                h('strong', 'Payment GateWay'),
                                ' ระบบจะส่งยอดถอนไปยัง ช่องทางที่เลือก สถานะรายการจะเป็น กำลังดำเนินการ แล้วโปรดรอ ผลการโอนจาก ช่องทางที่เลือก.',
                            ]),
                            h('p', { class: 'text-left' }, [
                                '- กรณี Payment ส่งการโอน นอกเหนือจาก สำเร็จ รายการจะ ปรับสถานะ เป็น ไม่อนุมัติ',
                                ' ยอดเงิน จะไม่คืนให้ ลูกค้า ให้ทีมงาน ดำเนินการอีกครั้ง โดยเลือก ธนาคารอื่น',
                            ]),
                        ]);

                        const confirmed = await this.$bvModal.msgBoxOk([messageVNode], {
                            title: 'ยืนยันการโอนเงิน',
                            size: 'sm',
                            buttonSize: 'sm',
                            okTitle: '✅ ยืนยันรายการ',
                            okVariant: 'success',
                            cancelVariant: 'danger',
                            centered: true,
                            noCloseOnBackdrop: true,
                            noCloseOnEsc: false,
                            hideHeaderClose: false,
                            returnFocus: true,
                        });

                        if (confirmed === true) {
                            await this.approveWithdraw(payload);
                        }
                    } finally {
                        this.approvePrompting = false;
                        this.approveCooldownUntil = Date.now() + 1000; // คูลดาวน์ 1s
                    }
                },

                async approveWithdraw(payload) {
                    const really = await this.$bvModal.msgBoxConfirm('โปรดกด "ยืนยัน" อีกครั้ง เพื่อดำเนินการ', {
                        title: 'ยืนยันการทำรายการ',
                        size: 'sm',
                        buttonSize: 'sm',
                        okTitle: '✅ ยืนยัน',
                        cancelTitle: 'ยกเลิก',
                        okVariant: 'success',
                        cancelVariant: 'secondary',
                        centered: true,
                        noCloseOnBackdrop: true,
                        noCloseOnEsc: true,
                        hideHeaderClose: false,
                        returnFocus: true,
                    });
                    if (really !== true) return;

                    try {
                        this.submittingApprove = true;
                        this.$blockUI();
                        // แปลงค่าที่ควรเป็น number ให้ชัดก่อนส่ง
                        payload.fee = Number(payload.fee) || 0;

                        // (ออปชัน) รวมวัน+เวลาเป็น transfer_at ถ้าหลังบ้านต้องการ
                        // payload.transfer_at = `${payload.date_bank} ${payload.time_bank}:00`;

                        const resp = await this.$http.post("{{ route('admin.'.$menu->currentRoute.'.approve') }}", payload);
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
                        this.$unblockUI();
                        this.submittingApprove = false;
                        if (window.LaravelDataTables?.["withdrawtable"]) {
                            window.LaravelDataTables["withdrawtable"].draw(false);
                        }
                    }
                },

                async cancelWithdraw(code) {

                    const really = await this.$bvModal.msgBoxConfirm('โปรดกด "ยืนยัน" อีกครั้ง ถ้าแต่ใจว่า ต้องการลบข้อมูล', {
                        title: 'ยืนยันการทำรายการ',
                        size: 'sm',
                        buttonSize: 'sm',
                        okTitle: '✅ ยืนยัน',
                        cancelTitle: 'ยกเลิก',
                        okVariant: 'success',
                        cancelVariant: 'secondary',
                        centered: true,

                        noCloseOnBackdrop: true,
                        noCloseOnEsc: true,
                        hideHeaderClose: false,
                        returnFocus: true,
                    });

                    if (really !== true) {
                        return;
                    }

                    try {
                        this.approving = true;
                        this._approving = true;

                        const resp = await this.$http.post("{{ route('admin.'.$menu->currentRoute.'.delete') }}", {id: code});
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
                async delModal(code) {

                    const confirmed = await this.$bvModal.msgBoxConfirm('ต้องการดำเนินการ ลบข้อมูล รายการนี้ หรือไม่', {
                        title: 'ยืนยันการทำรายการ',
                        size: 'sm',
                        buttonSize: 'sm',
                        okTitle: '✅ ยืนยัน',
                        cancelTitle: 'ยกเลิก',
                        okVariant: 'success',
                        cancelVariant: 'secondary',
                        centered: true,
                        noCloseOnBackdrop: true,
                        noCloseOnEsc: true,
                        hideHeaderClose: false,
                        returnFocus: true,
                    });

                    if (confirmed === true) {
                        await this.cancelWithdraw(code);
                    }

                },
                toggleButtonDisable(disabled) {
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
            }
        });
    </script>
@endpush
