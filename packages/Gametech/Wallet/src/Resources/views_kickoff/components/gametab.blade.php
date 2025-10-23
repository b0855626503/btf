<script type="text/x-template" id="gametab-template">
	
	<div class="member__games_entrance entrance_games_theme game_type-container container-fluid position-relative member-menu-bg py-1 mt-2">
		<div class="fs-4 mt-3 text-start">{{ __('app.home.game') }}</div>
		<div class="interior" style="min-height: 750px;">
			<div class="input-group mb-3 mt-3 position-absolute top-0 end-0 me-3 custon-input-search"
			     style="min-width: 250px; max-width: 50%;">
				
				<input type="search" maxlength="30" v-model="searchGame" placeholder="{{ __('app.input.search') }}"
				       class="form-control" v-if="selectedProvider">
				<input type="search" maxlength="30" v-model="searchProvider" placeholder="{{ __('app.input.search') }}"
				       class="form-control" v-else>
				<label class="input-group-text">
					<i class="bi bi-search fw-bolder"></i>
				</label>
			</div>
			<div class="entrance_games_theme-container">
				<div class="entrance_games_theme-2 d-flex pt-2" style="min-height: 750px;">
					<ul id="menuTypeGame_theme2" class="menu_games ps-0">
						
						<li class="menu_item p-2 d-flex flex-column justify-content-center align-items-center tabgamelink"
						    :class="{ active: selectedTab === type.key }" v-for="(type, i) in categories"
						    :key="type.key" @click="selectTabScroll(type.key)">
							<img
									:src="`/assets/kimberbet/images/icon/mini_${type.key}.svg`"
									alt=""
									class="menu-icon"
									v-on:error="handleImgError($event)"/>
							<span class="menu-name lh-1 pt-1 small fw-light text-white"
							      v-text="getGameLabel(type.key)"></span>
						</li>
					
					</ul>
					
					<div class="list_games_wrapper col ps-3 position-relative"
					     style="height: 677.289px; min-height: 750px;">
						
						<div v-if="selectedProvider">
							<div class="gamecontent" style="display:block;">
								<div class="list_games_wrapper col ps-3 position-relative" style=" min-height: 750px;">
									<div class="title-provider-name text-center d-flex justify-content-center">
										<span class="text-content title py-1 lead" v-text="selectedProvider"></span>
										<button class="btn-back-to-provider reset-btn" @click="selectedProvider = null">
											<i class="bi bi-arrow-left"></i>
										</button>
									</div>
									<ul class="list_games w-100 d-flex justify-content-center list-unstyled sub-list"
									    style="">
										
										<li
												v-for="(item, index) in filteredGames"
												:key="item.id"
												:class="getGameItemClass(item)"
												:style="getItemStyle(index)"
												@animationend="markAsRendered(item)"
										>
											
											
											<div class="game-title w-100 text-content text-center"
											     v-text="item.gameName"></div>
											<img :src="item.image.vertical" alt="" class="game-img w-100">
											
											<div class="d-flex justify-content-center">
												<button class="py-1 btn btn-custom-primary btn-play d-flex justify-content-center align-items-center"
												        @click.prevent="openGamePopup(item)">
													<i class="bi bi-controller me-1"></i> {{ __('app.home.playgame') }}
												</button>
											</div>
										</li>
										
										<li></li>
									</ul>
								</div>
							</div>
						</div>
						<div v-else>
							<div class="gamecontent" style="display:block;">
								
								<div class="title-provider-name text-center d-flex justify-content-center">
                                    <span class="text-content title py-1 lead"
                                          v-text="getGameLabel(selectedTab)"></span>
									<!---->
								</div>
								
								<ul class="list_providers w-100 d-flex justify-content-center list-unstyled sub-list"
								    style="">
									
									<li class="provider_item d-flex flex-column fade-in"
									    :style="{ animationDelay: (index * 100) + 'ms' }"
									    v-for="(item, index) in filteredProviders"
									    :key="item.provider" @click="loadGames(item)">
										<div class="game-title w-100  text-content text-center"
										     v-text="item.providerName"></div>
										<img :src="item.logoURL" alt="" class="game-img w-100">
									</li>
								
								</ul>
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
        Vue.component('gametab', {
            template: '#gametab-template',
            props: {
                apiGetgameTemplate: String,
                apiGetproviderTemplate: String,
                apiGetloginTemplate: String,
            },
            data: function () {
                return {
                    providerList: [],
                    selectedTab: 'lotto',
                    selectedProvider: null,
                    providerGames: [],
                    searchGame: '',
                    searchProvider: '',
                    shownItems: [],
                    renderedGameIds: [],
                    renderKey: 0,
                    customClassMap: {} // <--- บันทึก class ต่อ item.id
                }
            },
            computed: {
                categories() {
                    return (this.$root && this.$root.$data && this.$root.$data.menus) || [];
                },
                getGameLabel() {
                    return key => window.translations[key] || key;
                },
                // sortedProviders() {
                //     return this.providerList;
                // },
                sortedProviders() {
                    return this.providerList.slice().sort((a, b) =>
                        a.providerName.localeCompare(b.providerName, 'en', {sensitivity: 'base'})
                    );
                },
                filteredGames() {
                    if (!this.searchGame) return this.providerGames;

                    return this.providerGames.filter(g =>
                        (g.gameName || '').toLowerCase().includes(this.searchGame.toLowerCase())
                    );
                },
                filteredProviders() {
                    let filtered = this.sortedProviders;
                    if (this.searchProvider.trim() !== '') {
                        const search = this.searchProvider.trim().toLowerCase();
                        filtered = filtered.filter(p => (p.providerName || '').toLowerCase().includes(search));
                    }
                    return filtered;
                },
            },
            methods: {
                getItemStyle(index) {
                    return {
                        animationDelay: `${index * 100}ms`
                    };
                },
                getGameItemClass(item) {
                    if (this.customClassMap[item.id]) {
                        return this.customClassMap[item.id];
                    }

                    return ['game_item', 'd-flex', 'flex-column', 'fade-in'];
                },
                markAsRendered(item) {
                    if (item.rtp && item.rtp > 100) {
                        // ถ้า RTP > 95 → ใส่ bounce
                        this.$set(this.customClassMap, item.id, [
                            'game_item',
                            'd-flex',
                            'flex-column',
                            'animate__animated',
                            'animate__bounce',
                            'animate__infinite'
                        ]);
                    } else {
                        // ค่า default หลัง animation
                        this.$set(this.customClassMap, item.id, [
                            'game_item',
                            'd-flex',
                            'flex-column'
                        ]);
                    }
                },
                handleImgError(event) {
                    event.target.src = '/assets/kimberbet/images/icon/mini_slot.svg';
                },
                selectTab(key) {
                    console.log('select' + key);
                    // const el = document.querySelector('#gametab');
                    // if (el) {
                    //     el.scrollIntoView({behavior: 'smooth', block: 'start'});
                    // }
                    this.searchGame = '';
                    this.searchProvider = '';
                    this.selectedTab = key;
                    this.selectedProvider = null;
                    this.fetchProviderData(key); // เรียก API ใหม่ทุกครั้งเมื่อเปลี่ยน tab

                },
                selectTabScroll(key) {
                    console.log('select' + key);
                    const el = document.querySelector('#gametab');
                    if (el) {
                        el.scrollIntoView({behavior: 'smooth', block: 'start'});
                    }
                    this.searchGame = '';
                    this.searchProvider = '';
                    this.selectedTab = key;
                    this.selectedProvider = null;
                    this.fetchProviderData(key); // เรียก API ใหม่ทุกครั้งเมื่อเปลี่ยน tab

                },
                fetchProviderData(type) {
                    this.providerList = [];

                    const url = this.apiGetproviderTemplate
                        .replace('__TYPE__', type);
                    fetch(url)
                        .then(res => res.json())
                        .then(data => {
                            this.providerList = data || []; // สมมติว่า API ยังคงส่งในรูปแบบ { type: [...] }
                        })
                        .catch(err => console.error('โหลด provider ล้มเหลว:', err));
                },
                loadGames(item) {
                    const el = document.querySelector('#gametab');
                    if (el) {
                        el.scrollIntoView({behavior: 'smooth', block: 'start'});
                    }
                    console.log('loadGame ' + item.provider);
                    this.selectedProvider = item.provider;
                    this.searchGame = '';
                    this.providerGames = [];
                    this.customClassMap = {};
                    const url = this.apiGetgameTemplate
                        .replace('__TYPE__', item.providerType)
                        .replace('__PROVIDER__', item.provider);

                    fetch(url)
                        .then(res => res.json())
                        .then(data => {
                            this.providerGames = data || []; // สมมติว่า API ยังคงส่งในรูปแบบ { type: [...] }
                        })
                        .catch(err => console.error('โหลด provider ล้มเหลว:', err));


                },
                enterGame(game) {
                    console.log('กำลังโหลดเกม:', game);

                    fetch(`/api/login-to-game/${game.id}`)
                        .then(res => res.json())
                        .then(data => {
                            if (data && data.url) {
                                const gameUrl = data.url;

                                if (this.isMobile()) {
                                    // 👉 บนมือถือ เปิดแท็บใหม่
                                    window.open(gameUrl, '_blank');
                                } else {
                                    // 👉 บน desktop เปิด popup window
                                    const width = 1024;
                                    const height = 720;
                                    const left = (screen.width / 2) - (width / 2);
                                    const top = (screen.height / 2) - (height / 2);
                                    window.open(
                                        gameUrl,
                                        'GameWindow',
                                        `width=${width},height=${height},left=${left},top=${top},resizable=yes,scrollbars=yes`
                                    );
                                }
                            } else {
                                alert('ไม่สามารถเข้าสู่เกมได้');
                            }
                        })
                        .catch(err => {
                            console.error('Login API ล้มเหลว:', err);
                            alert('เกิดข้อผิดพลาด');
                        });
                },
                savePlayedGame(item) {
                    const key = 'playedGames';
                    let list = JSON.parse(localStorage.getItem(key)) || [];

                    if (!list.some(g => g.id === item.id)) {
                        list.push({
                            id: item.id,
                            gameName: item.gameName,
                            image: item.image,
                            provider: item.provider,
                            gameCategory: item.gameCategory
                        });

                        if (list.length > 10) list = list.slice(-10); // เก็บล่าสุด 10 เกม

                        localStorage.setItem(key, JSON.stringify(list));
                    }
                },
                openGamePopup(game) {
                    // const url = `https://api.leo918.com/api//${game.id}`;
                    const url = this.apiGetloginTemplate
                        .replace('__TYPE__', game.gameCategory)
                        .replace('__PROVIDER__', game.provider)
                        .replace('__ID__', game.id);

                    this.savePlayedGame(game);

                    if (this.isMobile()) {
                        // มือถือ: เปิดในแท็บใหม่
                        window.open(url, '_blank');
                    } else {
                        // Desktop: เปิด popup ขนาดกำหนด
                        const width = 1024;
                        const height = 720;
                        const left = (screen.width / 2) - (width / 2);
                        const top = (screen.height / 2) - (height / 2);

                        window.open(
                            url,
                            'GamePopup',
                            `width=${width},height=${height},left=${left},top=${top},resizable=yes,scrollbars=yes`
                        );
                    }
                },
                isMobile() {
                    return /Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
                }
            },
            mounted() {
                this.customClassMap = {};
                if (this.categories.length) {
                    this.selectTab(this.categories[0].key);
                }
            }
        });
	</script>
@endpush

