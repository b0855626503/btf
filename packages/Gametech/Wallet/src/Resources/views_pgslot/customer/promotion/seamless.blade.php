<div class="containslide">
    <div class="swiper prosw">
        <div class="swiper-wrapper">
            @foreach($promotions as $i => $item)
            <div class="swiper-slide">
                <img src="{{  Storage::url('promotion_img/'.$item->filepic)  }}">
                @if($pro_limit > 0)
                <button data-id="{{ $item->code }}" class="getpro">{{ __('app.promotion.choose') }}</button>
                @else
                    <button disabled style="opacity:0.4;">{{ __('app.promotion.choose') }}</button>
                @endif
                <button data-toggle="modal" data-target="#promodaldetail{{ $i }}">{{ __('app.promotion.detail') }}</button>
            </div>
            @endforeach
                @foreach($pro_contents as $i => $item)
                    <div class="swiper-slide">
                        <img src="{{  Storage::url('promotion_img/'.$item->filepic)  }}">
                        <button disabled style="opacity:0;">{{ __('app.promotion.choose') }}</button>
                        <button data-toggle="modal" data-target="#promodaldetails{{ $i }}">{{ __('app.promotion.detail') }}</button>
                    </div>
                @endforeach

        </div>
    </div>
    <div class="btnslide">
        <button class="btnleftslide"><i class="fad fa-caret-left"></i></button>
        <button class="btnrightslide"><i class="fad fa-caret-right"></i></button>
    </div>
</div>

@foreach($promotions as $i => $item)
    <div class="modal fade" id="promodaldetail{{ $i }}" tabindex="-1" aria-labelledby="promodaldetailLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content modalcontent">
                <div class="modal-header headmodalcontent">
                    <h5 class="modal-title" id="promodaldetailLabel">{{ $item->name_th }}</h5>
                    <i class="fas fa-times" data-dismiss="modal" aria-label="Close"></i>
                </div>
                <div class="modal-body angpaocontent">
                    {!! $item->content !!}
                </div>
                <div class="modal-footer footermodalcontent">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">ปิด</button>
                </div>
            </div>
        </div>
    </div>
@endforeach
@foreach($pro_contents as $i => $item)
    <div class="modal fade" id="promodaldetails{{ $i }}" tabindex="-1" aria-labelledby="promodaldetailLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content modalcontent">
                <div class="modal-header headmodalcontent">
                    <h5 class="modal-title" id="promodaldetailLabel">{{ $item->name_th }}</h5>
                    <i class="fas fa-times" data-dismiss="modal" aria-label="Close"></i>
                </div>
                <div class="modal-body angpaocontent">
                    {!! $item->content !!}
                </div>
                <div class="modal-footer footermodalcontent">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">ปิด</button>
                </div>
            </div>
        </div>
    </div>
@endforeach
