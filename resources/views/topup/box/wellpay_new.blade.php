@extends('wallet::layouts.master')

{{-- page title --}}
@section('title','THAI QR PAYMENT')

@push('styles')
    <style>

        :root {
            --navy: #0f3563; /* เฮดเดอร์ */
            --border: #d7d7d7;
            --label: #888;
            --copy: #173a6b;
            --copy-hover: #0a2b55;
            --danger: #cc1f1a;
            --muted: #6c757d;
            --card-w: 420px;
        }

        * {
            box-sizing: border-box
        }

        body {
            height: auto !important;
            margin: 0;
            background: #f4f6f9;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans Thai", sans-serif;
            color: #222;
        }

        .wrap {
            min-height: 100svh;
            display: grid;
            place-items: center;
            padding: 24px
        }

        .card {
            width: 100%;
            max-width: var(--card-w);
            background: #fff;
            border: 1px solid #cfd4da;
            border-radius: 6px;
            box-shadow: 0 1px 0 rgba(0, 0, 0, .03);
            overflow: hidden
        }

        .card header {
            background: var(--navy);
            padding: 14px 16px;
            text-align: center
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: #fff;
            font-weight: 700;
            letter-spacing: .3px;
            width: max(160px, 40%);
        }

        .brand svg {
            width: 28px;
            height: 28px
        }

        .body {
            padding: 18px 22px
        }

        /*.promptpay-logo{display:grid; background:#0f3563; color:#fff; width: 30%; border-radius:4px; margin:0 auto; font-weight:700}*/

        .rowline {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin: 8px 0
        }

        .rowline .left {
            flex: 1;
            background: #f1f1f1;
            border: 1px solid #e6e6e6;
            border-radius: 4px;
            padding: 10px 12px
        }

        .rowline .title {
            color: #555;
            font-size: .9rem
        }

        .rowline .value {
            font-size: 1.25rem;
            font-weight: 700
        }

        .btn-copy {
            white-space: nowrap;
            padding: 7px 10px;
            border: 1px solid #204a83;
            background: #204a83;
            color: #fff;
            border-radius: 4px;
            font-weight: 700;
            cursor: pointer
        }

        .btn-copy:hover {
            background: var(--copy-hover);
            border-color: var(--copy-hover)
        }

        hr {
            border: none;
            border-top: 1px dashed var(--border);
            margin: 14px 0
        }

        .meta {
            font-size: .92rem;
            color: #333
        }

        .meta .small {
            font-size: .86rem;
            color: #666
        }

        .muted {
            color: #6c757d
        }

        .warn {
            color: var(--danger);
            font-weight: 600;
            margin: 8px 0
        }

        .qrbox {
            display: grid;
            place-items: center;
            margin: 14px 0 8px
        }

        .qrbox img {
            width: 240px;
            height: 240px;
            object-fit: contain;
            image-rendering: pixelated;
            border: 6px solid #e9ecef;
            border-radius: 6px
        }

        .expiry {
            text-align: center;
            color: #666;
            font-size: .95rem;
            margin: 8px 0 4px
        }

        /* footer note */
        .footnote {
            font-size: .84rem;
            color: #555;
            text-align: center;
            padding: 0 10px 6px
        }

        /* จัดกึ่งกลางทั้งแนวตั้งและแนวนอน */
        .qr_payment .qr-box {
            display: grid;
            place-items: center;
            /*border: 2px dashed #ccc;*/
            padding: 6px;
            border-radius: 8px;
            background: #fff;
        }

        /* ขนาด QR แบบยืดหยุ่น แต่ไม่เกิน 300px และอยู่กลางเสมอ */
        .qr_payment .qr-box img,
        .qr_payment .qr-box svg {
            display: block;
            width: clamp(200px, 60vw, 250px); /* 200–300px ตามจอ */
            height: auto;
            margin: 0 auto;
            background: transparent;
            padding: 0;
            border-radius: 4px;
        }

        .qr_payment .qr-box {
            display: grid;
            place-items: center;
            /*border:2px dashed #ccc; */
            padding: 16px 36px; /* เผื่อที่สำหรับป้ายข้าง */
            border-radius: 8px;
            background: #fff;
        }

        .qr_payment .qr-wrap {
            position: relative;
            display: inline-block;
        }

        .qr_payment .qr-box img, .qr-box svg {
            display: block;
            width: clamp(200px, 60vw, 250px);
            height: auto;
            margin: 0 auto;
            background: transparent;
            border-radius: 4px;
        }

        .qr_payment .qr-box {
            /*border: 2px dashed #ccc;*/
            padding: 10px;
            border-radius: 8px;
            /*margin-bottom: 10px;*/
        }

        .qr_payment .qr-box img {
            width: 100%;
            max-width: 200px;
            height: auto;
        }

        /* ปรับ wrap ให้เป็นคอนเทนเนอร์กว้างกลางหน้า */
        .wrap.qr_payment {
            min-height: unset; /* ไม่ต้องเต็มจอเพื่อให้สูงตามเนื้อหา */
            display: block; /* จากเดิม grid + place-items:center */
            padding: 16px;
        }

        .qr-grid {
            max-width: 1120px; /* กรอบรวมบนเดสก์ท็อป */
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr; /* มือถือ: 1 คอลัมน์ */
            gap: 16px;
        }

        /* การ์ดไม่จำกัดความกว้างคงที่อีกต่อไป */
        .card {
            width: 100%;
            max-width: none;
        }

        /* เดสก์ท็อปขึ้นไป: 2 คอลัมน์ */
        @media (min-width: 992px) {
            .qr-grid {
                grid-template-columns: 1fr 1fr; /* ซ้าย/ขวา */
                align-items: start;
                gap: 20px;
            }

            /* การ์ดล่าง (customer) ให้พาด 2 คอลัมน์ */
            .card-span-2 {
                grid-column: 1 / -1;
            }
        }

        /* ปรับโลโก้ PromptPay ให้กว้างอัตโนมัติ ไม่บีบ */
        .promptpay-logo {
            display: grid;
            place-items: center;
            /*background:#0f3563;*/
            color: #fff;
            border-radius: 4px;
            margin: 0 auto;
            /*padding: 6px 10px;*/
            width: max(160px, 40%); /* อย่างน้อย 160px หรือ 40% ของการ์ด */
        }

        .promptpay-logo img {
            max-height: 28px;
        }

        /* กล่อง QR ให้ปรับขนาดตามการ์ด */
        .qrbox img {
            width: min(260px, 80%);
            height: auto;
        }

        /* เส้นแบ่งและตัวหนังสือให้กระชับพื้นที่ */
        hr {
            margin: 12px 0;
        }

        .meta {
            font-size: .95rem;
        }

        /* เดิมมี .qr-grid แล้ว — เพิ่มต่อท้ายได้เลย */
        .qr-grid {
            max-width: 1120px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr;
            gap: 16px;
        }

        /* คอลัมน์ขวา (ห่อการ์ด 2 ใบทางขวา) */
        .right-col {
            display: grid;
            gap: 20px;
        }

        @media (min-width: 992px) {
            .qr-grid {
                grid-template-columns: 1fr 1fr; /* ซ้าย QR, ขวา รายละเอียด+ลูกค้า */
                align-items: start;
                gap: 20px;
            }

            .right-col {
                grid-column: 2; /* ให้คอลัมน์ขวาไปอยู่ช่องขวา */
            }
        }
        .qr_payment .expiration {
            font-size: 13px;
            color: #0236b8;
            margin-bottom: 10px;
        }

    </style>
@endpush


@php
    use Carbon\Carbon;
    use Illuminate\Support\Str;
    use SimpleSoftwareIO\QrCode\Facades\QrCode;

    function detectQrType($input)
    {
        if (Str::startsWith($input, 'data:image')) {
            return 'base64_img';
        }

        if (Str::startsWith($input, ['http://', 'https://', 'line://'])) {
            return 'url';
        }

        if (Str::startsWith($input, '000201')) {
            return 'promptpay_emv';
        }

        if (isBase64($input)) {
            return 'raw_base64';
        }

        return 'text';
    }

    function isBase64($str)
    {
        $decoded = base64_decode($str, true);
        return $decoded && base64_encode($decoded) === $str;
    }
@endphp

@section('content')

    <div class="wrap qr_payment">
        <div class="qr-grid">

            {{-- คอลัมน์ซ้าย: QR --}}
            <article class="card" role="region" aria-label="Thai QR Payment">
                <header>
                    <div class="brand" aria-label="THAI QR PAYMENT">
                        <img class="img-fluid" src="{{ url('images/logo/logo_promptpay.png') }}">
                    </div>
                </header>

                <div class="body">
                    <div class="promptpay-logo justify-content-center">
                        <img class="img-fluid" src="{{ url('images/logo/promptpay.png') }}">
                    </div>

                    @php $type = detectQrType($data['qrcode']); @endphp
                    <div class="qr-box">
                        <div class="qr-wrap">
                            @php $type = detectQrType($data['qrcode']); @endphp

                            @if ($type === 'base64_img')
                                <img id="qrcode" src="{{ $data['qrcode'] }}" alt="QR Image">
                            @elseif ($type === 'raw_base64')
                                <img id="qrcode" src="data:image/png;base64,{{ $data['qrcode'] }}" alt="QR Image">
                            @elseif ($type === 'promptpay_emv')
                                <div id="qr-svg" class="qr-svg-wrap">
                                    {!! QrCode::format('svg')->errorCorrection('H')->size(240)->margin(4)->generate($data['qrcode']) !!}
                                </div>
                            @elseif ($type === 'url')
                                <a href="{{ $data['qrcode'] }}" target="_blank">{{ $data['qrcode'] }}</a>
                            @else
                                <pre>{{ $data['qrcode'] }}</pre>
                            @endif
                        </div>
                    </div>
                    <div class="text-center">พร้อมเพย์ : <span> {{ $data['promptpayNumber'] }} </span></div>
                    <div class="expiration text-center">{{ __('app.qrscan.expire') }} : <span
                                id="countdown">--:--</span></div>
                </div>
            </article>

            {{-- คอลัมน์ขวา: รวม "รายละเอียด" + "ลูกค้า" ซ้อนกัน --}}
            <div class="right-col">

                {{-- รายละเอียด / คำเตือน --}}
                <article class="card" role="region" aria-label="Detail">
                    @php
                        $date1 = Carbon::parse($data['date_create']);
                        $date2 = Carbon::parse($data['expired_date']);
                        $minutes = $date1->diffInMinutes($date2);

                        $discount = false;
                        if ($data['payamount'] < $data['amount']){
                            $diff = $data['amount'] - $data['payamount'];
                            $discount = true;
                        }

                    @endphp

                    <div class="body">
                        <div class="meta">
                            <div>เวลาสั่งซื้อ <span id="orderTime" class="small">{{ $data['date_create'] }}</span></div>
                            <div>เลขใบสั่งซื้อ <span id="orderTime" class="small">{{ $data['txid'] }}</span></div>
                            <div class="small" style="margin-top:6px; color:#dc2626; font-weight:600">
                                กรุณาชำระเงินให้เสร็จสิ้นภายใน <span id="limitMin">{{ $minutes }}</span> นาที
                            </div>

                            @if($discount)
                                <div class="discount">
                                    <div class="small" style="margin-top:10px">ยินดีด้วย! ลูกค้าได้รับส่วนลด</div>
                                    <div class="small">ลูกค้าต้องชำระเพียง <strong>{{ $data['payamount'] }}</strong> บาทค่ะ</div>
                                    <div class="small">เป็นส่วนลดพิเศษ <strong>{{ $diff }}</strong> บาท !!</div>
                                </div>
                            @else
                                <div class="normal">
                                    {{-- แก้ tag strong ที่หาย > --}}
                                    <div class="small">ยอดที่ลูกค้าต้องชำระ <strong>{{ $data['payamount'] }}</strong> บาทค่ะ
                                    </div>
                                </div>
                            @endif
                        </div>

                        <p class="warn">สำคัญ!! กรุณาอย่าสแกน QR Code ซ้ำ เพราะระบบจะไม่ทำการคืนเงินให้ท่านได้</p>
                    </div>
                </article>

                {{-- ข้อมูลบัญชีลูกค้า --}}
                <article class="card" role="region" aria-label="customer">
                    <div class="body">
                        <div class="meta">
                            <div class="info" style="padding: 10px; border-radius: 8px; margin-bottom: 10px;">
                                <strong>📌 {{ __('app.qrscan.use_account') }}:</strong>
                                <br>{{ __('app.qrscan.acc_name') }}: <strong>{{ $member->name }}</strong>
                                <br>{{ __('app.qrscan.acc_bank') }}: <strong>{{ $member->bank->name_th }}</strong>
                                <br>{{ __('app.qrscan.acc_no') }}: <strong>{{ $member->acc_no }}</strong>
                            </div>
                        </div>
                    </div>
                </article>

            </div> {{-- /.right-col --}}

        </div> {{-- /.qr-grid --}}

    </div>

@endsection


@push('scripts')

    <script>

        const createdAt = new Date("{{ Carbon::parse($data['date_create'])->toIso8601String() }}");
        const expireAt = new Date("{{ Carbon::parse($data['expired_date'])->toIso8601String() }}");


        const countdown = document.getElementById('countdown');

        let timer;

        function expireAction() {
            fetch("{{ route('api.wellpay.deposit.expire', ['txid' => $data['detail']]) }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': "{{ csrf_token() }}"
                },
                body: JSON.stringify({})
            }).then(() => {

//                 document.querySelector('.qr-box').style.display = 'none';
//                 document.getElementById('downloadBtn').style.display = 'none';
//
//                 const expirationDiv = document.querySelector('.expiration');
//                 expirationDiv.innerHTML = `
//     <div id="payment-success" style="
//         opacity: 0;
//         transform: translateY(-10px);
//         background-color: #e6f9ec;
//         border: 2px solid #2ecc71;
//         color: #2e7d32;
//         font-weight: bold;
//         font-size: 16px;
//         padding: 12px 20px;
//         border-radius: 10px;
//         display: inline-block;
//         box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
//         transition: all 0.5s ease;
//     ">
//         ✅ รายการนี้หมดอายุ การใช้งานแล้ว โปรดอย่า่ สแกน QR ของรายการนี้้
//     </div>
// `;
//
// // trigger fade-in
//                 setTimeout(() => {
//                     const el = document.getElementById('payment-success');
//                     el.style.opacity = '1';
//                     el.style.transform = 'translateY(0)';
//                 }, 50);


                {{--if (window.opener) {--}}
                {{--    window.close();--}}
                {{--} else {--}}
                {{--    window.location.href = "{{ route('customer.home.index') }}";--}}
                {{--}--}}
            });
        }

        function updateCountdown() {
            const now = new Date();
            const diff = expireAt - now;

            if (diff <= 0) {
                countdown.textContent = '00:00';
                clearInterval(timer);
                expireAction();
                return;
            }

            const minutes = String(Math.floor(diff / 1000 / 60)).padStart(2, '0');
            const seconds = String(Math.floor((diff / 1000) % 60)).padStart(2, '0');
            countdown.textContent = `${minutes}:${seconds}`;
        }

        const now = new Date();
        if (now >= expireAt) {
            // หากเข้ามาหน้านี้ตอนหมดเวลาแล้ว ให้ทำทันที
            countdown.textContent = '00:00';
            expireAction();
        } else {
            updateCountdown();
            timer = setInterval(updateCountdown, 1000);
        }
    </script>

    <script>
        function trans(key, replace = {}) {
            var translation = key.split('.').reduce((t, i) => t[i] || null, window.i18n);

            for (var placeholder in replace) {
                translation = translation.replace(`:${placeholder}`, replace[placeholder]);
            }
            return translation;
        }

    </script>
{{--    <script>--}}
{{--        document.getElementById('downloadBtn').addEventListener('click', async function () {--}}
{{--            const type = "{{ $type }}";--}}
{{--            const canvas = document.getElementById('qrCanvas');--}}
{{--            const ctx = canvas.getContext('2d', {willReadFrequently: true});--}}

{{--            // เตรียมข้อความประกอบไฟล์--}}
{{--            const amount = "฿{{ number_format($data['payamount'], 2) }}";--}}
{{--            const created = "{{ $data['date_create'] }}";--}}
{{--            const orderId = "{{ $data['detail'] }}";--}}
{{--            const txid = "{{ $data['txid'] }}";--}}
{{--            const siteName = "{{ request()->getHost() }}";--}}
{{--            const accountName = "{{ $member->name }}";--}}
{{--            const bankName = "{{ $member->bank->name_th }}";--}}
{{--            const accountNo = "{{ $member->acc_no }}";--}}
{{--            const expireTime = new Date("{{ \Carbon\Carbon::parse($data['expired_date'])->toIso8601String() }}")--}}
{{--                .toLocaleTimeString('th-TH', {hour: '2-digit', minute: '2-digit', second: '2-digit'});--}}
{{--            const expireText = '{{ __("app.qrscan.expire") }} ' + expireTime;--}}

{{--            // สร้างรูป QR สำหรับวาดบน canvas--}}
{{--            function loadImageForCanvas() {--}}
{{--                return new Promise((resolve, reject) => {--}}
{{--                    const img = new Image();--}}
{{--                    img.onload = () => resolve(img);--}}
{{--                    img.onerror = reject;--}}

{{--                    if (type === 'base64_img') {--}}
{{--                        img.src = "{{ $data['qrcode'] }}";--}}
{{--                    } else if (type === 'raw_base64') {--}}
{{--                        img.src = 'data:image/png;base64,{{ $data["qrcode"] }}';--}}
{{--                    } else if (type === 'promptpay_emv') {--}}
{{--                        // ดึง SVG ที่แสดงอยู่ แล้วทำเป็น blob/data URL--}}
{{--                        const svgEl = document.querySelector('#qr-svg svg');--}}
{{--                        const svgData = new XMLSerializer().serializeToString(svgEl);--}}
{{--                        const svgBlob = new Blob([svgData], {type: 'image/svg+xml;charset=utf-8'});--}}
{{--                        const url = URL.createObjectURL(svgBlob);--}}
{{--                        img.onload = () => {--}}
{{--                            URL.revokeObjectURL(url);--}}
{{--                            resolve(img);--}}
{{--                        };--}}
{{--                        img.src = url;--}}
{{--                    } else {--}}
{{--                        reject(new Error('Unsupported QR type for download.'));--}}
{{--                    }--}}
{{--                });--}}
{{--            }--}}

{{--            try {--}}
{{--                const qrImg = await loadImageForCanvas();--}}

{{--                // ขนาดเอาท์พุตสูงขึ้นเพื่อความคม (ไม่เบลอ)--}}
{{--                const qrTarget = 300; // px ของ QR ในไฟล์ที่โหลด--}}
{{--                const sidePadding = 100;--}}
{{--                const lineHeight = 28;--}}
{{--                const lines = [--}}
{{--                    siteName.toUpperCase(),--}}
{{--                    `${amount} - {{ $data['date_create'] }}`,--}}
{{--                    expireText,--}}
{{--                    '{{ __("app.qrscan.orderid") }}: ' + orderId,--}}
{{--                    '{{ __("app.qrscan.txtid") }}: ' + txid,--}}
{{--                    '⚠ {{ __("app.qrscan.warning_detail") }}',--}}
{{--                    '{{ __("app.qrscan.use_account") }}',--}}
{{--                    '{{ __("app.qrscan.acc_name") }}: ' + accountName,--}}
{{--                    '{{ __("app.qrscan.acc_bank") }}: ' + bankName,--}}
{{--                    '{{ __("app.qrscan.acc_no") }}: ' + accountNo--}}
{{--                ];--}}

{{--                const width = qrTarget + sidePadding * 2;--}}
{{--                const height = qrTarget + (lines.length * lineHeight) + 70;--}}

{{--                canvas.width = width;--}}
{{--                canvas.height = height;--}}

{{--                // วาดพื้นหลังขาว--}}
{{--                ctx.fillStyle = '#fff';--}}
{{--                ctx.fillRect(0, 0, width, height);--}}

{{--                // ปิดการเบลอพิกเซล--}}
{{--                ctx.imageSmoothingEnabled = false;--}}

{{--                // หัวข้อเว็บ--}}
{{--                ctx.fillStyle = '#222';--}}
{{--                ctx.font = 'bold 18px Tahoma, sans-serif';--}}
{{--                ctx.textAlign = 'center';--}}
{{--                ctx.fillText(siteName.toUpperCase(), width / 2, 32);--}}

{{--                // วาด QR ตรงกลาง--}}
{{--                const qrX = (width - qrTarget) / 2;--}}
{{--                const qrY = 50;--}}
{{--                ctx.drawImage(qrImg, qrX, qrY, qrTarget, qrTarget);--}}

{{--                // ข้อความประกอบ--}}
{{--                ctx.font = '14px Tahoma, sans-serif';--}}
{{--                ctx.fillStyle = '#333';--}}
{{--                let y = qrY + qrTarget + 24;--}}
{{--                for (let i = 1; i < lines.length; i++) {--}}
{{--                    ctx.fillText(lines[i], width / 2, y);--}}
{{--                    y += lineHeight;--}}
{{--                }--}}

{{--                // ดาวน์โหลด--}}
{{--                const url = canvas.toDataURL('image/png');--}}
{{--                const a = document.createElement('a');--}}
{{--                a.href = url;--}}
{{--                a.download = `qrcode_{{ number_format($data['payamount'], 2) }}_{{ \Illuminate\Support\Str::slug($data['date_create']) }}.png`;--}}
{{--                a.click();--}}
{{--            } catch (e) {--}}
{{--                alert('ไม่สามารถสร้างไฟล์ดาวน์โหลดได้: ' + e.message);--}}
{{--            }--}}
{{--        });--}}
{{--    </script>--}}

        <script>
            const txid = "{{ $data['detail'] }}";
            const interval = setInterval(async () => {
                try {
                    const res = await fetch("{{ route('api.wellpay.deposit.status', ['txid' => $data['detail']]) }}");
                    const data = await res.json();


                    if (data.success && data.status !== 'pending') {
                        clearInterval(interval);
                        document.querySelector('.qr-box').style.display = 'none';
                        document.getElementById('downloadBtn').style.display = 'none';

                        const expirationDiv = document.querySelector('.expiration');

                        let message = '';
                        switch (data.status) {
                            case 'completed':
                                message = '✅ รายการได้รับการชำระเรียบร้อยแล้ว โปรดตรวจสอบยอดเครดิตของท่าน';
                                break;
                            case 'expired':
                                message = '✅ รายการนี้หมดอายุการใช้งานแล้ว โปรดอย่า่สแกน QR ของรายการนี้';
                                break;
                            case 'CANCEL':
                                message = '✅ รายการนี้ถูกยกเลิกแล้ว';
                                break;
                            default:
                                message = '⚠️ สถานะไม่ทราบแน่ชัด โปรดติดต่อเจ้าหน้าที่';
                        }

                        expirationDiv.innerHTML = `
                    <div id="payment-success" style="
                        opacity: 0;
                        transform: translateY(-10px);
                        background-color: #e6f9ec;
                        border: 2px solid #2ecc71;
                        color: #2e7d32;
                        font-weight: bold;
                        font-size: 16px;
                        padding: 12px 20px;
                        border-radius: 10px;
                        display: inline-block;
                        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
                        transition: all 0.5s ease;
                    ">
                        ${message}
                    </div>
                `;

                        setTimeout(() => {
                            const el = document.getElementById('payment-success');
                            el.style.opacity = '1';
                            el.style.transform = 'translateY(0)';
                        }, 50);


                    }


                } catch (e) {
                    console.warn('เช็คสถานะไม่สำเร็จ:', e);
                }
            }, 3000);
    	</script>
@endpush
