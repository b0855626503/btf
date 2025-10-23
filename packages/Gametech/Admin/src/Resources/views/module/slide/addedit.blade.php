<b-modal ref="addedit" id="addedit" centered size="sm" title="{{ $menu->currentName }}" :no-stacking="true"
         :no-close-on-backdrop="true"
         :hide-footer="true">
    <b-form @submit.prevent="addEditSubmitNew" v-if="show">

        <div class="form-group {!! $errors->has('filepic.*') ? 'has-error' : '' !!}">
            <label>รูปภาพ</label>
            <image-wrapper
                @clear="clearImage"
                @upload="handleUpload($event)"
                button-label="เพิ่มรูปภาพ"
                :removed="true"
                input-name="filepic"
                :multiple="false"
                :images="formaddedit.filepic"
                :imgpath="imgpath"
                v-bind:testProp.sync="trigger"></image-wrapper>
        </div>

        <b-form-group
            id="input-group-2"
            label="ลำดับ:"
            label-for="sort"
            description="">
            <b-form-input
                id="sort"
                v-model="formaddedit.sort"
                type="number"
                size="sm"
                placeholder=""
                autocomplete="off"
                required
            ></b-form-input>
        </b-form-group>

        <b-form-checkbox
            id="enable"
            v-model="formaddedit.enable"
            value="Y"
            unchecked-value="N">
            สถานะใช้งาน
        </b-form-checkbox>

        <b-button type="submit" variant="primary" class="-align-right">บันทึก</b-button>

    </b-form>
</b-modal>

@push('scripts')
    <script type="module">

        window.app = new Vue({
            el: '#app',
            data() {
                return {
                    show: false,
                    trigger: 0,
                    fileupload: '',
                    formmethod: 'edit',
                    formaddedit: {
                        name_th: '',
                        name_en: '',
                        enable: '',
                        sort: '',
                        filepic: ''
                    },
                    imgpath: '/storage/slide_img/'
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
                        enable: ''
                    }
                    this.formmethod = 'edit';
                    this.fileupload = '';
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
                    this.formaddedit = {
                        enable: '',
                        filepic:''
                    }
                    this.formmethod = 'add';
                    this.fileupload = '';
                    this.show = false;
                    this.$nextTick(() => {
                        this.show = true;
                        this.$refs.addedit.show();

                    })
                },
                async loadData() {
                    const response = await axios.post("{{ route('admin.'.$menu->currentRoute.'.loaddata') }}", {id: this.code});
                    this.formaddedit = {
                        enable: response.data.data.enable,
                        sort: response.data.data.sort
                    };
                    // if (response.data.data.filepic) {
                    //     this.trigger++;
                    //     this.formaddedit.filepic = response.data.data.filepic;
                    // }
                    if (response.data.data.filepic) {
                        // this.trigger++;
                        this.formaddedit.filepic = response.data.data.filepic;

                    } else {
                        this.formaddedit.filepic = '';
                    }
                },
                clearImage() {
                    this.trigger++;
                    this.formaddedit.filepic = '';
                    // console.log('Clear :' + this.formaddedit.filepic);
                },
                handleUpload(value) {
                    this.fileupload = value;
                },
                addEditSubmitNew(event) {
                    event.preventDefault();
                    this.toggleButtonDisable(true);

                    if (this.formmethod === 'add') {
                        var url = "{{ route('admin.'.$menu->currentRoute.'.create') }}";
                    } else if (this.formmethod === 'edit') {
                        var url = "{{ route('admin.'.$menu->currentRoute.'.update') }}/" + this.code;
                    }




                    let formData = new FormData();
                    const json = JSON.stringify({
                        enable: this.formaddedit.enable,
                        sort: this.formaddedit.sort
                    });

                    formData.append('data', json);
                    formData.append('fileupload', this.fileupload);


                    const config = {headers: {'Content-Type': `multipart/form-data; boundary=${formData._boundary}`}};

                    axios.post(url, formData, config)
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
                        .catch(errors => console.log(errors));

                }
            },
        });

    </script>
@endpush

