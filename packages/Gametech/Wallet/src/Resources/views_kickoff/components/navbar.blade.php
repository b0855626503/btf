<script type="text/x-template" id="navbar-template">
	<nav id="main-nav" class="navbar navbar-expand-sm navbar-light">
		<div class="container" style="max-height: 100%">
			<div class="d-inline-flex align-items-center ham-menu">
				<button class="navbar-toggler p-0" type="button" @click="toggleNavbar"
				        aria-controls="navbarSupportedContent" :aria-expanded="isOpen.toString()"
				        aria-label="Toggle navigation" style="height: 35px; width: 35px;">
					<span class="bi bi-list bi-2x text-light"></span>
				</button>
			</div>
			
			<a href="{{ route('customer.session.index') }}"
			   class="navbar-brand m-0 d-flex align-items-center router-link-exact-active">
				<img id="main-logo" src="{{ url(core()->imgurl($config->logo,'img')) }}">
			</a>
			
			<div id="auth-wrapper" class="group-button-user p-1 rounded-pill login-b">
				<div class="d-none d-md-inline-flex">
					<a href="{{ route('customer.session.destroy') }}"
					   class="nav-link register-btn btn btn-custom-secondary rounded-pill d-flex align-items-center pt-1 pb-1 text-white justify-content-center homeregis"
					   aria-label="logout">
            <span class="fw-bold text-highlight d-flex align-items-center">
              <i class="bi bi-box-arrow-right me-2 nav-icon text-white"></i> {{ __('app.home.logout') }}
            </span>
					</a>
				</div>
			</div>
			
			<div class="collapse navbar-collapse navbar-content-index" :class="{ show: isOpen }"
			     id="navbarSupportedContent">
				<div class="navbar-nav ms-auto align-items-center">
					<li class="nav-item header-group-menu pt-3">
						<span>Pages</span>
					</li>
					
					<li class="nav-item bg-box-1 nc-home btn-home">
						<a href="{{ route('customer.home.index') }}"
						   class="nav-link btn btn-box-1 d-flex align-items-center btn-lg position-relative">
							<span class="text-highlight">{{ __('app.login.home') }}</span>
						</a>
					</li>
					
					<li class="nav-item bg-box-1 btn-contact">
						<a href="{{ $config->linelink }}"
						   class="nav-link btn btn-box-1 d-flex align-items-center btn-lg position-relative"
						   target="_blank">
							<span class="text-highlight">{{ __('app.home.contact') }}</span>
						</a>
					</li>
					
					<li class="nav-item bg-box-1 w-100 d-flex justify-content-center btn-deposit">
						<button type="button" class="btn nav-link custom btn-box-1 d-flex align-items-center btn-lg"
						        @click="openDepositModal">
							<span class="nav-item-text text-highlight">{{ __('app.home.deposit') }}</span>
						</button>
					</li>
					
					<li class="nav-item bg-box-1 w-100 d-flex justify-content-center flex-lg-fill btn-withdraw">
						<button type="button" class="btn nav-link custom btn-box-1 d-flex align-items-center btn-lg"
						        @click="openWithdrawModal">
							<span class="nav-item-text text-highlight">{{ __('app.home.withdraw') }}</span>
						</button>
					</li>
					
					<li class="nav-item bg-box-1 btn-language dropdown d-none d-md-block">
						<a class="nav-link btn btn-box-1 d-flex align-items-center btn-lg position-relative dropdown-toggle"
						   href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
							<span class="text-highlight">{{ __('app.login.language') }}</span>
						</a>
						<ul class="dropdown-menu" aria-labelledby="navbarDropdown">
							<li><a class="dropdown-item" href="{{ route('customer.home.lang', ['lang' => 'en']) }}"><img
											src="/images/flag/en.png" width="32" height="32"
											class="img img-fluid img-sm"> English</a>
							</li>
							<li><a class="dropdown-item" href="{{ route('customer.home.lang', ['lang' => 'th']) }}"><img
											src="/images/flag/th.png" width="32" height="32"
											class="img img-fluid img-sm"> ภาษาไทย</a></li>
							<li><a class="dropdown-item" href="{{ route('customer.home.lang', ['lang' => 'kh']) }}"><img
											src="/images/flag/kh.png" width="32" height="32"
											class="img img-fluid img-sm"> ភាសាខ្មែរ</a></li>
							<li><a class="dropdown-item" href="{{ route('customer.home.lang', ['lang' => 'la']) }}"><img
											src="/images/flag/la.png" width="32" height="32"
											class="img img-fluid img-sm"> ພາສາລາວ</a>
							<li><a class="dropdown-item" href="{{ route('customer.home.lang', ['lang' => 'cn']) }}"><img
											src="/images/flag/cn.png" width="32" height="32"
											class="img img-fluid img-sm"> 中國人</a></li>
							<li><a class="dropdown-item" href="{{ route('customer.home.lang', ['lang' => 'kr']) }}"><img
											src="/images/flag/kr.png" width="32" height="32"
											class="img img-fluid img-sm"> 한국어</a></li>
						</ul>
					</li>
					
					
					<li class="nav-item header-group-menu pt-3">
						<span>{{ __('app.login.language') }}</span>
					</li>
					<li class="nav-item bg-box-1 nc-home btn-language d-block d-md-none">
						<a href="{{ route('customer.home.lang', ['lang' => 'en']) }}"
						   class="nav-link btn btn-box-1 d-flex align-items-center btn-lg position-relative">
                            <span class="text-highlight"><img
			                            src="/images/flag/en.png" width="32" height="32" class="img img-fluid img-sm"> English</span>
						</a>
					</li>
					<li class="nav-item bg-box-1 nc-home btn-language d-block d-md-none">
						<a href="{{ route('customer.home.lang', ['lang' => 'th']) }}"
						   class="nav-link btn btn-box-1 d-flex align-items-center btn-lg position-relative">
                            <span class="text-highlight"><img
			                            src="/images/flag/th.png" width="32" height="32" class="img img-fluid img-sm"> ภาษาไทย</span>
						</a>
					</li>
					<li class="nav-item bg-box-1 nc-home btn-language d-block d-md-none">
						<a href="{{ route('customer.home.lang', ['lang' => 'kh']) }}"
						   class="nav-link btn btn-box-1 d-flex align-items-center btn-lg position-relative">
                            <span class="text-highlight"><img
			                            src="/images/flag/kh.png" width="32" height="32" class="img img-fluid img-sm"> ភាសាខ្មែរ</span>
						</a>
					</li>
					<li class="nav-item bg-box-1 nc-home btn-language d-block d-md-none">
						<a href="{{ route('customer.home.lang', ['lang' => 'la']) }}"
						   class="nav-link btn btn-box-1 d-flex align-items-center btn-lg position-relative">
                            <span class="text-highlight"><img
			                            src="/images/flag/la.png" width="32" height="32" class="img img-fluid img-sm"> ພາສາລາວ</span>
						</a>
					</li>
					<li class="nav-item bg-box-1 nc-home btn-language d-block d-md-none">
						<a href="{{ route('customer.home.lang', ['lang' => 'cn']) }}"
						   class="nav-link btn btn-box-1 d-flex align-items-center btn-lg position-relative">
                            <span class="text-highlight"><img
			                            src="/images/flag/cn.png" width="32" height="32" class="img img-fluid img-sm"> 中國人</span>
						</a>
					</li>
					<li class="nav-item bg-box-1 nc-home btn-language d-block d-md-none">
						<a href="{{ route('customer.home.lang', ['lang' => 'kr']) }}"
						   class="nav-link btn btn-box-1 d-flex align-items-center btn-lg position-relative">
                            <span class="text-highlight"><img
			                            src="/images/flag/kr.png" width="32" height="32" class="img img-fluid img-sm"> 한국어</span>
						</a>
					</li>
					
					
					<li class="nav-item header-group-menu pt-3">
						<span>Games</span>
					</li>
					
					<li v-for="menu in menus" :key="menu.key" :class="`btn-${menu.key}`"
					    class="nav-item cutom-game-entry d-flex justify-content-center invert-color position-relative">
						<a href="javascript:void(0)"
						   class="nav-link btn btn-box-1 d-flex align-items-center btn-lg"
						   @click="selectTabOrRedirect(menu.key)">
							<span class="nav-item-text text-highlight" v-text="getMenuLabel(menu.key)"></span>
						</a>
					</li>
					
					<li class="nav-item bg-box-2 d-inline-flex ms-2 logout-mobile" style="margin-bottom:5em;">
						
						<a href="{{ route('customer.session.destroy') }}"
						   class="border-0 text-decoration-none shadow px-3 btn-custom-secondary rounded-pill d-flex justify-content-center align-items-center nav-link btn btn-box-2 flex-grow-1">
							<i class="bi bi-box-arrow-right me-1 nav-icon pe-1"
							   style="color: rgb(181, 60, 60) !important;"></i>
							<span style="color: rgb(244, 170, 170) !important;padding-left:0 !important;">{{ __('app.home.logout') }}</span>
						</a>
					
					
					</li>
				</div>
			</div>
		</div>
	</nav>
</script>

@push('components')
	
	<script type="module">

        Vue.component('navbar-component', {
            template: '#navbar-template',
            data() {
                return {
                    isOpen: false,
                    showLangList: false

                };
            },

            computed: {
                getMenuLabel() {
                    return key => window.translations[key] || key;
                },
                menus() {
                    return (this.$root && this.$root.$data && this.$root.$data.menus) || [];
                },
            },
            methods: {
                toggleLangList() {
                    this.showLangList = !this.showLangList;
                },
                selectTabOrRedirect(tabKey) {
                    localStorage.setItem('selectTabKey', tabKey);

                    // ถ้าอยู่หน้า /member แล้ว ให้ selectTab ทันที
                    if (window.location.pathname === '/member') {
                        this.isOpen = !this.isOpen;
                        const vueInstance = this.$root;
                        vueInstance.$refs.gameTabComponent?.selectTab(tabKey);

                        const el = document.querySelector('#gametab');
                        el?.scrollIntoView({behavior: 'smooth'});
                    } else {
                        // เปลี่ยนหน้าไป /member แล้วจัดการต่อในหน้าใหม่
                        window.location.href = '/member';
                    }
                },
                selectGameType(key) {
                    this.isOpen = !this.isOpen;
                    this.$root.$refs.gameTabComponent.selectTab(key);
                },
                openDepositModal() {

                    this.$root.$refs.depositModalComponent.showModal();
                },
                openWithdrawModal() {
                    console.log('click withdraw')
                    this.$root.$refs.withdrawModalComponent.showModal();
                },
                toggleNavbar() {
                    this.isOpen = !this.isOpen;

                },
                redirectTo(url) {
                    window.location.href = url;
                },
                submitLogout() {
                    this.$refs.logoutForm.submit();
                }

            }
        });
	
	</script>
@endpush

