<script type="text/x-template" id="withdraw-modal-template">
	<div class="modal modal-custom fade" id="withdrawModal" data-bs-backdrop="static" data-bs-keyboard="false"
	     tabindex="-1" aria-labelledby="withdrawLabel" data-bs-focus="false">
		<div class="modal-dialog modal-dialog-scrollable modal-dialog-centered">
			<div class="modal-content bg-dark-2" style="min-height: 60vh;">
				<div class="modal-header">
					<h5 class="modal-title text-center mb-0 text-dark lh-1"
					    id="withdrawLabel">{{ __('app.home.withdraw') }} </h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body p-1">
					<div class="fs-6 text-content pt-2 w-100 text-center pt-4">{{ __('app.home.withdraw_credit') }} :
						<span
								class="fw-bolder text-custom-primary" v-text="member.balance">0.00</span>
					</div>
					<hr class="w-75 mx-auto my-1">
					
					
					<div class="fs-6 text-content w-100 text-center">{{ __('app.home.withdraw_max_day') }} : <span
								class="fw-bolder text-danger" v-text="member.maxwithdraw_day">0</span>
						- {{ __('app.home.withdraw_sum_day') }} : <span
								class="fw-bolder text-danger" v-text="member.withdraw_sum_today">0</span>
					</div>
					<hr class="w-75 mx-auto my-1">
					
					
					<div class="fs-6 text-content w-100 text-center">{{ __('app.home.withdraw_remain_day') }} :
						<span
								class="fw-bolder text-danger" v-text="member.withdraw_remain_today">0</span>
					</div>
					<div v-if="member.getpro">
						<hr class="w-75 mx-auto my-1">
						<div class="fs-6 text-content w-100 text-center">{{ __('app.promotion.me') }} :
							<span class="fw-bolder text-danger" v-text="member.pro_name"></span><br>
							{{ __('app.home.withdraw_turn') }} : <span class="fw-bolder text-danger"
							                                           v-text="member.amount_balance"></span><br>
							{{ __('app.home.limit_withdraw') }} : <span class="fw-bolder text-danger"
							                                            v-text="member.withdraw_limit_amount"></span>
						</div>
					</div>
					<div v-else></div>
					
					<form @submit.prevent="submitWithdraw">
						<div class="theme-form mt-4">
							<div class="input-group input-group-lg mx-auto custom-style-input"
							     style="max-width: 20em;">
                                <span class="input-group-text"><img src="/assets/kimberbet/images/icon/coin.svg"
                                                                    width="40" height="40"></span>
								<input
										v-model="withdrawAmount"
										step="1"
										id="withdraw"
										type="number"
										autocomplete="off"
										placeholder="{{ __('app.home.withdraw_amount') }}"
										:min="member.withdraw_min"
										:max="member.withdraw_max"
										required
										class="form-control"
										:readonly="member.pro"
										@keydown="preventDot"
								>
							</div>
						</div>
						
						<div class="text-center mt-3 pb-3">
							<button type="submit" class="btn btn-primary btn-custom-primary w-100 rounded-pill"
							        :disabled="isSubmitting"
							        style="max-width: 20em;">
								{{ __('app.home.withdraw') }}
							</button>
						</div>
					</form>
				
				</div>
				<div class="modal-footer p-1 w-100 text-center">
					<div class="small text-danger fw-light w-100">{{ __('app.home.withdraw_min') }} <span
								v-text="member.withdraw_min"></span>
					</div>
				</div>
			</div>
		</div>
	</div>
</script>



@push('components')
	
	<script type="module">

        Vue.component('withdraw-modal', {
            template: '#withdraw-modal-template',
            data() {
                return {
                    member: {
                        balance: 0,
                        maxwithdraw_day: 0,
                        withdraw_sum_today: 0,
                        withdraw_remain_today: 0,
                        withdraw_min: 0,
                        withdraw_max: 10000,
                        pro: false,
                        pro_name: '',
                        amount_balance: 0,
                        withdraw_limit_amount: 0
                    },
                    isLoading: false,
                    withdrawAmount: 0,
                    isSubmitting: false
                };
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
                async loadMemberData() {
                    this.isLoading = true;
                    try {
                        const res = await axios.get("{{ route('customer.home.credit') }}");
                        if (res.data.success) {
                            this.member = res.data.profile;

                            if (this.member.pro) {
                                this.withdrawAmount = this.member.balance;

                            }
                            // console.log('pro '+this.member.pro);
                            // console.log('balance '+this.member.balance);
                            // console.log('withdrawAmount '+this.withdrawAmount);
                            // this.withdrawAmount = this.member.balance;
                        }
                    } catch (err) {
                        console.error("โหลดข้อมูลิดพลาด", err);
                    } finally {
                        this.isLoading = false;
                    }
                },
                showModal() {
                    console.log('เปิด modal ถอน');
                    this.withdrawAmount = 0; // reset ค่าเดิม
                    const modal = new bootstrap.Modal(document.getElementById('withdrawModal'));
                    this.$nextTick(() => {
                        this.loadMemberData();
                        modal.show();
                    })
                },

                async submitWithdraw() {
                    try {
                        this.isSubmitting = true;
                        // console.log(this.withdrawAmount);
                        // console.log(this.member.withdraw_min);
                        // console.log(this.member.withdraw_max);
                        if (parseFloat(this.withdrawAmount) < parseFloat(this.member.withdraw_min) || parseFloat(this.withdrawAmount) > parseFloat(this.member.withdraw_max)) {
                            window.Toast.fire({
                                icon: 'info',
                                title: this.trans('app.withdraw.wrong_amount')
                            });
                            return;
                        }

                        const res = await axios.post("{{ route('customer.withdraw.storeapi') }}", {
                            amount: this.withdrawAmount
                        });

                        if (res.data.success) {
                            window.Toast.fire({
                                icon: 'success',
                                title: res.data.message
                            });

                            this.$root.$refs.memberComponent.loadCredit();
                        } else {
                            window.Toast.fire({
                                icon: 'info',
                                title: res.data.message
                            });
                        }


                        // ปิด modal
                        bootstrap.Modal.getInstance(document.getElementById('withdrawModal')).hide();

                    } catch (err) {
                        window.Toast.fire({
                            icon: 'info',
                            title: err?.data?.message || this.trans('app.status.error')
                        });
                        console.error(err);
                    } finally {
                        this.isSubmitting = false;

                    }
                },
            }
        });
	
	</script>
@endpush

