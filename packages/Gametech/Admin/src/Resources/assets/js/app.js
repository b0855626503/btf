// ======== ESM-first for Vite (Vue2 + jQuery stack) ========
const path = location.pathname
const isAuthPage = /\/(login|register|password|forgot|reset)/i.test(path)
const isAdminBankIn = /\/admin\/bank_in/.test(path)
// 0) Lodash (รองรับโค้ดเดิมที่อ้าง _ )
import _ from 'lodash'
window._ = _

// 1) jQuery มาก่อนปลั๊กอิน jQuery ทุกตัว
import $ from 'jquery'
window.$ = window.jQuery = $
try { /* eslint-disable no-undef */ global.$ = global.jQuery = $ } catch {}

// 2) axios
import axios from 'axios'
window.axios = axios
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'
const token = document.head.querySelector('meta[name="csrf-token"]')
if (token) window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content

// 3) moment (+ locale ไทยถ้าต้องการ)
import moment from 'moment';
import 'moment/locale/th';
moment.locale('th');
window.moment = window.Moment = moment

// 4) Bootstrap/Popper bundle (v4) + AdminLTE
import 'bootstrap/dist/js/bootstrap.bundle'


if (!isAuthPage) {

    import('admin-lte')
    import('daterangepicker').then(() => import('daterangepicker/daterangepicker.css'))
    // โหลด Tempus Dominus เฉพาะหลังบ้าน และหลัง moment พร้อม
    import('tempusdominus-bootstrap-4')

    Promise.all([
        // import('datatables.net').then(m => m.default(window, $)),
        import('datatables.net-bs4').then(m => m.default(window, $)),
        // import('datatables.net-responsive').then(m => m.default(window, $)),
        import('datatables.net-responsive-bs4').then(m => m.default(window, $)),
        // import('datatables.net-buttons').then(m => m.default(window, $)),
        import('datatables.net-buttons-bs4').then(m => m.default(window, $)),
        import('datatables.net-buttons/js/buttons.html5'),
        import('datatables.net-buttons/js/buttons.print'),
        import('datatables.net-buttons/js/buttons.colVis'),
    ])
//     import 'admin-lte'
//
//
// // 4.1) Date Range Picker (ต้องมี moment + jQuery แล้ว)
//     import 'daterangepicker'
//     import 'daterangepicker/daterangepicker.css'
//
// // import dtCore from 'datatables.net';
// // dtCore(window, $)
//
// // 5.2) Skin BS4 → side-effect imports (ห้ามเรียกเป็นฟังก์ชัน)
//     import 'datatables.net-bs4'
//
// // 5.3) Responsive (core → factory) + skin BS4 (side-effect)
// // import dtResponsive from 'datatables.net-responsive'
// // dtResponsive(window, $)
//     import 'datatables.net-responsive-bs4'
//
// // 5.4) Buttons (core → factory) + skin BS4 (side-effect)
// // import dtButtons from 'datatables.net-buttons'
// // dtButtons(window, $)
//     import 'datatables.net-buttons-bs4'
//
// // Tempus Dominus (bs4) — โหลดหลัง jQuery/Bootstrap
//     (async () => {
//         await import('tempusdominus-bootstrap-4');
//
//     })();
//
//
// // // 5.5) ปุ่มเสริม (side-effect modules)
//     import 'datatables.net-buttons/js/buttons.html5'
//     import 'datatables.net-buttons/js/buttons.print'
//     import 'datatables.net-buttons/js/buttons.colVis'

// 6) ไฟล์ภายในโปรเจกต์ (ถ้ามี)

}

import './toasty/src/toasty.js'
import './jquery.marquee'

// 7) Echo/Pusher — lazy init เฉพาะมี config
function resolveEchoConfig () {
    if (window.ECHO_CONFIG && typeof window.ECHO_CONFIG === 'object') return window.ECHO_CONFIG
    const meta = document.querySelector('meta[name="echo-key"]')
    if (!meta) return null
    const key  = meta.getAttribute('content')
    const host = meta.dataset?.host || window.location.hostname
    const port = Number(meta.dataset?.port || '6001')
    const tls  = meta.dataset?.tls === '1'
    return {
        broadcaster: 'pusher',
        key,
        wsHost: host,
        wsPort: port,
        wssPort: port,
        forceTLS: tls,
        disableStats: true,
        enabledTransports: ['ws', 'wss'],
        cluster: meta.dataset?.cluster || 'ap1',
    }
}

;(async function maybeInitEcho () {
    try {
        const cfg = resolveEchoConfig()
        if (!cfg || !cfg.key) { if (!window.Echo) window.Echo = null; return }
        const [{ default: Pusher }, { default: Echo }] = await Promise.all([
            import('pusher-js'),
            import('laravel-echo'),
        ])
        window.Pusher = Pusher
        window.Echo = new Echo(cfg)
        window.dispatchEvent(new Event('echo:ready'))
    } catch {
        if (!window.Echo) window.Echo = null
    }
})()

// 8) Vue2 (runtime+compiler) + PortalVue + BootstrapVue
import Vue from 'vue'
window.Vue = Vue

import PortalVue from 'portal-vue'
Vue.use(PortalVue)

import { BootstrapVue, IconsPlugin } from 'bootstrap-vue'
Vue.use(BootstrapVue)
Vue.use(IconsPlugin)

// 9) VeeValidate v2 + locale TH
import VeeValidate, { Validator } from 'vee-validate'
import th from 'vee-validate/dist/locale/th'
Validator.localize('th', th)
Vue.use(VeeValidate, { inject: true, fieldsBagName: 'veeFields' })

// 10) SweetAlert2 + Toast helper
import Swal from 'sweetalert2'
window.Swal = Swal
const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    didOpen: t => {
        t.addEventListener('mouseenter', Swal.stopTimer)
        t.addEventListener('mouseleave', Swal.resumeTimer)
    }
})
window.Toast = Toast

// 11) Toasty instance (ถ้ามี lib นี้)
if (window.Toasty) {
    window.ToastyInstance = new window.Toasty({
        classname: 'toast',
        transition: 'fade',
        insertBefore: true,
        duration: 5000,
        enableSounds: true,
        autoClose: true,
        progressBar: true,
        sounds: {
            info: 'storage/sound/alert.mp3',
            success: 'storage/sound/alert.mp3',
            warning: 'storage/sound/alert.mp3',
            error: 'storage/sound/alert.mp3'
        }
    })
}

// 12) Event bus กลาง
window.eventBus = new Vue()

// 13) Bootstrap root + ส่งสัญญาณพร้อมใช้งานให้สคริปต์เพจ
document.addEventListener('DOMContentLoaded', () => {
    Vue.mixin({
        data () {
            return {
                imageObserver: null,
                baseUrl: document.querySelector('meta[name="app-url"]')?.content || ''
            }
        },
        methods: {
            redirect (route) { if (route) window.location.href = route },
            isMobile () {
                const ua = navigator.userAgent
                const mobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini|mobi/i.test(ua)
                if (mobile && this.isMaxWidthCrossInLandScape()) return false
                return mobile
            },
            isMaxWidthCrossInLandScape () { return window.innerWidth > 900 },
            getDynamicHTML (input) {
                let _staticRenderFns
                const { render, staticRenderFns } = Vue.compile(input)
                _staticRenderFns = this.$options.staticRenderFns.length > 0
                    ? this.$options.staticRenderFns
                    : (this.$options.staticRenderFns = staticRenderFns)
                try {
                    const output = render.call(this, this.$createElement)
                    this.$options.staticRenderFns = _staticRenderFns
                    return output
                } catch (e) {
                    console.log('render error', e)
                    this.$options.staticRenderFns = _staticRenderFns
                    return null
                }
            },
            getStorageValue (key) {
                const v = window.localStorage.getItem(key)
                return v ? JSON.parse(v) : v
            },
            setStorageValue (key, value) {
                window.localStorage.setItem(key, JSON.stringify(value))
                return true
            }
        }
    })

    if (document.getElementById('app')) {
        // eslint-disable-next-line no-new
        new Vue({
            el: '#app',
            data () { return { modalIds: {} } },
            mounted () {
                const lang = document.documentElement.lang || 'th'
                Validator.localize(lang)
                setTimeout(() => {
                    this.addServerErrors()
                    this.addFlashMessages()
                    window.dispatchEvent(new Event('app:ready'))
                }, 0)
                this.addIntersectionObserver()
            },
            methods: {
                onSubmit (e) {
                    this.toggleButtonDisable(true)
                    if (typeof tinyMCE !== 'undefined' && tinyMCE?.triggerSave) tinyMCE.triggerSave()
                    this.$validator.validateAll().then(ok => {
                        if (ok) e.target.submit()
                        else {
                            this.toggleButtonDisable(false)
                            window.eventBus.$emit('onFormError')
                        }
                    })
                },
                toggleButtonDisable (v) { document.querySelectorAll('button').forEach(b => (b.disabled = v)) },
                addServerErrors (scope = null) {
                    if (!window.serverErrors) return
                    for (const key in window.serverErrors) {
                        const inNames = []
                        key.split('.').forEach((chunk, i) => inNames.push(i ? `[${chunk}]` : chunk))
                        const inputName = inNames.join('')
                        const field = this.$validator.fields.find({ name: inputName, scope })
                        if (field) {
                            this.$validator.errors.add({
                                id: field.id, field: inputName, msg: window.serverErrors[key][0], scope
                            })
                        }
                    }
                },
                addFlashMessages () {
                    if (!window.flashMessages) return
                    for (const key in window.flashMessages) {
                        const fm = window.flashMessages[key]
                        if (!fm?.message) continue
                        Toast.fire({ icon: fm.type, title: fm.message })
                    }
                },
                showModal (refer) { this.$nextTick(() => this.$root.$refs[refer]?.show?.()) },
                addIntersectionObserver () {
                    try {
                        this.imageObserver = new IntersectionObserver(entries => {
                            entries.forEach(entry => {
                                if (entry.isIntersecting) {
                                    const el = entry.target
                                    if (el.dataset?.src) el.src = el.dataset.src
                                }
                            })
                        })
                    } catch { /* older browser: ignore */ }
                }
            }
        })
    } else {
        window.dispatchEvent(new Event('app:ready'))
    }
})

// 14) เลือก import โค้ดเพจด้วยตัวเอง (ตัวอย่าง: bank_in)
// if (location.pathname.includes('/admin/bank_in')) {
//     import('@pages/bank_in.js')
// }
