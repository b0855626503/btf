@extends('wallet::layouts.master')

{{-- page title --}}
@section('title','')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/old/css/game.css').'?v='.time() }}">
@endpush


@section('content')
    <div class="container">
        <div class="row">
            <div class="col-md-8 offset-md-2 col-sm-12">
                @if($config->seamless == 'Y')
                    <div class="card text-light card-trans">
                        <div class="card-body py-3 px-2">
                            <seamless></seamless>
                        </div>
                    </div>

                    @include('wallet::customer.game.seamless')
                @else
                    @include('wallet::customer.game.single')
                @endif

            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        function copy(id) {
            var copyText = document.getElementById(id);
            var input = document.createElement("textarea");
            input.value = copyText.textContent;
            this.copycontent = copyText.textContent;
            document.body.appendChild(input);
            input.select();
            input.setSelectionRange(0, 99999);
            document.execCommand("copy");
            input.remove();
        }

        // เพิ่มการรองรับสำหรับการแตะบนมือถือ
        document.querySelectorAll('.nav-item .-inner-wrapper img').forEach(img => {
            img.addEventListener('touchstart', function() {
                img.style.transform = 'scale(1.1)';  // ซูมภาพเมื่อแตะ
                img.style.boxShadow = '0 12px 20px rgba(0, 0, 0, 0.3)';  // เพิ่มเงาเมื่อแตะ
            });

            img.addEventListener('touchend', function() {
                img.style.transform = 'scale(1)';  // คืนค่าซูมกลับ
                img.style.boxShadow = 'none';  // ลบเงาเมื่อเลิกแตะ
            });
        });

    </script>


@endpush

