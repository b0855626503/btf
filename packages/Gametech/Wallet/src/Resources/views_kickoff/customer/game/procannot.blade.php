@extends('wallet::layouts.blank')


@section('title','')




@section('content')
    <div class="ring">Can {{ $cannot }} only
        <span></span>
    </div>

@endsection

@push('scripts')
    <script type="text/javascript">
        // $(document).ready(function () {
            setTimeout(function () {
                window.close();
            }, 5000);
        // });
    </script>
@endpush
