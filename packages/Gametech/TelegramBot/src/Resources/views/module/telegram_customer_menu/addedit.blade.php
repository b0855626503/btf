<b-modal ref="addedit" id="addedit" centered size="sm" title="{{ $menu->currentName }}" :no-stacking="true"
         :no-close-on-backdrop="true"
         :hide-footer="true">
    <b-form @submit.prevent="addEditSubmit" v-if="show">

        <b-form-group
                id="input-group-title"
                label="หัวข้อ :"
                label-for="title"
                description="ระบุหัวข้อ">
            <b-form-input
                    id="title"
                    v-model="formaddedit.title"
                    type="text"
                    size="sm"
                    placeholder="หัวข้อ"
                    autocomplete="off"
                    required
            ></b-form-input>
        </b-form-group>

        <b-form-group
                id="input-group-type"
                label="ประเภท :"
                label-for="type"
                description="url หรือ callback">
            <b-form-select
                    id="type"
                    v-model="formaddedit.type"
                    :options="option.type"
                    size="sm"
                    required
            ></b-form-select>
        </b-form-group>

        <b-form-group
                id="input-group-value"
                label="ข้อมูล :"
                label-for="value"
                description="ระบุ ลิงค์ ถ้า type เป็น url">
            <b-form-input
                    id="value"
                    v-model="formaddedit.value"
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

    <script type="module">

        window.app = new Vue({
            el: '#app',
            data() {
                return {
                    show: false,
                    formmethod: 'add',
                    formaddedit: {
                        title: '',
                        type: '',
                        value: '',
                    },
                    option: {
                        type: [
                            {value: 'url', text: 'url'},
                            {value: 'callback', text: 'callback'},
                        ]
                    },

                };
            },
            created() {
                this.audio = document.getElementById('alertsound');
                this.autoCnt(false);
            },
            methods: {

                editModal(code) {
                    this.code = null;
                    this.formaddedit = {
                        title: '',
                        type: '',
                        value: '',
                    }

                    this.formmethod = 'edit';

                    this.show = false;
                    this.$nextTick(() => {

                        this.code = code;
                        this.loadData();
                        this.$refs.addedit.show();
                        this.show = true;

                    })
                },
                addModal() {
                    this.code = null;
                    this.formaddedit = {
                        title: '',
                        type: '',
                        value: '',
                    }
                    this.formmethod = 'add';

                    this.show = false;
                    this.$nextTick(() => {
                        this.$refs.addedit.show();
                        this.show = true;

                    })
                },
                async loadData() {
                    const response = await axios.post("{{ route('telegrambot.'.$menu->currentRoute.'.loaddata') }}", {id: this.code});

                    this.formaddedit = {
                        title: response.data.data.title,
                        type: response.data.data.type,
                        value: response.data.data.value,
                    }

                },
                addEditSubmit(event) {
                    event.preventDefault();
                    this.toggleButtonDisable(true);


                    if (this.formmethod === 'add') {
                        var url = "{{ route('telegrambot.'.$menu->currentRoute.'.create') }}";
                    } else if (this.formmethod === 'edit') {
                        var url = "{{ route('telegrambot.'.$menu->currentRoute.'.update') }}";
                    }
                    this.$http.post(url, {id: this.code, data: this.formaddedit})
                        .then(response => {
                            this.$refs.addedit.hide();

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


            },
        });

    </script>
@endpush

