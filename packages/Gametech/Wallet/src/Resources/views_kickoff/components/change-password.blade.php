<script type="text/x-template" id="change-password-template">
	<div class="change-pin-container mt-3">
		<div class="header-block-content d-block rounded-top py-2">
			<h5 class="text-center mb-0 text-dark lh-1">{{ __('app.profile.changepass') }}</h5>
		</div>
		<div class="card bg-dark-2 rounded-0 rounded-bottom">
			<div class="card-body p-3">
        <span class="text-center d-block lh-1 text-content mb-2">
          <small>{{ __('app.profile.changepass_text') }}</small>
        </span>
				<form class="theme-form mx-auto" style="max-width: 25em;"
				      method="POST" ref="form" action="{{ route('customer.profile.changepassapi') }}"
				      @submit.prevent="onSubmit">
					<div class="input-group mb-3 custom-style-input">
            <span class="input-group-text p-0">
              <i class="bi bi-lock-fill fs-4"></i>
            </span>
						<input type="password" v-model="password_current"
						       placeholder="{{ __('app.profile.old_password') }}"
						       minlength="6" maxlength="10" required class="form-control">
					</div>
					<div class="input-group mb-3 custom-style-input">
            <span class="input-group-text">
              <i class="bi bi-key-fill bi-1-5x fs-4"></i>
            </span>
						<input type="password" v-model="password" placeholder="{{ __('app.profile.new_password') }}"
						       minlength="6" maxlength="10" required class="form-control">
					</div>
					<div class="input-group mb-3 custom-style-input">
            <span class="input-group-text">
              <i class="bi bi-key-fill bi-1-5x fs-4"></i>
            </span>
						<input type="password" v-model="password_confirmation"
						       placeholder="{{ __('app.profile.new_password_confirm') }}"
						       minlength="6" maxlength="10" required class="form-control">
					</div>
					<button type="submit" class="btn btn-custom-primary w-100 mt-4 rounded-pill">
						{{ __('app.home.changepass') }}
					</button>
				</form>
			</div>
		</div>
	</div>
</script>

@push('components')
	
	<script type="module">

        Vue.component('change-password-form', {
            template: '#change-password-template',
            data() {
                return {
                    password_current: '',
                    password: '',
                    password_confirmation: '',
                    isSubmitting: false,
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
                onSubmit() {
                    if (this.isSubmitting) return;
                    this.isSubmitting = true;

                    const payload = {
                        password_current: this.password_current,
                        password: this.password,
                        password_confirmation: this.password_confirmation
                    };

                    axios.post(this.$refs.form.action, payload)
                        .then(res => {
                            if (res.success) {


                                this.isSubmitting = false;

                                window.Toast.fire({
                                    icon: 'success',
                                    title: res.message
                                });
                                this.password_current = '';
                                this.password = '';
                                this.password_confirmation = '';
                            }

                            window.Toast.fire({
                                icon: 'error',
                                title: res.message
                            });
                        })
                        .catch(err => {
                            this.isSubmitting = false;
                            if (err.response && err.response.data && err.response.data.errors) {
                                const errors = err.response.data.errors;
                                let msg = Object.values(errors).map(v => v.join("\n")).join("\n");
                                window.Toast.fire({
                                    icon: 'info',
                                    title: msg
                                });
                            } else {
                                window.Toast.fire({
                                    icon: 'info',
                                    title: this.trans('app.status.tryagain')
                                });
                            }
                        });
                }
            }
        });
	
	</script>
@endpush

