<div class="member__footer_menu d_flex">
	<button type="button" class="menu_item d_flex btn_reset route_link">
		<a href="{{ route('customer.profile.index') }}" class="w-100 h-100">
			<img src="/assets/kimberbet/img/icon/menu/member_menu_profile.svg" alt="" class="ic_menu">
			<span class="txt_menu">{{ __('app.home.profile') }}</span>
		</a>
	</button>
	<button type="button" class="menu_item d_flex btn_reset route_link">
		<a href="{{ route('customer.history.index') }}" class="w-100 h-100">
			<img src="/assets/kimberbet/img/icon/menu/member_menu_history.svg" alt="" class="ic_menu">
			<span class="txt_menu">{{ __('app.home.history') }}</span>
		</a>
	</button>
	<button type="button" class="menu_item d_flex btn_reset active route_link">
		<a href="{{ route('customer.home.index') }}" class="w-100 h-100 router-link-exact-active router-link-active">
			<img src="/assets/kimberbet/img/icon/home.svg" alt="" class="ic_menu">
			<span class="txt_menu">{{ __('app.login.home') }}</span>
		</a>
	</button>
	<button type="button" class="menu_item d_flex btn_reset route_link">
		<a href="{{ route('customer.promotion.index') }}" class="w-100 h-100">
			<img src="/assets/kimberbet/img/icon/menu/member_menu_giftbox.svg" alt="" class="ic_menu">
			<span class="txt_menu">{{ __('app.home.promotion') }}</span>
		</a>
	</button>
	<button type="button" class="menu_item d_flex btn_reset route_link">
		<a href="{{ $config->linelink }}" target="_blank" class="w-100 h-100">
			<img src="/assets/kimberbet/img/icon/menu/member_menu_contact.svg" alt="" class="ic_menu">
			<span class="txt_menu">{{ __('app.home.contact') }}</span>
		</a>
	</button>
</div>

<div class="myAlert-top alertcopy">
	<i class="fa fa-check-circle"></i>
	<br>
	<strong>คัดลอกเรียบร้อยแล้ว </strong>
</div>

<deposit-modal ref="depositModalComponent">
	<upload-slip></upload-slip>
</deposit-modal>
<withdraw-modal ref="withdrawModalComponent"></withdraw-modal>
<bonus-modal ref="bonusModalComponent"></bonus-modal>
<event-modal ref="eventModalComponent"></event-modal>





