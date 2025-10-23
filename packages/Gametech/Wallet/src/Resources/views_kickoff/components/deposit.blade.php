<script type="text/x-template" id="deposit-modal-template">
	<div class="modal modal-custom fade" id="depositModal" data-bs-backdrop="static" data-bs-keyboard="false"
	     tabindex="-1" aria-labelledby="depositLabel" aria-hidden="true" data-bs-focus="false">
		<div class="modal-dialog modal-dialog-scrollable modal-dialog-centered">
			<div class="modal-content bg-dark-2" style="min-height: 60vh;">
				<div class="modal-header">
					<h5 class="modal-title text-center mb-0 text-dark lh-1"
					    id="depositLabel">{{ __('app.topup.refill') }} </h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				
				<div class="modal-body p-1">
					<topup-tabs :tabs="tabs" :selected="selected" @select="selected = $event"></topup-tabs>
					<component :is="selectedComponent" :key="selectedKey"/>
				</div>
				<div class="modal-footer p-1 w-100 text-center " v-if="selectedPro">
					<div class="text-warning fw-light w-100">
						{{ __('app.promotion.select') }} <span v-text="promotion.name"></span></br>
						{{ __('app.promotion.min') }} <span v-text="promotion.min"></span></br>
						<div class="d-grid gap-2 mt-1">
							<button class="btn btn-danger"
							        @click="deSelectPro">{{ __('app.promotion.delete') }}</button>
						</div>
					</div>
				</div>
				<div v-else class="modal-footer p-1 w-100 text-center">
					<div class="text-warning fw-light w-100">
						{{ __('app.promotion.suggest') }}
					</div>
				</div>
			
			</div>
		</div>
	</div>
</script>

<script type="text/x-template" id="topup-tabs-template">
	
	<div class="container-fluid">
		<div class="row g-2 mt-1">
			<div class="col-4" v-for="tab in tabs" :key="tab.id">
				<div
						class="card h-100 text-center shadow-sm p-2"
						:class="[
                            'bg-dark',
                            'text-white',
                            tab.id !== selected ? 'opacity-50' : 'opacity-100'
                        ]">
					
					<button class="btn btn-for-deposit" @click="$emit('select', tab.id)">
						<img :src="tab.icon" class="card-img-top mx-auto" style="width: 50px; object-fit: contain;">
						
						<p class="-title text-white" v-text="tab.title"></p>
					</button>
				</div>
			</div>
		</div>
	</div>

</script>

<script type="text/x-template" id="topup-bank-template">
	
	<div v-if="loading" class="text-center text-muted py-4">{{ __('app.status.loading') }}</div>
	<div v-else>
		
		<div v-if="items.length > 0">
			<div class="container-fluid">
				<div class="row g-2 mt-1">
					<div class="card bg-dark bank-deposit-item mb-1" v-for="item in items" v-if="item">
						<div class="card-body bank-item-container container p-3">
							<div class="bank-info d-flex">
								<div class="bank-icon d-flex align-items-center">
									<img :src="item.bank_pic" style="width: 50px; object-fit: contain;">
								</div>
								<div class="bank-detail ps-4 col text-white">
									<div class="text-start fw-light" v-text="item.bank_name"></div>
									<div class="text-start mt-auto pt-1" v-text="item.acc_name"></div>
									<div class="text-warning fs-6 text-start lh-1" v-text="item.acc_no"></div>
								</div>
								<div class="btn-copy-bank d-flex align-items-center">
									<!-- COPY TEXT IN DIV -->
									<button class="btn_copy_bankcode py-1 shadow rounded-pill btn btn-outline-secondary btn-custom-secondary text-white fw-light d-flex"
									        style="min-width: unset;" @click="copylink(item.acc_no)">
										<!---->
										<span class="w-100 flex-row-center-xy">
                                <i class="bi bi-clipboard-check text-light fw-light"></i> {{ __('app.con.copy') }}
											<!-- COPY THIS -->
                                <b v-text="item.acc_no"></b>
											<!-- COPY THIS -->
                                <input tabindex="-1" aria-hidden="true" class="ip-copyfrom modal-deposit">
                              </span>
									</button>
									<!-- COPY TEXT IN DIV -->
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		
		<div v-else class="text-center text-muted py-4">
			{{ __('app.home.no_list') }}
		</div>
	</div>
</script>

<script type="text/x-template" id="topup-tw-template">
	<div v-if="loading" class="text-center text-muted py-4">{{ __('app.status.loading') }}</div>
	<div v-else>
		
		<div v-if="items.length > 0">
			<div class="container-fluid">
				<div class="row g-2 mt-1">
					<div class="card bg-dark bank-deposit-item mb-1" v-for="item in items" v-if="item">
						<div class="card-body bank-item-container container p-3">
							<div class="bank-info d-flex">
								<div class="bank-icon d-flex align-items-center">
									<img :src="item.bank_pic" style="width: 50px; object-fit: contain;">
								</div>
								<div class="bank-detail ps-4 col text-white">
									<div class="text-start fw-light" v-text="item.bank_name"></div>
									<div class="text-start mt-auto pt-1" v-text="item.acc_name"></div>
									<div class="text-warning fs-6 text-start lh-1" v-text="item.acc_no"></div>
								</div>
								<div class="btn-copy-bank d-flex align-items-center">
									<!-- COPY TEXT IN DIV -->
									<button class="btn_copy_bankcode py-1 shadow rounded-pill btn btn-outline-secondary btn-custom-secondary text-white fw-light d-flex"
									        style="min-width: unset;" @click="copylink(item.acc_no)">
										<!---->
										<span class="w-100 flex-row-center-xy">
                                <i class="bi bi-clipboard-check text-light fw-light"></i> {{ __('app.con.copy') }}
											<!-- COPY THIS -->
                                <b v-text="item.acc_no"></b>
											<!-- COPY THIS -->
                                <input tabindex="-1" aria-hidden="true" class="ip-copyfrom modal-deposit">
                              </span>
									</button>
									<!-- COPY TEXT IN DIV -->
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		
		<div v-else class="text-center text-muted py-4">
			{{ __('app.home.no_list') }}
		</div>
	</div>
</script>

<script type="text/x-template" id="topup-payment-template">
	<div v-if="loading" class="text-center text-muted py-4">{{ __('app.status.loading') }}</div>
	<div v-else>
		
		<div v-if="item">
			<div class="container-fluid">
				<div class="row g-2 mt-1">
					<div class="card bg-dark bank-deposit-item mb-1">
						<div class="card-body bank-item-container container p-3">
							<div class="bank-info">
								<form @submit.prevent="submitDeposit">
									<div class="theme-form mt-4 text-center">
										<div class="input-group input-group-lg mx-auto custom-style-input"
										     style="max-width: 20em;">
                                <span class="input-group-text"><img src="/assets/kimberbet/images/icon/coin.svg"
                                                                    width="40" height="40"></span>
											<input
													v-model="depositAmount"
													step="1"
													id="deposit"
													type="number"
													autocomplete="off"
													placeholder=""
													required
													min="200"
													class="form-control"
													@keydown="preventDot"
											>
											<!-- หลัง input -->
											<div class="quick-amounts text-center mt-3 mb-4">
												<div class="d-flex flex-wrap justify-content-center gap-2">
													<button
															v-for="amt in [200, 300, 400, 500, 600, 1000]"
															:key="amt"
															type="button"
															class="btn amount-btn"
															:class="{ 'active': parseInt(depositAmount) === amt }"
															@click="depositAmount = amt"
													>
														@{{ amt.toLocaleString() }}
													</button>
												</div>
											</div>
											
											<p style="margin:0 auto;text-align:center;font-size:smaller;"> {{ __('app.topup.maintenance') }}</p>
										</div>
									</div>
									
									<div class="text-center mt-3 pb-3">
										<button type="submit"
										        class="btn btn-primary btn-custom-primary w-100 rounded-pill"
										        :disabled="isSubmitting"
										        style="max-width: 20em;">
											{{ __('app.home.deposit') }}
										</button>
									</div>
								</form>
							</div>
						</div>
					</div>
				
				</div>
			
			</div>
		</div>
		
		<div v-else class="text-center text-muted py-4">
			{{ __('app.home.no_list') }}
		</div>
	
	</div>
	</div>
</script>

<script type="text/x-template" id="topup-slip-template">
	<div v-if="loading" class="text-center text-muted py-4">{{ __('app.status.loading') }}</div>
	<div v-else>
		
		<div v-if="item">
			<div class="container-fluid">
				<div class="row g-2 mt-1">
					<div class="card bg-dark bank-deposit-item mb-1">
						<div class="card-body bank-item-container container p-3">
							<div class="bank-info d-flex">
								<div class="bank-icon d-flex align-items-center">
									<img :src="item.bank_pic" style="width: 50px; object-fit: contain;">
								</div>
								<div class="bank-detail ps-4 col text-white">
									<div class="text-start fw-light" v-text="item.bank_name"></div>
									<div class="text-start mt-auto pt-1" v-text="item.acc_name"></div>
									<div class="text-warning fs-6 text-start lh-1" v-text="item.acc_no"></div>
								</div>
								<div class="btn-copy-bank d-flex align-items-center">
									<!-- COPY TEXT IN DIV -->
									<button class="btn_copy_bankcode py-1 shadow rounded-pill btn btn-outline-secondary btn-custom-secondary text-white fw-light d-flex"
									        style="min-width: unset;" @click="copylink(item.acc_no)">
										<!---->
										<span class="w-100 flex-row-center-xy">
                                    <i class="bi bi-clipboard-check text-light fw-light"></i> {{ __('app.con.copy') }}
											<!-- COPY THIS -->
                                    <b v-text="item.acc_no"></b>
											<!-- COPY THIS -->
                                    <input tabindex="-1" aria-hidden="true" class="ip-copyfrom modal-deposit">
                                  </span>
									</button>
									<!-- COPY TEXT IN DIV -->
								</div>
							</div>
						</div>
					</div>
					
					<upload-slip :account-info="item"></upload-slip>
				
				</div>
			
			</div>
		</div>
		
		<div v-else class="text-center text-muted py-4">
			{{ __('app.home.no_list') }}
		</div>
	
	</div>
	</div>
</script>

<script type="text/x-template" id="upload-slip-template">
	<form ref="dropzoneRef" class="dropzone">
		<div class="dz-message">
			<i class="fas fa-upload"></i>
			<span>{{ __('app.topup.dragslip') }}</span>
		</div>
	</form>
</script>


@push('components')
	
	<script type="module">

        Vue.component('upload-slip', {
            props: ['accountInfo'], // รับ object จาก parent
            template: '#upload-slip-template',
            data() {
                return {
                    dz: null,
                };
            },
            mounted() {
                this.initDropzone();
            },
            methods: {
                trans(key, replace = {}) {
                    var translation = key.split('.').reduce((t, i) => t[i] || null, window.i18n);

                    for (var placeholder in replace) {
                        translation = translation.replace(`:${placeholder}`, replace[placeholder]);
                    }
                    return translation;
                },
                initDropzone() {
                    const self = this;
                    this.dz = new Dropzone(this.$refs.dropzoneRef, {
                        url: "{{ route('customer.slip.upload') }}",
                        method: 'post',
                        maxFiles: 1,
                        acceptedFiles: 'image/*',
                        addRemoveLinks: true,
                        autoProcessQueue: true,
                        init: function () {
                            this.on('sending', function (file, xhr, formData) {

                                formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);


                                const info = {
                                    code: self.accountInfo?.code || ''
                                };

                                const payload = {
                                    checkDuplicate: true,
                                    checkReceiver: [{
                                        accountType: self.accountInfo?.slip_bank || '',
                                        accountNumber: self.accountInfo?.acc_no || ''
                                    }],
                                    checkDate: {
                                        type: 'gte',
                                        date: new Date().toISOString()
                                    }
                                };

                                formData.append('payload', JSON.stringify(payload));
                                formData.append('info', JSON.stringify(info));

                            });

                            this.on('success', function (file, response) {
                                this.removeFile(file);

                                if (response.code === '200200') {
                                    bootstrap.Modal.getInstance(document.getElementById('depositModal')).hide();
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'ผลการตรวจสอบ',
                                        text: 'ตรวจสอบสำเร็จ โปรดตรวจสอบยอดเครดิต',
                                        timer: 2500,
                                        showConfirmButton: false
                                    });
                                } else {
                                    bootstrap.Modal.getInstance(document.getElementById('depositModal')).hide();
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'ผลการตรวจสอบ',
                                        text: response.message,
                                        timer: 2500,
                                        showConfirmButton: false
                                    });
                                }

                            });

                            this.on('error', function (file, errorMessage) {
                                console.log('error');
                                this.removeFile(file);
                                bootstrap.Modal.getInstance(document.getElementById('depositModal')).hide();
                                Swal.fire({
                                    icon: 'info',
                                    title: 'ผลการตรวจสอบ',
                                    text: 'ผิดพลาด',
                                    timer: 2500,
                                    showConfirmButton: false
                                });
                            });
                        },
                    });
                },
                resetUpload() {
                    if (this.dz) {
                        this.dz.removeAllFiles(true);
                    }
                }
            }
        });

        Vue.component('deposit-modal', {
            template: '#deposit-modal-template',
            data() {
                return {
                    selectedPro: false,
                    promotion: {
                        name: '',
                        min: 0
                    },
                    resetCounter: 0,
                    selected: '',
                    tabs: []
                };
            },
            created() {
                this.tabs = [
                    {
                        id: 'topup_bank',
                        title: this.trans('app.topup.bank'),
                        icon: 'https://img2.pic.in.th/pic/bank19da438c9e295f0b.png',
                        component: 'topup-bank'
                    },
                    {
                        id: 'topup_tw',
                        title: this.trans('app.topup.tw'),
                        icon: 'https://img2.pic.in.th/pic/twa6cf4bb54c16ae4b.png',
                        component: 'topup-tw'
                    },
                    // {
                    //     id: 'topup_slip',
                    //     title: this.trans('app.topup.slip'),
                    //     icon: 'https://img5.pic.in.th/file/secure-sv1/slipupload.png',
                    //     component: 'topup-slip'
                    // },
                    // {
                    //     id: 'topup_payment',
                    //     title: this.trans('app.topup.payment'),
                    //     icon: 'https://img5.pic.in.th/file/secure-sv1/qr0068bdbf0cc6226d.png',
                    //     component: 'topup-payment'
                    // },
                ];
            },
            computed: {
                selectedComponent() {
                    const tab = this.tabs.find(t => t.id === this.selected);
                    return tab ? tab.component : null;
                },
                selectedKey() {
                    return `${this.selected}-${this.resetCounter}`;
                }
            },
            methods: {
                trans(key, replace = {}) {
                    var translation = key.split('.').reduce((t, i) => t[i] || null, window.i18n);

                    for (var placeholder in replace) {
                        translation = translation.replace(`:${placeholder}`, replace[placeholder]);
                    }
                    return translation;
                },
                async deSelectPro() {
                    try {
                        const res = await axios.post("{{ route('customer.promotion.deselect') }}");
                        if (res.data.success) {
                            window.Toast.fire({
                                icon: 'success',
                                title: res.data.message
                            });
                            this.selectedPro = false;
                        }

                    } catch (err) {
                        console.error("โหลดข้อมูลผิดพลาด", err);
                    } finally {

                    }

                },
                async loadMemberData() {

                    try {
                        const res = await axios.get("{{ route('customer.home.credit') }}");
                        if (res.data.success) {
                            this.selectedPro = res.data.promotion.select;
                            this.promotion = res.data.promotion;
                        }
                    } catch (err) {
                        console.error("โหลดข้อมูลผิดพลาด", err);
                    } finally {

                    }
                },
                resetModal() {
                    // เคลียร์ค่า + รีโหลด
                    this.resetCounter++;
                    this.selected = ''; // clear tab
                    this.promotion = ''; // clear tab
                    this.selectedPro = false;
                },
                async showModal() {
                    this.resetModal();

                    await this.loadMemberData(); // โหลดข้อมูลทุกครั้งที่เปิด
                    const modal = new bootstrap.Modal(document.getElementById('depositModal'));
                    modal.show();
                },
            },
        });

        Vue.component('topup-tabs', {
            props: ['tabs', 'selected'],
            template: '#topup-tabs-template'
        });

        Vue.component('topup-payment', {
            template: '#topup-payment-template',
            data() {
                return {
                    item: false,
                    content: '',
                    loading: true,
                    depositAmount: 200,
                    isSubmitting: false
                };
            },
            mounted() {
                this.loadBank();

            },
            methods: {
                trans(key, replace = {}) {
                    var translation = key.split('.').reduce((t, i) => t[i] || null, window.i18n);

                    for (var placeholder in replace) {
                        translation = translation.replace(`:${placeholder}`, replace[placeholder]);
                    }
                    return translation;
                },
                preventDot(event) {
                    if (event.key === '.' || event.key === ',' || event.key === 'e') {
                        event.preventDefault();
                    }
                },
                copylink(acc_no) {

                    navigator.clipboard.writeText(acc_no);

                    $(".myAlert-top").show();
                    setTimeout(function () {
                        $(".myAlert-top").hide();
                    }, 1000);
                },

                async loadBank() {
                    this.loading = true;
                    this.item = (this.$root.$data.webconfig.qrscan === 'Y' ? true : false);

                    this.loading = false;

                },
                async submitDeposit_() {
                    try {
                        this.isSubmitting = true;

                        if (parseFloat(this.depositAmount) < 200) {
                            window.Toast.fire({
                                icon: 'info',
                                title: this.trans('app.withdraw.wrong_amount')
                            });
                            return;
                        }

                        const res = await axios.post("{{ route('api.payment.deposit') }}", {
                            amount: this.depositAmount
                        });

                        if (res.data.success) {
                            window.Toast.fire({
                                icon: 'success',
                                title: res.data.msg || this.trans('app.topup.create')
                            });
                            setTimeout(function () {
                                window.open(res.data.url, '_blank');
                            }, 5000);

                        } else {
                            window.Toast.fire({
                                icon: 'error',
                                title: res.data.msg || this.trans('app.status.error')
                            });
                        }

                    } catch (err) {
                        window.Toast.fire({
                            icon: 'error',
                            title: err?.response?.data?.message || this.trans('app.status.error')
                        });
                        console.error(err);
                    } finally {
                        this.isSubmitting = false;
                    }
                },
                async submitDeposit_bk() {
                    try {
                        this.isSubmitting = true;

                        if (parseFloat(this.depositAmount) < 200) {
                            window.Toast.fire({
                                icon: 'info',
                                title: this.trans('app.withdraw.wrong_amount')
                            });
                            return;
                        }

                        const res = await axios.post("{{ route('api.payment.deposit') }}", {
                            amount: this.depositAmount
                        }, {
                            timeout: 10000  // <--- เพิ่ม timeout 10 วินาที
                        });

                        if (res.data.success) {
                            if (res.data.code === 0) {
                                window.Toast.fire({
                                    icon: 'success',
                                    title: res.data.msg || this.trans('app.topup.create')
                                });
                            } else {
                                window.Toast.fire({
                                    icon: 'info',
                                    title: res.data.msg || this.trans('app.topup.create')
                                });
                            }

                            setTimeout(function () {
                                window.open(res.data.url, '_blank');
                            }, 3000);
                        } else {
                            window.Toast.fire({
                                icon: 'error',
                                title: res.data.msg || this.trans('app.status.error')
                            });
                        }

                    } catch (err) {
                        let message = this.trans('app.status.error');

                        if (err.code === 'ECONNABORTED') {
                            message = 'การเชื่อมต่อช้า หรือไม่ตอบสนอง กรุณาลองใหม่อีกครั้ง';
                        } else if (err?.response?.data?.message) {
                            message = err.response.data.message;
                        }

                        window.Toast.fire({
                            icon: 'error',
                            title: message
                        });

                        console.error(err);

                    } finally {
                        this.isSubmitting = false;
                    }
                },
                async submitDeposit(force = false) {
                    try {
                        this.isSubmitting = true;

                        const amount = parseFloat(this.depositAmount);
                        if (!amount || isNaN(amount) || amount < 200) {
                            window.Toast.fire({
                                icon: 'info',
                                title: this.trans('app.withdraw.wrong_amount') || 'กรุณากรอกจำนวนเงินที่ถูกต้อง (ขั้นต่ำ 200)'
                            });
                            return;
                        }

                        const res = await axios.post("{{ route('api.payment.deposit') }}", {
                            amount: amount,
                            force: force
                        }, {
                            timeout: 10000
                        });

                        if (res.data.success) {
                            const msg = res.data.msg || this.trans('app.topup.create');
                            window.Toast.fire({
                                icon: 'success',
                                title: msg
                            });

                            setTimeout(() => {
                                window.open(res.data.url, '_blank');
                            }, 3000);

                        } else if (res.data.status === 'has_pending') {
                            const d = res.data.data;
                            const result = await Swal.fire({
                                title: this.trans('app.topup.dup_topic'),
                                html: `
                        <p>${this.trans('app.topup.amount')} <strong>${d.amount}</strong></p>
                        <p>${this.trans('app.topup.amount_pay')} <strong>${d.payamount}</strong></p>
                        <p>${this.trans('app.topup.txnid')} <strong>${d.txid}</strong></p>
                        <p>${this.trans('app.topup.dup_detail')}</p>
                        <p><small>${this.trans('app.topup.dup_detail_2')}</small></p>
                    `,
                                icon: 'warning',
                                showCloseButton: true,
                                showCancelButton: true,
                                confirmButtonText: this.trans('app.topup.confirm_new'),
                                cancelButtonText: this.trans('app.topup.view_old'),
                                reverseButtons: true
                            });

                            if (result.isConfirmed) {
                                // 🔁 กดยืนยัน → ส่งใหม่พร้อม force = true
                                await this.submitDeposit(true);
                            } else if (result.dismiss === Swal.DismissReason.cancel) {
                                // 👁‍🗨 ไปหน้ารายการเดิม
                                window.open(d.url, '_blank');
                            }

                        } else {
                            window.Toast.fire({
                                icon: 'error',
                                title: res.data.msg || this.trans('app.status.error')
                            });
                        }

                    } catch (err) {
                        let message = this.trans('app.status.error');
                        if (err.code === 'ECONNABORTED') {
                            message = 'การเชื่อมต่อช้า หรือไม่ตอบสนอง กรุณาลองใหม่อีกครั้ง';
                        } else if (err?.response?.data?.message) {
                            message = err.response.data.message;
                        }

                        window.Toast.fire({
                            icon: 'error',
                            title: message
                        });
                        console.error(err);
                    } finally {
                        this.isSubmitting = false;
                    }
                }

            }
        });

        Vue.component('topup-bank', {
            template: '#topup-bank-template',
            data() {
                return {
                    items: [],
                    content: '',
                    loading: true
                };
            },
            mounted() {
                this.loadBank();

            },
            methods: {

                copylink(acc_no) {

                    navigator.clipboard.writeText(acc_no);

                    $(".myAlert-top").show();
                    setTimeout(function () {
                        $(".myAlert-top").hide();
                    }, 1000);
                },

                async loadBank() {
                    this.loading = true
                    this.items = []

                    try {
                        const res = await axios.post("{{ route('customer.slip.loadbank') }}", {method: 'bank'})
                        if (res.data.success) {
                            this.items = Object.values(res.data.bank); // ต้องเก็บค่าที่แปลงกลับมาด้วย
                            // console.log(this.items.length);
                        } else {
                            this.items = [];
                        }
                    } catch (err) {
                        console.error('โหลดข้อมูลล้มเหลว', err)
                    }

                    this.loading = false;
                },

            }
        });

        Vue.component('topup-tw', {
            template: '#topup-tw-template',
            data() {
                return {
                    items: [],
                    content: '',
                    loading: true
                };
            },
            mounted() {
                this.loadBank();

            },
            methods: {
                copylink(acc_no) {

                    navigator.clipboard.writeText(acc_no);

                    $(".myAlert-top").show();
                    setTimeout(function () {
                        $(".myAlert-top").hide();
                    }, 1000);
                },

                async loadBank() {
                    this.loading = true
                    this.items = []

                    try {
                        const res = await axios.post("{{ route('customer.slip.loadbank') }}", {method: 'tw'})
                        if (res.data.success) {
                            this.items = Object.values(res.data.bank); // ต้องเก็บค่าที่แปลงกลับมาด้วย
                            // console.log(this.items.length);
                        } else {
                            this.items = [];
                        }
                    } catch (err) {
                        console.error('โหลดข้อมูลล้มเหลว', err)
                    }

                    this.loading = false;

                },

            }
        });

        Vue.component('topup-slip', {
            template: '#topup-slip-template',
            data() {
                return {
                    item: '',
                    content: '',
                    loading: true,
                };
            },
            mounted() {
                this.loadBank();
            },
            methods: {
                copylink(acc_no) {

                    navigator.clipboard.writeText(acc_no);

                    $(".myAlert-top").show();
                    setTimeout(function () {
                        $(".myAlert-top").hide();
                    }, 1000);
                },

                async loadBank() {
                    this.loading = true
                    this.item = '';

                    try {
                        const res = await axios.post("{{ route('customer.slip.loadbank') }}", {method: 'slip'});
                        if (res.data.success) {
                            this.item = res.data.bank; // ต้องเก็บค่าที่แปลงกลับมาด้วย
                            // console.log(this.items.length);
                        } else {
                            this.item = '';
                        }
                    } catch (err) {
                        console.error('โหลดข้อมูลล้มเหลว', err)
                    }

                    this.loading = false;
                }
            }
        });
	
	</script>
@endpush

