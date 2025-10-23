<script type="text/x-template" id="member-credit-template">
	
	<div class="member__header credit-box container-fluid mt-1 px-2 shadow">
		<div class="credit-content p-3 my-0 mx-auto">
			<div class="slider position-absolute w-100 h-100 top-0 start-0"></div>
			<div class="row w-100 g-0">
				<div class="profile-badge-new position-relative">
					<div class="txt-number-phone rounded-pill bg-white shadow d-flex align-items-center"
					     v-text="item.user_name"></div>
					<div class="front shadow">
						<img src="/assets/kimberbet/images/icon/profile_user.svg" style="width: 3em;">
					</div>
				</div>
			</div>
			<div class="d-flex latest_update_data d_flex align_items_center pt-1">
				<span class="txt_update">{{ __('app.home.lastupdate') }} &nbsp;&nbsp;&nbsp; </span>
				<span class="latest_date" v-text="item.lastupdate"></span>
				<span class="latest_time"></span>
			</div>
			<div class="member__header--showmoney  mt-2 g-0 align-items-center moneymb">
				<button class="reloadmoney" @click="loadCredit()">
					<i :class="['fas', 'fa-sync-alt', { 'fa-spin': isSpinning }]"></i>
				</button>
				<table>
					<tr>
						<td class="bordermbleft">
							<span v-text="item.balance">0</span>
							<li>{{ __('app.home.credit') }}</li>
						</td>
						<td>
							<span v-text="item.diamond">0</span>
							<li>{{ __('app.profile.diamond') }}</li>
						</td>
					</tr>
					<tr>
						<td>
							<span v-text="item.cashback">0</span>
							<li>{{ __('app.home.cashback') }}</li>
						</td>
						<td class="bordermbright">
							<li>{{ __('app.home.suggest') }}
								<credit-box v-text="item.downline">0</credit-box>
							</li>
							<li>{{ __('app.home.commission') }}
								<credit-box v-text="item.faststart">0</credit-box>
							</li>
						</td>
					</tr>
				</table>
			</div>
		</div>
	
	</div>
</script>


@push('components')
	
	<script type="module">

        Vue.component('member-credit', {
            template: '#member-credit-template',
            data() {
                return {
                    item: [],
                    isSpinning: false
                };
            },
            mounted() {
                this.loadCredit();

            },
            methods: {
                async loadCredit() {
                    this.isSpinning = true;
                    const res = await axios.get("{{ route('customer.home.credit') }}")
                    if (res.data.success) {
                        this.item = res.data.profile;
                    }
                    setTimeout(() => {
                        this.isSpinning = false;
                    }, 2000); // หมุน 5 วิ

                },

            }
        });
	
	</script>
@endpush

