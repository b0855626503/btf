@extends('wallet::layouts.blank')


@section('title','')




@section('content')
    <div class="ring">Loading
        <span></span>
    </div>
@endsection

@push('scripts')

    <script type="text/javascript">
        setTimeout(function () {
            location.href = '{!! $url !!}';
        }, 2000);

    </script>

@endpush
