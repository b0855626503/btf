{{-- Admin Deposit/Refill Modals (renamed state to "deposit") --}}
<b-modal ref="addedit" id="addedit" centered size="sm" title="ตรวจสอบ ข้อมูลรายการฝาก" :no-stacking="true"
         :no-close-on-backdrop="true" :hide-footer="true" :lazy="true"  @shown="onAddEditShown">
    <b-container class="bv-example-row">
        <b-form @submit.prevent="addEditSubmitNew" v-if="show" id="frmaddedit" ref="frmaddedit">
            <b-form-row>
                <b-col>
                    <b-form-group
                            id="input-group-bank"
                            label="ธนาคาร:"
                            label-for="bank"
                            description="">
                        <b-form-input
                                id="bank"
                                v-model="formaddedit.bank"
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
                            id="input-group-time"
                            label="เวลาธนาคาร:"
                            label-for="time"
                            description="">
                        <b-form-input
                                id="time"
                                v-model="formaddedit.time"
                                type="text"
                                size="sm"
                                placeholder=""
                                autocomplete="off"
                                plaintext
                        ></b-form-input>
                    </b-form-group>
                </b-col>
            </b-form-row>

            <b-form-row>
                <b-col>
                    <b-form-group
                            id="input-group-value"
                            label="จำนวนเงินที่เติม:"
                            label-for="value"
                            description="">
                        <b-form-input
                                id="value"
                                v-model="formaddedit.value"
                                type="text"
                                size="sm"
                                placeholder=""
                                autocomplete="off"
                                plaintext
                        ></b-form-input>
                    </b-form-group>
                </b-col>
            </b-form-row>

            <b-form-row>
                <b-col>
                    <b-form-group
                            id="input-group-tranferer"
                            label="User:"
                            label-for="tranferer"
                            description="ระบุ User ID ที่ต้องการ เติมเงินรายการนี้">
                        <b-form-input
                                id="tranferer"
                                ref="tranferer"
                                v-model.trim="formaddedit.tranferer"
                                type="text"
                                size="sm"
                                placeholder="User ID"
                                autocomplete="off"
                                @input="debouncedLoadUser('addedit')"
                        ></b-form-input>
                    </b-form-group>
                </b-col>
            </b-form-row>

            <b-form-row>
                <b-col>
                    <small v-if="formaddedit.tranferer && userFound.addedit === false" class="text-danger">
                        ไม่พบข้อมูลสมาชิกในระบบ
                    </small>
                    <small v-else-if="formaddedit.tranferer && userFound.addedit === true" class="text-success">
                        พบข้อมูลสมาชิกในระบบ
                    </small>
                </b-col>
            </b-form-row>

            <b-button
                    class="float-right"
                    type="submit"
                    variant="primary"
                    id="btnchecking"
                    :disabled="!userFound.addedit || submittingAddEdit"
            >
                <span v-if="submittingAddEdit"><b-spinner small class="mr-1"></b-spinner>กำลังบันทึก...</span>
                <span v-else>บันทึก</span>
            </b-button>
        </b-form>
    </b-container>
</b-modal>

<b-modal ref="deposit" id="deposit" centered size="xl" title="เพิ่ม รายการฝาก" :no-stacking="true"
         :no-close-on-backdrop="true" :hide-footer="true" :lazy="true" @shown="onDepositShown">
    <b-container class="bv-example-row">
        <b-form @submit.prevent="depositSubmit" v-if="show">
            <input type="hidden" id="id" :value="formdeposit.id" required>
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
                                            ref="user_name"
                                            v-model.trim="formdeposit.user_name"
                                            :disabled="searchingDeposit || searchedDeposit"
                                            type="text"
                                            size="sm"
                                            placeholder="User ID"
                                            autocomplete="off"
                                    ></b-form-input>
                                    <b-input-group-append>
                                        <b-button variant="success"
                                                  @click="loadUserDeposit"
                                                  :disabled="searchingDeposit || !formdeposit.user_name">
                                            <span v-if="searchingDeposit"><b-spinner small class="mr-1"></b-spinner>กำลังค้นหา...</span>
                                            <span v-else>ค้นหา</span>
                                        </b-button>
                                    </b-input-group-append>
                                </b-input-group>
                                <small v-if="searchedDeposit && userFound.deposit" class="text-success d-block mt-1">
                                    ✅ พบผู้ใช้แล้ว
                                </small>
                                <small v-else-if="searchedDeposit && !userFound.deposit"
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
                                        v-model="formdeposit.name"
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
                            <b-form-group id="input-group-1" label="จำนวนเงิน:" label-for="amount"
                                          description="ระบุจำนวนเงิน ที่ต้องการเติม">
                                <b-form-input
                                        id="amount"
                                        ref="amount"
                                        v-model.number="formdeposit.amount"
                                        type="number"
                                        size="sm"
                                        placeholder="จำนวนเงิน"
                                        min="1"
                                        step="1"
                                        autocomplete="off"
                                        required
                                        :disabled="!userFound.deposit || !searchedDeposit"
                                ></b-form-input>
                            </b-form-group>
                        </b-col>
                    </b-form-row>

                    <b-form-row>
                        <b-col>
                            <b-form-group id="input-group-2" label="ช่องทางที่ฝาก:" label-for="account_code">
                                <b-form-select
                                        id="account_code"
                                        v-model="formdeposit.account_code"
                                        :options="banks"
                                        size="sm"
                                        required
                                        :disabled="!userFound.deposit || !searchedDeposit"
                                ></b-form-select>
                            </b-form-group>
                        </b-col>
                    </b-form-row>

                    <b-form-row>
                        <b-col>
                            <b-form-group id="input-group-date" label="วันที่โอน:" label-for="date_bank" description="">
                                <b-form-datepicker
                                        id="date_bank"
                                        v-model="formdeposit.date_bank"
                                        size="sm"
                                        placeholder=""
                                        autocomplete="off"
                                        locale="th-TH"
                                        :date-format-options="{ year: 'numeric', month: '2-digit', day: '2-digit' }"
                                        @context="onContext"
                                        :disabled="!userFound.deposit || !searchedDeposit"
                                ></b-form-datepicker>
                            </b-form-group>
                        </b-col>

                        <b-col>
                            <b-form-group id="input-group-timebank" label="เวลาที่โอน:" label-for="time_bank"
                                          description="">
                                <b-form-timepicker
                                        id="time_bank"
                                        v-model="formdeposit.time_bank"
                                        type="text"
                                        size="sm"
                                        placeholder=""
                                        autocomplete="off"
                                        :hour12="false"
                                        :disabled="!userFound.deposit || !searchedDeposit"
                                ></b-form-timepicker>
                            </b-form-group>
                        </b-col>
                    </b-form-row>

                    <b-form-row class="mb-sm-3">
                        <b-button type="submit" variant="primary"
                                  :disabled="submittingDeposit || searchingDeposit || !userFound.deposit || !searchedDeposit || !formdeposit.amount || !formdeposit.account_code"
                                  class="btn-block">
                            <span v-if="submittingDeposit"><b-spinner small class="mr-1"></b-spinner>กำลังบันทึก...</span>
                            <span v-else>บันทึก</span>
                        </b-button>
                    </b-form-row>
                </b-col>

                <b-col>
                    <p class="text-center">ข้อมูลการฝากเงิน 5 รายการล่าสุด</p>

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
    <script>
        function clearModal(id) {
            window.app.clearModal(id);
        }

        function approveModal(id) {
            window.app.approveModal(id);
        }

        function deposit() {        // เดิม refill()
            window.app.depositModal();
        }

        $(document).ready(function () {
            $("body").tooltip({selector: '[data-toggle="tooltip"]', container: 'body'});
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
                    formmethod: 'edit',
                    // แยก state พบผู้ใช้ต่อ modal
                    userFound: { addedit: false, deposit: false },
                    userTimer: null,

                    submittingSearch: false,
                    submittingAddEdit: false,
                    submittingDeposit: false,
                    submittingClear: false,

                    searchingDeposit: false,
                    searchedDeposit: false,

                    // 🔒 กันกดรัวตอนเปิด approveModal
                    approvePrompting: false,
                    approveCooldownUntil: 0,

                    formaddedit: {
                        tranferer: '',
                        bank: '',
                        time: '',
                        value: '',
                    },
                    formdeposit: {
                        id: '',
                        user_name: '',
                        name: '',
                        amount: 0,
                        account_code: '',
                        remark_admin: '',
                        date_bank: '',
                        time_bank: '',
                        one_time_password: ''
                    },
                    formclear: { remark: '' },

                    fields: [
                        {key: 'time', label: 'วันที่รายการ'},
                        {key: 'bank', label: 'ช่องทางฝาก', class: 'text-center'},
                        {key: 'amount', label: 'จำนวนเงิน', class: 'text-right'},
                        {key: 'user_id', label: 'ผู้ทำรายการ', class: 'text-center'},
                        {key: 'status', label: 'สถานะ', class: 'text-center'},
                    ],
                    items: [],
                    caption: null,
                    isBusy: false,

                    option: {banks: []},
                    banks: [{value: '', text: '== ธนาคาร =='}],

                    method: 'deposit', // ใช้ตอนโหลด log
                };
            },
            created() {
                this.audio = document.getElementById('alertsound');
                if (this.autoCnt) this.autoCnt(true);
            },
            mounted() {
                this.loadBankAccount();
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
                    this.formatted = ctx.selectedFormatted || '';
                    this.selected = ctx.selectedYMD || '';
                },

                // ---------- Modal Actions ----------
                clearModal(code) {
                    this.code = null;
                    this.formclear = {remark: ''};
                    this.formmethod = 'clear';

                    this.show = false;
                    this.$nextTick(() => {
                        this.show = true;
                        this.code = code;
                        this.$refs.clear && this.$refs.clear.show();
                    });
                },
                onAddEditShown() {
                    this.$nextTick(() => {
                        const r = this.$refs.tranferer
                        if (r?.focus) r.focus()                          // method ของ b-form-input
                        else if (r?.$el?.querySelector) r.$el.querySelector('input')?.focus() // fallback
                    })
                },
                onDepositShown() {
                    this.$nextTick(() => {
                        const r = this.$refs.user_name
                        if (r?.focus) r.focus()                          // method ของ b-form-input
                        else if (r?.$el?.querySelector) r.$el.querySelector('input')?.focus() // fallback
                    })
                },
                editModal(code) {
                    this.code = null;
                    this.formaddedit = {tranferer: '', bank: '', time: '', value: ''};
                    this.formmethod = 'edit';
                    this.userFound.addedit = false;

                    this.show = false;
                    this.$nextTick(async () => {
                        this.show = true;
                        this.code = code;
                        await this.loadData();
                        this.$refs.addedit.show();
                    });
                },

                depositModal() {     // เดิม refill()
                    this.code = null;
                    const now = new Date();
                    const yyyy = now.getFullYear();
                    const mm = String(now.getMonth() + 1).padStart(2, '0');
                    const dd = String(now.getDate()).padStart(2, '0');
                    const HH = String(now.getHours()).padStart(2, '0');
                    const II = String(now.getMinutes()).padStart(2, '0');

                    this.formdeposit = {
                        id: '',
                        user_name: '',
                        name: '',
                        amount: '',
                        account_code: '',
                        date_bank: `${yyyy}-${mm}-${dd}`,
                        time_bank: `${HH}:${II}`,
                        remark_admin: '',
                        one_time_password: ''
                    };
                    this.userFound.deposit = false;
                    this.method = 'deposit';
                    this.items = [];
                    this.isBusy = false;

                    this.searchingDeposit = false;
                    this.searchedDeposit = false;

                    this.show = false;
                    this.$nextTick(() => {
                        this.show = true;
                        this.$refs.deposit.show();
                    });
                },

                addModal() {
                    this.code = null;
                    this.formaddedit = {
                        acc_name: '',
                        acc_no: '',
                        banks: '',
                        user_name: '',
                        user_pass: '',
                        sort: 1,
                    };
                    this.formmethod = 'add';
                    this.userFound.addedit = false;

                    this.show = false;
                    this.$nextTick(() => {
                        this.show = true;
                        this.$refs.addedit.show();
                    });
                },

                // 🔒 ป้องกันกดรัว ๆ ที่นี่
                async approveModal(code) {
                    const now = Date.now();
                    if (this.approvePrompting || now < this.approveCooldownUntil) return;

                    this.approvePrompting = true;

                    try {
                        const response = await axios.post("{{ route('admin.'.$menu->currentRoute.'.loaddata') }}", {id: code});
                        const data = response?.data?.data || {};
                        const user = data?.tranferer ? `ไอดีที่จะเติมเงิน : ${data.tranferer}` : 'ไม่พบข้อมูล';
                        const info = data?.value ? `จำนวนเงิน : ${data.value}` : 'ไม่พบข้อมูล';

                        const h = this.$createElement;
                        const messageVNode = h('div', [
                            h('p', {class: 'text-left'}, [
                                'ถ้าไอดีลูกค้าที่ทีมงานใส่มาไม่ถูกต้อง ให้กด ',
                                h('strong', 'User ไม่ถูกต้อง'),
                                ' เพื่อย้อนกลับขั้นตอน Check.',
                            ]),
                            h('p', {class: 'text-left'}, [
                                'ถ้าข้อมูลไอดีถูกต้องแล้ว ให้กด ',
                                h('strong', 'เติมเงิน'),
                                ' เพื่อทำรายการเติมเงินเข้าไอดี.',
                            ]),
                            h('p', {class: 'text-info mt-2'}, user),
                            h('p', {class: 'text-info mt-2'}, info)
                        ]);

                        const confirmed = await this.$bvModal.msgBoxConfirm([messageVNode], {
                            title: 'จัดการรายการฝาก',
                            size: 'sm',
                            buttonSize: 'sm',
                            okTitle: '✅ เติมเงิน',
                            cancelTitle: '❌ User ไม่ถูกต้อง',
                            okVariant: 'success',
                            cancelVariant: 'danger',
                            centered: true,
                            noCloseOnBackdrop: true,
                            noCloseOnEsc: true,
                            hideHeaderClose: false,
                            returnFocus: true,
                        });

                        if (confirmed === true) {
                            await this.approveDeposit(code);
                        } else if (confirmed === false) {
                            await this.cancelDeposit(code);
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
                        this.approvePrompting = false;
                        this.approveCooldownUntil = Date.now() + 1200; // 1.2s
                    }
                },

                async approveDeposit(code) {
                    const really = await this.$bvModal.msgBoxConfirm('โปรดยืนยันอีกครั้งเพื่อทำรายการ "เติมเงิน"', {
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
                        this.$blockUI();
                        const resp = await this.$http.post("{{ route('admin.'.$menu->currentRoute.'.approve') }}", {id: code});
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
                        if (window.LaravelDataTables?.["deposittable"]) {
                            window.LaravelDataTables["deposittable"].draw(false);
                        }
                    }
                },

                async cancelDeposit(code) {
                    const really = await this.$bvModal.msgBoxConfirm('โปรดกด "ยืนยัน" อีกครั้ง ถ้าแต่ใจว่า User ไม่ถูกต้อง', {
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
                        this.$blockUI();
                        const resp = await this.$http.post("{{ route('admin.'.$menu->currentRoute.'.cancel') }}", {id: code});
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
                        if (window.LaravelDataTables?.["deposittable"]) {
                            window.LaravelDataTables["deposittable"].draw(false);
                        }
                    }
                },
                // ---------- Data Loaders ----------
                async loadData() {
                    const response = await axios.post("{{ route('admin.'.$menu->currentRoute.'.loaddata') }}", {id: this.code});
                    const d = response?.data?.data || {};
                    this.formaddedit = {
                        value: d.value || '',
                        bank: d.bank || '',
                        time: d.time || '',
                        tranferer: this.formaddedit.tranferer || '',
                    };
                },

                async loadUser(context = 'addedit') {
                    let id;
                    if (context === 'addedit') {
                        id = this.formaddedit.tranferer?.trim();
                    } else {
                        id = this.formdeposit.user_name?.trim();
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

                        if (context === 'deposit' && ok) {
                            this.formdeposit.name = resp?.data?.data?.me?.name ?? '';
                            this.formdeposit.id = resp?.data?.data?.user ?? '';
                            await this.myLog();
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

                async loadUserDeposit() {   // เดิม loadUserRefill
                    if (!this.formdeposit.user_name) return;
                    this.searchingDeposit = true;
                    try {
                        await this.loadUser('deposit');                 // เซ็ต userFound.deposit
                        this.searchedDeposit = !!this.userFound.deposit; // ตอนนี้ช่องจะ enable แล้ว

                        // โฟกัสหลังช่องถูก enable
                        await this.$nextTick();
                        const r = this.$refs.amount;
                        if (r?.focus) r.focus();                        // BootstrapVue component method
                        else if (r?.$el?.querySelector) r.$el.querySelector('input')?.focus(); // fallback DOM
                    } finally {
                        this.searchingDeposit = false;
                    }
                },

                async myLog() {
                    this.items = [];
                    this.isBusy = true;
                    try {
                        const response = await axios.get("{{ route('admin.member.gamelog') }}", {
                            params: {id: this.formdeposit.id, method: this.method}
                        });
                        this.caption = response?.data?.name || '';
                        this.items = response?.data?.list || [];
                        this.submittingSearch = false;
                    } finally {
                        setTimeout(() => {
                            this.isBusy = false;
                            this.submittingSearch = false;
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

                // ---------- Submits ----------
                async depositSubmit(event) {  // เดิม refillSubmit
                    event.preventDefault();
                    this.submittingDeposit = true;
                    this.toggleButtonDisable(true);
                    this.$blockUI();
                    try {
                        // NOTE: ยังยิงไปที่ route เดิม เพื่อไม่ไปกระทบ backend
                        const resp = await this.$http.post("{{ route('admin.'.$menu->currentRoute.'.refill') }}", this.formdeposit, { meta: { block: 'global' }});
                        const data = resp?.data || {};
                        this.showAlert(data);
                    } catch (e) {
                        console.log('deposit error', e);
                        this.$bvModal.msgBoxOk('เกิดข้อผิดพลาดระหว่างเชื่อมต่อเซิร์ฟเวอร์', {
                            title: 'เชื่อมต่อไม่สำเร็จ',
                            okVariant: 'danger',
                            size: 'sm',
                            buttonSize: 'sm',
                            centered: true
                        });
                    } finally {
                        this.$unblockUI();
                        this.submittingDeposit = false;
                        this.toggleButtonDisable(false);
                        if (window.LaravelDataTables?.["deposittable"]) {
                            window.LaravelDataTables["deposittable"].draw(false);
                        }
                    }
                },

                addEditSubmitNew(event) {
                    event.preventDefault();
                    this.submittingAddEdit = true;
                    this.toggleButtonDisable(true);
                    this.$blockUI();
                    let url;
                    if (this.formmethod === 'add') {
                        url = "{{ url($menu->currentRoute.'/create') }}";
                    } else if (this.formmethod === 'edit') {
                        url = "{{ url($menu->currentRoute.'/update') }}/" + this.code;
                    }

                    const formData = new FormData();
                    const json = JSON.stringify({tranferer: this.formaddedit.tranferer});
                    formData.append('data', json);
                    const config = {headers: {'Content-Type': `multipart/form-data; boundary=${formData._boundary}`}};

                    axios.post(url, formData, config)
                        .then(response => {
                            const res = response?.data || {};
                            if (res.success === true) {
                                this.$bvModal.msgBoxOk(res.message || 'ทำรายการสำเร็จ', {
                                    title: 'ผลการดำเนินการ',
                                    size: 'sm',
                                    buttonSize: 'sm',
                                    okVariant: 'success',
                                    headerClass: 'p-2 border-bottom-0',
                                    footerClass: 'p-2 border-top-0',
                                    centered: true
                                });
                                if (window.LaravelDataTables?.["deposittable"]) {
                                    window.LaravelDataTables["deposittable"].draw(false);
                                }
                            } else {
                                const msg = res?.message;
                                if (msg && typeof msg === 'object') {
                                    Object.keys(msg).forEach((id) => {
                                        const el = document.getElementById(id);
                                        if (el) el.classList.add("is-invalid");
                                    });
                                    $('input').on('focus', (ev) => {
                                        ev.preventDefault();
                                        ev.stopPropagation();
                                        const id = $(ev.currentTarget).attr('id');
                                        const el = document.getElementById(id);
                                        if (el) el.classList.remove("is-invalid");
                                    });
                                } else {
                                    this.$bvModal.msgBoxOk(res.message || 'ทำรายการไม่สำเร็จ', {
                                        title: 'ผลการดำเนินการ',
                                        size: 'sm',
                                        buttonSize: 'sm',
                                        okVariant: 'danger',
                                        centered: true
                                    });
                                }
                            }
                        })
                        .catch(errors => console.log(errors))
                        .finally(() => {
                            this.$unblockUI();
                            this.submittingAddEdit = false;
                            this.toggleButtonDisable(false);
                        });
                },

                clearSubmit(event) {
                    event.preventDefault();
                    this.submittingClear = true;
                    this.toggleButtonDisable(true);
                    this.$blockUI();

                    this.$http.post("{{ url($menu->currentRoute.'/clear') }}", {
                        id: this.code,
                        remark: this.formclear.remark
                    })
                        .then(response => {
                            const res = response?.data || {};
                            this.$bvModal.msgBoxOk(res.message || 'ทำรายการสำเร็จ', {
                                title: 'ผลการดำเนินการ',
                                size: 'sm',
                                buttonSize: 'sm',
                                okVariant: 'success',
                                headerClass: 'p-2 border-bottom-0',
                                footerClass: 'p-2 border-top-0',
                                centered: true
                            });
                            if (window.LaravelDataTables?.["deposittable"]) {
                                window.LaravelDataTables["deposittable"].draw(true);
                            }
                        })
                        .catch(exception => {
                            console.log('error', exception);
                            this.$bvModal.msgBoxOk('เกิดข้อผิดพลาดระหว่างเชื่อมต่อเซิร์ฟเวอร์', {
                                title: 'เชื่อมต่อไม่สำเร็จ',
                                okVariant: 'danger',
                                size: 'sm',
                                buttonSize: 'sm',
                                centered: true
                            });
                        })
                        .finally(() => {
                            this.$unblockUI();
                            this.submittingClear = false;
                            this.toggleButtonDisable(false);
                        });
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
                        await this.delDeposit(code);
                    }

                },

                async delDeposit(code) {
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

                    if (really !== true) return;

                    try {
                        this.$blockUI();
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
                        this.$unblockUI();
                        if (window.LaravelDataTables?.["deposittable"]) {
                            window.LaravelDataTables["deposittable"].draw(false);
                        }
                    }
                },
                // ---------- Helpers ----------
                toggleButtonDisable(disabled) {
                    try {
                        const btn = document.getElementById('btnchecking');
                        if (btn) btn.disabled = !!disabled;
                    } catch (_) {}
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
            },
        });
    </script>
@endpush
