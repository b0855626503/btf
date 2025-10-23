<script type="text/x-template" id="member-history-template">
	<div class="sub-page sub-footer" style="min-height: 100vh;">
		<div class="container pt-3 px-0 history-container" style="max-width: 720px;">
			<div class="card bg-transparent">
				<div class="card-body container">
					
					<!-- Tab Bar -->
					<div class="nav-tab-bar">
						<button v-for="tab in tabs"
						        :key="tab.key"
						        class="nav-tab"
						        :class="{ active: currentTab === tab.key }"
						        @click="selectTab(tab.key)"
						        v-text="tab.label">
						</button>
					</div>
					
					<!-- Heading -->
					<h3 :class="getTabClass(currentTab)" v-text="getTabLabel(currentTab)"></h3>
					
					<!-- Loading -->
					<div v-if="loading" class="text-center py-5">
						<div class="spinner-border text-light"></div>
					</div>
					
					<!-- List -->
					<div v-else>
						<div v-if="dataStore[currentTab].length">
							<div v-for="item in dataStore[currentTab]" :key="item.id" class="card bg-dark mb-2">
								<div class="card-body d-flex justify-content-between p-2">
									<div>
										<div><span v-text="item.method"></span></div>
										<div class="text-muted small" v-text="item.date_create"
										     style="text-align: left"></div>
									</div>
									<div class="text-end">
										<div class="fs-5" v-html="item.amount_text"></div>
										<div class="text-muted small" v-text="item.status_display"></div>
									</div>
								</div>
							</div>
						</div>
						<div v-else class="card bg-dark text-center py-5" style="min-height: 25em;">
							<div class="card-body">
								<em>{{ __('app.home.no_list') }}</em>
							</div>
						</div>
					</div>
					
					<em class="small fw-light text-muted d-block mt-4">{{ __('app.home.limit_history') }}</em>
				</div>
			</div>
		</div>
	</div>
</script>

@push('components')
	
	<script type="module">

        Vue.component('member-history', {
            template: '#member-history-template',
            data() {
                return {
                    tabs: [],
                    dataStore: {
                        deposit: [],
                        withdraw: [],
                        spin: [],
                        cashback: [],
                        memberic: [],
                        faststart: [],
                        bonus: [],
                        other: [],
                    },
                    currentTab: 'deposit',
                    loading: false,
                    swiper: null,
                }
            },
            created() {
                this.tabs = [
                    {
                        key: 'deposit',
                        label: this.trans('app.history.deposit'),
                        title: this.trans('app.history.deposit_last'),
                        titleClass: 'text-success'
                    },
                    {
                        key: 'withdraw',
                        label: this.trans('app.history.withdraw'),
                        title: this.trans('app.history.withdraw_last'),
                        titleClass: 'text-danger'
                    },
                    {
                        key: 'spin',
                        label: this.trans('app.history.spin'),
                        title: this.trans('app.history.spin_last'),
                        titleClass: 'text-info'
                    },
                    {
                        key: 'cashback',
                        label: this.trans('app.history.cashback'),
                        title: this.trans('app.history.cashback_last'),
                        titleClass: 'text-warning'
                    },
                    {
                        key: 'memberic',
                        label: this.trans('app.history.memberic'),
                        title: this.trans('app.history.memberic_last'),
                        titleClass: 'text-secondary'
                    },
                    {
                        key: 'faststart',
                        label: this.trans('app.history.faststart'),
                        title: this.trans('app.history.faststart_last'),
                        titleClass: 'text-info'
                    },
                    {
                        key: 'bonus',
                        label: this.trans('app.history.bonus'),
                        title: this.trans('app.history.bonus_last'),
                        titleClass: 'text-info'
                    },
                    {
                        key: 'other',
                        label: this.trans('app.history.other'),
                        title: this.trans('app.history.other_last'),
                        titleClass: 'text-info'
                    },
                ];
            },
            methods: {
                trans(key, replace = {}) {
                    var translation = key.split('.').reduce((t, i) => t[i] || null, window.i18n);

                    for (var placeholder in replace) {
                        translation = translation.replace(`:${placeholder}`, replace[placeholder]);
                    }
                    return translation;
                },
                getTabLabel(key) {
                    const tab = this.tabs.find(t => t.key === key);
                    return tab ? this.trans(`app.history.${key}_last`) : '';
                },
                getTabClass(key) {
                    const tab = this.tabs.find(t => t.key === key);
                    return tab ? tab.class : '';
                },
                async selectTab(tabKey) {
                    this.currentTab = tabKey;

                    if (this.dataStore[tabKey].length > 0) return; // มีข้อมูลแล้วไม่โหลดซ้ำ

                    this.loading = true;

                    try {
                        const response = await axios.post("{{ route('customer.history.store') }}", {id: tabKey});
                        const r = response.data;

                        if (!r.success || !Array.isArray(r.data)) throw new Error('โหลดข้อมูลล้มเหลว');

                        this.dataStore[tabKey] = r.data.map(o => ({
                            ...o,
                            time_ago: moment(o.time).fromNow(),
                            amount_text: o.transfer_type + ' ' + this.intToMoney(o.amount),
                            description: o.is_bonus ? 'ได้รับโบนัส' : (tabKey === 'withdraw' ? 'ถอนเงิน' : 'ฝากเข้า'),
                        }));
                    } catch (err) {
                        console.error(`โหลดข้อมูล ${tabKey} ผิดพลาด`, err);
                        this.dataStore[tabKey] = [];
                    } finally {
                        this.loading = false;
                    }
                },
                intToMoney(n) {
                    return parseFloat(n).toLocaleString(undefined, {minimumFractionDigits: 2});
                }
            },
            mounted() {
                this.selectTab(this.currentTab);
            }
        });
	
	</script>
@endpush

