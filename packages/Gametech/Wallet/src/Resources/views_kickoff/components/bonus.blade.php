<script type="text/x-template" id="bonus-modal-template">
	<div class="modal modal-custom fade" id="bonusModal" data-bs-backdrop="static" data-bs-keyboard="false"
	     tabindex="-1" aria-labelledby="bonusLabel" aria-hidden="true" data-bs-focus="false">
		<div class="modal-dialog modal-dialog-scrollable modal-dialog-centered">
			<div class="modal-content bg-dark-2" style="min-height: 60vh;">
				<div class="modal-header">
					<h5 class="modal-title text-center mb-0 text-dark lh-1"
					    id="bonusLabel">{{ __('app.home.bonus') }} </h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				
				<div class="modal-body p-1">
					<div class="container-fluid">
						<div class="row g-2 mt-1">
							<div class="col-6" v-for="tab in tabs" :key="tab.id">
								<div
										class="card h-100 text-center shadow-sm p-2"
										:class="[
                            'bg-dark',
                            'text-white',
                            tab.id !== selected ? 'opacity-75' : 'opacity-100'
                        ]">
									
									<button class="btn btn-for-bonus" @click="getBonus(tab)">
										<img :src="tab.icon" class="card-img-top mx-auto"
										     style="width: 50px; object-fit: contain;">
										
										<small class="-title text-white" v-text="item[tab.id]"></small>
										<p class="-title text-white" v-text="tab.title"></p>
									</button>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</script>

@push('components')
	
	<script type="module">

        Vue.component('bonus-modal', {
            template: '#bonus-modal-template',
            data() {
                return {
                    selectedPro: false,
                    promotion: {
                        name: '',
                        min: 0
                    },
                    item: {
                        cashback: 0,
                        ic: 0,
                        bonus: 0,
                        faststart: 0
                    },
                    resetCounter: 0,
                    selected: '',
                    tabs: [
                        {
                            id: 'bonus',
                            method: 'BONUS',
                            title: this.trans('app.bonus.wheel'),
                            icon: '/assets/kimberbet/images/icon/icon-bonus.webp',
                        },
                        {
                            id: 'cashback',
                            method: 'CASHBACK',
                            title: this.trans('app.bonus.cashback'),
                            icon: '/assets/kimberbet/images/icon/icon-cashback.webp',
                        },
                        {
                            id: 'ic',
                            method: 'IC',
                            title: this.trans('app.bonus.ic'),
                            icon: '/assets/kimberbet/images/icon/icon-ic.webp',
                        },
                        {
                            id: 'faststart',
                            method: 'FASTSTART',
                            title: this.trans('app.bonus.faststart'),
                            icon: '/assets/kimberbet/images/icon/icon-faststart.webp',
                        }
                    ]
                };
            },
            computed: {
                configs() {
                    return (this.$root && this.$root.$data && this.$root.$data.webconfig) || [];
                },
            },
            mounted() {
				this.loadData();
			},
            methods: {
                async loadData() {

                    const res = await axios.get("{{ route('customer.home.bonus') }}")
                    if (res.data.success) {
                        this.item = {
                            cashback: res.data.profile.cashback || 0,
                            ic: res.data.profile.ic || 0,
                            bonus: res.data.profile.bonus || 0,
                            faststart: res.data.profile.faststart || 0
                         
                        };
                    }

                },
                trans(key, replace = {}) {
                    var translation = key.split('.').reduce((t, i) => t[i] || null, window.i18n);

                    for (var placeholder in replace) {
                        translation = translation.replace(`:${placeholder}`, replace[placeholder]);
                    }
                    return translation;
                },
                getBonusByKey(key) {
                    return this.tabs.find(t => t.method === key || t.key === key);
                },
                getBonus(tabOrKey) {
                    let tab = typeof tabOrKey === 'string' ? this.getBonusByKey(tabOrKey) : tabOrKey;
                    if (!tab) {
                        console.warn('Tab not found for:', tabOrKey);
                        return;
                    }
                    const modalEl = document.getElementById('bonusModal');
                    const modalInstance = bootstrap.Modal.getInstance(modalEl);

// เช็กว่า modal มี instance และกำลังแสดงผลอยู่ (มี class show)
                    if (modalInstance && modalEl.classList.contains('show')) {
                        modalInstance.hide();
                    }

                    Swal.fire({
                        title: this.trans('app.bonus.word') + tab.title + this.trans('app.bonus.word2'),
                        html: this.trans('app.bonus.detail') + this.configs.pro_reset,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: this.trans('app.bonus.yes'),
                        cancelButtonText: this.trans('app.bonus.no'),
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        allowEnterKey: false
                    }).then((result) => {
                        if (result.isConfirmed) {
                            axios.post("{{ route('customer.transfer.bonus.confirm') }}", {
                                id: tab.method
                            }).then(response => {
                                if (response.data.success) {
                                    Swal.fire(
                                        this.trans('app.bonus.success'),
                                        response.data.message,
                                        'success'
                                    );

                                } else {
                                    Swal.fire(
                                        this.trans('app.bonus.fail'),
                                        response.data.message,
                                        'error'
                                    );
                                }

                            }).catch(err => [err]);
                        } else {
                            bootstrap.Modal.getInstance(document.getElementById('bonusModal')).show();
                        }

                    })

                },
                async showModal() {
                    const modal = new bootstrap.Modal(document.getElementById('bonusModal'));
                    modal.show();
                },
            },
        });
	
	</script>
@endpush

