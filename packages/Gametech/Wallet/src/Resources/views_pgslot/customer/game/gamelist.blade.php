
<div class="game-search-inner">
    <form id="frmseach">
        <div class="input-group">

            <input type="search" id="searchKeyword" name="search" value="" class="form-control border-end-0 border inputstyle" placeholder="ค้นหาชื่อเกม..." data-search />
            <span class="input-group-append">
                    <button class="btn btn-outline-secondary bg-white border-start-0 border-bottom-0 border ms-n5" type="button">
                        <i class="fa fa-search"></i>
                    </button>
                </span>
        </div>
    </form>
</div>
<div class="gameborder">
    <div class="headertab"><h2>{{ $game_name->name }}</h2></div>
    <div class="row m-0 mt-3">
        <div class="gridgame">
            @foreach($games as $i => $item)
                <div class="ingridgame" data-filter-item data-filter-name="{{ strtolower($item->name) }}">
                    <div class="iningridgame newversion">
                        <a href="{{ route('customer.game.redirect', [ 'id' => $id , 'name' => $item->code ,'method' => $item->method ]) }}"
                           data-toggle="modal" data-target="#gametechPopup"
                           target="gametechPopup">
                            <img src="{{ $item->image }}">
                        </a>
                    </div>
                </div>
            @endforeach

        </div>
    </div>
</div>

@push('scripts')
    <script type="text/javascript">
        let windowObjectReference = null; // global variable
        let previousURL; /* global variable that will store the
                    url currently in the secondary window */
        function openRequestedSingleTab(url, windowName) {

            const winHtml = `<!DOCTYPE html><html><head><title>Window with Blob</title></head><body><h1>Hello from the new window!</h1></body></html>`;
            const winUrl = URL.createObjectURL(new Blob([winHtml], {type: "text/html"}));
            const win = window.open(url, windowName, `width=800,height=400,screenX=200,screenY=200`);
        }
        $(document).ready(function () {

            const links = document.querySelectorAll(
                "a[target='gametechPopup']"
            );
            for (const link of links) {
                link.addEventListener(
                    "click",
                    (event) => {

                        Toast.fire({
                            icon: 'info',
                            title: '{{ __('app.game.login') }}'
                        })

                        openRequestedSingleTab(link.href, 'gametechPopup');
                        event.preventDefault();
                    },
                    false
                );
            }

            // console.log(previousURL);

            $('[data-search]').on('input', function () {
                var searchVal = $(this).val();
                var filterItems = $('[data-filter-item]');
                console.
                    log(filterItems);
                if (searchVal != '') {
                    filterItems.addClass('hidden');
                    $('[data-filter-item][data-filter-name*="' + searchVal.toLowerCase() + '"]').removeClass('hidden');
                } else {
                    filterItems.removeClass('hidden');
                }
            });

        });

    </script>
@endpush