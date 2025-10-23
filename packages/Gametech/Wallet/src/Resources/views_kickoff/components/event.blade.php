<script type="text/x-template" id="event-modal-template">
	<div class="modal modal-custom fade" id="eventModal" data-bs-backdrop="static" data-bs-keyboard="false"
	     tabindex="-1" aria-labelledby="eventLabel" aria-hidden="true" data-bs-focus="false">
		<div class="modal-dialog modal-dialog-scrollable modal-dialog-centered">
			<div class="modal-content bg-dark-2" style="min-height: 60vh;">
				<div class="modal-header">
					<h5 class="modal-title text-center mb-0 text-dark lh-1"
					    id="eventLabel">{{ __('app.home.event') }} </h5>
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

        Vue.component('event-modal', {
            template: '#event-modal-template',
            data() {
                return {
                    selectedPro: false,
                    promotion: {
                        name: '',
                        min: 0
                    },
                    resetCounter: 0,
                    selected: '',
                    tabs: [
                        {
                            id: 'wheel',
                            method: 'WHEEL',
                            type: 'link',
                            href: '{{ route('customer.spin.index') }}',
                            title: this.trans('app.home.wheels'),
                            icon: '/assets/kimberbet/images/icon/icon-bonus.webp',
                        },
                        {
                            id: 'coupon',
                            method: 'COUPON',
                            type: 'button',
                            title: this.trans('app.home.coupon'),
                            icon: '/assets/kimberbet/images/icon/icon-coupon.webp',
                        },

                    ]
                };
            },
            computed: {
                configs() {
                    return (this.$root && this.$root.$data && this.$root.$data.webconfig) || [];
                },
            },
            methods: {
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
                    const modalEl = document.getElementById('eventModal');
                    const modalInstance = bootstrap.Modal.getInstance(modalEl);

// เช็กว่า modal มี instance และกำลังแสดงผลอยู่ (มี class show)
                    if (modalInstance && modalEl.classList.contains('show')) {
                        modalInstance.hide();
                    }

                    if (tab.type === 'link') {
                        window.location.href = tab.href;
                    } else if (tab.type === 'button') {

                    }


                },
                async showModal() {
                    const modal = new bootstrap.Modal(document.getElementById('eventModal'));
                    modal.show();
                },
            },
        });
	
	</script>
@endpush

