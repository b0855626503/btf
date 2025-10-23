@extends('wallet::layouts.master')

{{-- page title --}}
@section('title','')

@push('styles')
    <style>
        #recent-games {
            display: flex;
            flex-wrap: nowrap;
            overflow-x: auto;
            padding: 10px 0;
        }

        .recent-game-item {
            position: relative;
            margin-right: 15px;
            width: 120px;
            height: 150px;
            border-radius: 10px;
            overflow: hidden;
            cursor: pointer; /* เพิ่มการเปลี่ยนเคอร์เซอร์เมื่อวางเมาส์ */
        }

        .recent-game-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 10px;
        }

        .recent-game-item .game-label {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0, 0, 0, 0.6);
            color: #fff;
            padding: 5px;
            text-align: center;
            font-size: 14px;
            border-bottom-left-radius: 10px;
            border-bottom-right-radius: 10px;
        }

    </style>
@endpush

@push('scripts')
    <script>
        function isMobile() {
            return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        }

        function displayRecentGames() {
            let recentGames = JSON.parse(localStorage.getItem('recentGames')) || [];
            let recentGamesContainer = document.getElementById('recent-games');

            // เคลียร์การแสดงผลเก่า
            recentGamesContainer.innerHTML = '';

            recentGames.forEach(game => {
                const gameItem = document.createElement('div');
                gameItem.classList.add('recent-game-item');
                gameItem.innerHTML = `
            <img src="${game.image}" alt="${game.name}">
            <div class="game-label">${game.name}</div>
        `;

                // เพิ่ม event listener เมื่อคลิกเกมในรายการ
                gameItem.addEventListener('click', function() {

                    if (isMobile()) {
                        // แสดง Toast บนมือถือ
                        window.Toast?.fire({
                            icon: 'success',
                            title: '{{ __("app.game.login_complete") }}'
                        });

                        // รอ 500ms แล้ว redirect
                        setTimeout(() => {
                            window.location.href = game.url;
                        }, 500);

                    } else {
                        // เปิด popup บนเดสก์ท็อป
                        const w = 800, h = 400;
                        const left = (screen.width - w) / 2;
                        const top = (screen.height - h) / 2;

                        const newWindow = window.open(
                            game.url,
                            'gametechPopup',
                            `width=${w},height=${h},left=${left},top=${top}`
                        );

                        if (!newWindow) {
                            // ถ้า popup ถูกบล็อก ให้ไปหน้าเดิมแทน
                            window.location.href = game.url;

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
                    // window.location.href = game.url; // เปลี่ยน URL ไปยังหน้าของเกมนั้น ๆ
                });

                recentGamesContainer.appendChild(gameItem);
            });
        }

        // เรียกฟังก์ชันแสดงเกมล่าสุดเมื่อหน้าโหลด
        document.addEventListener('DOMContentLoaded', function () {
            displayRecentGames();
        });


    </script>

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
                            @if($config->multigame_open == 'Y')
                                <wallet></wallet>
                            @else
                                <credit></credit>
                            @endif
                        @endif
                    </div>
                </div>

                @if($config->seamless == 'Y')
                    @include('wallet::customer.home.seamless')
                @else
                    @if($config->multigame_open == 'Y')
                        @include('wallet::customer.home.multi')
                    @else
                        @include('wallet::customer.home.single')
                    @endif
                @endif
            </div>
        </div>
    </div>

@endsection
