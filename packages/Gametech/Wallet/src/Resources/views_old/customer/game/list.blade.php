@extends('wallet::layouts.master')

{{-- page title --}}
@section('title','')

@section('back')
    <a class="nav-link p-2 text-light mx-auto hand-point" href="{{ route('customer.home.index') }}">
        <i class="fas fa-chevron-left"></i> กลับ</a>
@endsection

@push('styles')
    <style>
        .x-search-component {
            position: relative;
            width: 100%; /* ให้ช่องค้นหามีความกว้างเต็มพื้นที่ */
        }

        .x-form-control {
            padding-right: 40px; /* เพิ่มพื้นที่ด้านขวาสำหรับปุ่ม X */
            width: 100%; /* ความกว้างของช่องค้นหา */
            padding-left: 10px; /* เพิ่มช่องว่างทางซ้ายสำหรับข้อความ */
            box-sizing: border-box; /* รวม padding กับความกว้างทั้งหมด */
        }

        .search-icon {
            position: absolute;
            right: 40px; /* ไอคอนค้นหาจะอยู่ขวาสุด */
            top: 50%;
            transform: translateY(-50%);
            font-size: 18px;
            color: #aaa;
        }

        .clear-btn {
            position: absolute;
            right: 10px; /* ปุ่ม X อยู่ขวามือสุด */
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            font-size: 20px;
            color: #aaa;
            cursor: pointer;
            opacity: 1; /* ปุ่ม X จะไม่ซ่อน */
            z-index: 10; /* ให้ปุ่ม X อยู่บนสุด */
        }

        .card-body .row .col-6 img {
            border-radius: 10px; /* มุมโค้งมน */
            transition: transform 0.3s ease, box-shadow 0.3s ease; /* เอฟเฟกต์การซูมและเงา */
        }

        .card-body .row .col-6:hover img {
            transform: scale(1.1); /* ซูมภาพเมื่อเมาส์วาง */
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3); /* เพิ่มเงาให้กับภาพ */
        }

    </style>
@endpush

@section('content')
    <div class="container">
        <div class="row">
            <div class="col-md-8 offset-md-2 col-sm-12">
                <div class="card text-light card-trans">
                    <div class="card-body py-3 px-2">
                        @if($config->seamless == 'Y')
                            <seamless></seamless>
                        @else
                            <credit></credit>
                        @endif
                    </div>
                </div>

                <div class="card text-light card-trans mt-5">
                    <div class="card-header">

                        <div class="input-group x-search-component -v2">
                            <input type="text" id="searchKeyword" name="search" value=""
                                   class="x-form-control form-control -form-search-input"
                                   placeholder="ค้นหาชื่อเกม..." data-search/>
                            <label for="searchKeyword" class="input-group-text search-icon">
                                <i class="fa fa-search fw-bolder"></i>
                            </label>
                            <button id="clearButton" class="clear-btn" onclick="clearSearch()">×</button>
                        </div>



                    </div>
                    <div class="card-body py-3 px-2">
                        <div class="row">
                            @foreach($games as $i => $item)
                                <div class="col-6 mb-3 col-md-4" data-filter-item
                                     data-filter-name="{{ strtolower($item->name) }}">

                                    <a href="{{ route('customer.game.redirect', [ 'id' => $id , 'name' => $item->code ,'method' => $item->method ]) }}"
                                       data-toggle="modal" data-target="#gametechPopup"
                                       target="gametechPopup"
                                       class="game-link"
                                       data-game-id="{{ $item->code }}"
                                       data-game-name="{{ $item->name }}"
                                       data-game-image="{{ $item->image }}"
                                       data-game-url="{{ route('customer.game.redirect', [ 'id' => $id , 'name' => $item->code ,'method' => $item->method ]) }}"
                                    >
                                        <img
                                                loading="lazy"
                                                src="{{ $item->image }}"
                                                data-src="{{ $item->image }}"
                                                class="d-block mx-auto img-fluid img-full"
                                        />

                                        <p class="text-main text-center mb-0 cut-text small">{{ $item->name }}</p>
                                        <p class="mb-0"></p>

                                    </a>
                                </div>
                                {{--                                <gameseamless-list :product="{{ json_encode($item) }}" ref="{{$i}}"--}}
                                {{--                                                   product_id="{{ $id }}" number="{{$i}}"></gameseamless-list>--}}
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    {{--    <window-portal ref="gamepopup" id="gamepopup"></window-portal>--}}
@endsection


@push('scripts')
    {{--    <script src="https://code.jquery.com/jquery-3.7.1.js" integrity="sha256-eKhayi8LEQwp4NKxN+CfCh+3qOVUtJn3QNZ0TciWLP4="--}}
    {{--            crossorigin="anonymous"></script>--}}
    {{--    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"--}}
    {{--            integrity="sha384-Fy6S3B9q64WdZWQUiU+q4/2Lc9npb8tCaSX9FK7E8HnRr0Jz8D6OP9dO5Vg3Q9ct"--}}
    {{--            crossorigin="anonymous"></script>--}}
    {{--    <script src="{{ asset('js/mdetect.js?v=1') }}"></script>--}}
    <script type="text/javascript">

        function clearSearch() {
            document.getElementById("searchKeyword").value = ''; // เคลียร์ข้อความที่พิมพ์
            document.getElementById("searchKeyword").focus(); // ใส่ focus ให้กับช่องค้นหา
        }
        function isMobile() {
            return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        }

        function addRecentGame(game) {
            let recentGames = JSON.parse(localStorage.getItem('recentGames')) || [];

            // ตรวจสอบว่าเกมนี้เคยมีในรายการแล้วหรือยัง
            if (!recentGames.some(item => item.id === game.id)) {
                recentGames.unshift(game); // เพิ่มเกมใหม่เข้าไปที่ตำแหน่งแรก

                // ถ้าเก็บไว้เกิน 5 เกม ก็ลบเกมเก่าที่สุดออก
                if (recentGames.length > 5) {
                    recentGames.pop();
                }

                // บันทึกข้อมูลใหม่ลงใน localStorage
                localStorage.setItem('recentGames', JSON.stringify(recentGames));
                console.log('record to recent games:', game);
                // displayRecentGames(); // เรียกใช้ฟังก์ชันเพื่อแสดงเกมล่าสุด
            }
        }

        // เมื่อคลิกเล่นเกม
        document.querySelectorAll('.game-link').forEach(link => {
            link.addEventListener('click', function (e) {
                // ดึงข้อมูลเกมจาก data-* attributes
                const game = {
                    id: this.getAttribute('data-game-id'),
                    name: this.getAttribute('data-game-name'),
                    image: this.getAttribute('data-game-image'),
                    url: this.getAttribute('data-game-url')
                };

                // เก็บข้อมูลเกมที่ผู้ใช้กดเล่นใน localStorage
                addRecentGame(game);
                console.log('Added to recent games:', game);
            });
        });



        $(document).on('click', "a[target='gametechPopup']", function (e) {
            e.preventDefault();

            // ใช้ this.href แทน link.href
            const url = this.href;


            if (isMobile()) {
                // แสดง Toast บนมือถือ
                window.Toast?.fire({
                    icon: 'success',
                    title: '{{ __("app.game.login_complete") }}'
                });

                // รอ 500ms แล้ว redirect
                setTimeout(() => {
                    window.location.href = url;
                }, 500);

            } else {
                // เปิด popup บนเดสก์ท็อป
                const w = 800, h = 400;
                const left = (screen.width - w) / 2;
                const top = (screen.height - h) / 2;

                const newWindow = window.open(
                    url,
                    'gametechPopup',
                    `width=${w},height=${h},left=${left},top=${top}`
                );

                if (!newWindow) {
                    // ถ้า popup ถูกบล็อก ให้ไปหน้าเดิมแทน
                    window.location.href = url;

                    window.Toast?.fire({
                        icon: 'error',
                        title: 'Popup ถูกบล็อก กรุณาอนุญาต popup ในเบราว์เซอร์ของคุณ'
                    });
                } else {
                    window.Toast?.fire({
                        icon: 'success',
                        title: '{{ __("app.game.login_complete") }}'
                    });
                }
            }
        });


        $(document).ready(function () {


            // console.log(previousURL);

            $('[data-search]').on('keyup', function () {
                var searchVal = $(this).val();
                var filterItems = $('[data-filter-item]');

                if (searchVal != '') {
                    filterItems.addClass('hidden');
                    $('[data-filter-item][data-filter-name*="' + searchVal.toLowerCase() + '"]').removeClass('hidden');
                } else {
                    filterItems.removeClass('hidden');
                }
            });
        });

        @if($refill)
        $(document).ready(function () {

            Swal.fire({
                title: "{{ __('app.promotion.can') }}",
                html: "{{ __('app.promotion.word') }} {{ $refill->value }} {!!  __('app.promotion.word2') !!}",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: '{{ __('app.promotion.yes') }}',
                cancelButtonText: '{{ __('app.promotion.no') }}',
                allowOutsideClick: false,
                allowEscapeKey: false,
                allowEnterKey: false
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '{{ route('customer.promotion.index') }}';
                } else {
                    axios.post(`{{ route('customer.promotion.cancel') }}`).then(response => {
                        if (response.data.success) {
                            Toast.fire({
                                icon: 'warning',
                                title: '{{ __('app.promotion.no2') }}'
                            })
                        }
                    }).catch(err => [err]);

                }
            })

        });
        @endif
    </script>
@endpush

