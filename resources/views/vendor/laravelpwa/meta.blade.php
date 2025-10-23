<!-- Web Application Manifest -->
<link rel="manifest" href="{{ route('laravelpwa.manifest') }}">
<!-- Chrome for Android theme color -->
<meta name="theme-color" content="{{ $config['theme_color'] }}">

<!-- Add to homescreen for Chrome on Android -->
<meta name="mobile-web-app-capable" content="{{ $config['display'] == 'standalone' ? 'yes' : 'no' }}">
<meta name="application-name" content="{{ $config['short_name'] }}">
<link rel="icon" sizes="{{ data_get(end($config['icons']), 'sizes') }}"
      href="{{ data_get(end($config['icons']), 'src') }}">

<!-- Add to homescreen for Safari on iOS -->
<meta name="apple-mobile-web-app-capable" content="{{ $config['display'] == 'standalone' ? 'yes' : 'no' }}">
<meta name="apple-mobile-web-app-status-bar-style" content="{{  $config['status_bar'] }}">
<meta name="apple-mobile-web-app-title" content="{{ $config['short_name'] }}">
<link rel="apple-touch-icon" href="{{ data_get(end($config['icons']), 'src') }}">



<!-- Service Worker register (เหมือนเดิม) -->
<script>
    if ('serviceWorker' in navigator) {
        (async () => {
            try {
                // ===== Register SW =====
                const v = "{{ filemtime(public_path('serviceworker.js')) }}";
                const reg = await navigator.serviceWorker.register(`/serviceworker.js?v=${v}`, { scope: '/' });

                // ===== Push helpers =====
                const VAPID_PUBLIC_KEY = '{{ env('VAPID_PUBLIC_KEY') }}'; // ตั้งใน .env
                const hasPush = 'PushManager' in window;
                const hasNotification = 'Notification' in window;

                function urlBase64ToUint8Array(base64String) {
                    const padding = '='.repeat((4 - base64String.length % 4) % 4);
                    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
                    const raw = atob(base64); const out = new Uint8Array(raw.length);
                    for (let i = 0; i < raw.length; ++i) out[i] = raw.charCodeAt(i);
                    return out;
                }

                async function subscribePush() {
                    if (!hasPush || !hasNotification) {
                        console.warn('[PUSH] Browser ไม่รองรับ Push'); return;
                    }
                    // ถ้าให้สิทธิแล้วก็ไม่ต้องขอซ้ำ
                    let perm = Notification.permission;
                    if (perm === 'default') {
                        perm = await Notification.requestPermission();
                    }
                    if (perm !== 'granted') {
                        console.warn('[PUSH] ผู้ใช้ไม่อนุญาต'); return;
                    }
                    if (!VAPID_PUBLIC_KEY) {
                        console.warn('[PUSH] ไม่มี VAPID_PUBLIC_KEY'); return;
                    }
                    const existing = await reg.pushManager.getSubscription();
                    if (existing) {
                        // มีอยู่แล้วก็ถือว่าสำเร็จ
                        return existing;
                    }
                    const appKey = urlBase64ToUint8Array(VAPID_PUBLIC_KEY);
                    const sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: appKey });
                    await fetch('/api/push/subscribe', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        body: JSON.stringify(sub),
                        credentials: 'include'
                    }).catch(err => console.warn('[PUSH] subscribe api fail', err));
                    console.log('[PUSH] subscribed');
                    return sub;
                }

                async function unsubscribePush() {
                    if (!hasPush) return;
                    const sub = await reg.pushManager.getSubscription();
                    if (sub) {
                        try {
                            await fetch('/api/push/unsubscribe', {
                                method: 'DELETE',
                                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                                body: JSON.stringify({ endpoint: sub.endpoint }),
                                credentials: 'include'
                            });
                        } catch (e) { console.warn('[PUSH] unsubscribe api fail', e); }
                        await sub.unsubscribe().catch(() => {});
                        console.log('[PUSH] unsubscribed');
                    }
                }

                // ===== Platform guards =====
                function isIOS() {
                    const ua = navigator.userAgent;
                    return /iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
                }
                function isInstalledPWA() {
                    const dmStandalone = window.matchMedia && window.matchMedia('(display-mode: standalone)').matches;
                    const iosStandalone = typeof navigator.standalone !== 'undefined' ? navigator.standalone : false;
                    return dmStandalone || iosStandalone;
                }
                function envSupportsPush() {
                    return ('serviceWorker' in navigator) && hasPush && hasNotification;
                }

                // ===== Hook plain buttons (optional) =====
                document.querySelectorAll('.js-enable-push').forEach(btn => btn.addEventListener('click', subscribePush));
                document.querySelectorAll('.js-disable-push').forEach(btn => btn.addEventListener('click', unsubscribePush));

                // ===== Resubscribe hint from SW =====
                navigator.serviceWorker.addEventListener('message', (ev) => {
                    if (ev.data?.type === 'PUSH_SUBSCRIPTION_EXPIRED') {
                        console.log('[PUSH] subscription expired → re-subscribe');
                        subscribePush();
                    }
                });

                // ===== SW update auto-apply (เดิม) =====
                reg.addEventListener('updatefound', () => {
                    const nw = reg.installing;
                    if (!nw) return;
                    nw.addEventListener('statechange', () => {
                        if (nw.state === 'installed' && navigator.serviceWorker.controller) {
                            const applyUpdate = async () => {
                                if (reg.waiting) reg.waiting.postMessage({ type: 'SKIP_WAITING' });
                                navigator.serviceWorker.addEventListener('controllerchange', () => location.reload());
                            };
                            applyUpdate();
                        }
                    });
                });

                // ===== POPUP integration =====
                const POPUP_KEY = 'notify_popup_state_v1';
                const COOLDOWN_DENY_DAYS = 7;
                const COOLDOWN_DISMISS_HOURS = 24;
                const SHOW_DELAY_MS = 1200;

                const $wrap  = document.querySelector('.notify-popup-wrapper');
                const $allow = document.querySelector('.allow-notify');
                const $deny  = document.querySelector('.not-allow-notify');
                const $close = document.querySelector('.close-notify');

                // hint element inside popup
                let $hint = document.getElementById('push-hint-inline');
                if (!$hint) {
                    $hint = document.createElement('div');
                    $hint.id = 'push-hint-inline';
                    $hint.style.cssText = 'color:#ffcf66;font-size:.92rem;margin-top:6px;display:none';
                    const contentDetail = document.querySelector('.notify-popup-body .content-detail');
                    contentDetail && contentDetail.appendChild($hint);
                }

                const now = () => Date.now();
                const addDays  = (d) => now() + d*24*3600*1000;
                const addHours = (h) => now() + h*3600*1000;
                const saveState = (obj) => localStorage.setItem(POPUP_KEY, JSON.stringify(obj));
                const loadState = () => { try { return JSON.parse(localStorage.getItem(POPUP_KEY) || '{}'); } catch { return {}; } };

                function showPopup(msg) {
                    if (!$wrap) return;
                    if (msg) { $hint.style.display='block'; $hint.textContent = msg; }
                    else { $hint.style.display='none'; $hint.textContent=''; }
                    $wrap.classList.add('show');
                    $wrap.style.display = 'flex';
                }
                function hidePopup() {
                    if (!$wrap) return;
                    $wrap.classList.remove('show');
                    $wrap.style.display = 'none';
                }

                function shouldShowPopup() {
                    if (!envSupportsPush()) return { show:false, reason:'เบราว์เซอร์นี้ไม่รองรับการแจ้งเตือน' };
                    if (location.protocol !== 'https:' && location.hostname !== 'localhost') {
                        return { show:false, reason:'ต้องใช้ผ่าน HTTPS หรือ localhost' };
                    }
                    const st = loadState();
                    if (st.until && now() < st.until) return { show:false, reason:'cooldown' };
                    if (st.decided === 'allowed') return { show:false, reason:'already-allowed' };

                    const perm = Notification.permission;
                    if (perm === 'granted') return { show:false, reason:'already-granted' };
                    if (perm === 'denied')  return { show:true, needExplain:true, reason:'denied-in-browser' };

                    if (isIOS() && !isInstalledPWA()) return { show:true, needExplain:true, reason:'ios-need-install' };
                    return { show:true };
                }

                async function onAllowClick() {
                    // guard iOS
                    if (isIOS() && !isInstalledPWA()) {
                        showPopup('บน iOS กรุณา “เพิ่มไปยังหน้าจอหลัก” แล้วเปิดแอปจากไอคอน จึงจะเปิดแจ้งเตือนได้');
                        return;
                    }
                    try {
                        const sub = await subscribePush();
                        if (sub) {
                            saveState({ decided:'allowed', until:0 });
                            hidePopup();
                        } else {
                            // ผู้ใช้กดยกเลิก permission
                            saveState({ decided:'denied', until:addDays(COOLDOWN_DENY_DAYS) });
                            hidePopup();
                        }
                    } catch (err) {
                        console.warn('[PUSH] subscribe error', err);
                        showPopup('สมัครแจ้งเตือนไม่สำเร็จ ลองอีกครั้งหรือตรวจการตั้งค่าเบราว์เซอร์');
                    }
                }
                function onDenyClick() { saveState({ decided:'denied', until:addDays(COOLDOWN_DENY_DAYS) }); hidePopup(); }
                function onCloseClick(){ saveState({ decided:'dismissed', until:addHours(COOLDOWN_DISMISS_HOURS) }); hidePopup(); }

                $allow && $allow.addEventListener('click', onAllowClick);
                $deny  && $deny.addEventListener('click', onDenyClick);
                $close && $close.addEventListener('click', onCloseClick);

                // Auto show popup (เมื่อเหมาะสม) + ออโต้สมัครถ้า granted
                setTimeout(async () => {
                    const res = shouldShowPopup();
                    if (res.show) {
                        if (res.needExplain) {
                            if (res.reason === 'ios-need-install') {
                                showPopup('บน iOS กรุณา “เพิ่มไปยังหน้าจอหลัก” แล้วเปิดแอปจากไอคอน จึงจะเปิดแจ้งเตือนได้');
                            } else if (res.reason === 'denied-in-browser') {
                                showPopup('คุณปิดสิทธิแจ้งเตือนในเบราว์เซอร์ไว้ กรุณาเปิดสิทธิการแจ้งเตือนก่อน');
                            } else {
                                showPopup('อุปกรณ์นี้ยังไม่พร้อมสำหรับการแจ้งเตือน');
                            }
                        } else {
                            showPopup();
                        }
                    } else if (Notification.permission === 'granted') {
                        // ให้สิทธิแล้วแต่ยังไม่มี subscription → สมัครเงียบ ๆ
                        const has = await reg.pushManager.getSubscription();
                        if (!has && VAPID_PUBLIC_KEY) {
                            try {
                                await subscribePush();
                                saveState({ decided:'allowed', until:0 });
                            } catch {}
                        }
                    }
                }, SHOW_DELAY_MS);

            } catch (e) {
                console.warn('[PWA] SW register fail:', e);
            }
        })();
    }
</script>

