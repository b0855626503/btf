<?php

namespace Gametech\Admin\DataTables;


use App\Http\Resources\Seamless;
use Carbon\Carbon;
use Gametech\Admin\Transformers\GameSeamlessTransformer;
use Gametech\Admin\Transformers\PGSlotTransformer;
use Gametech\Admin\Transformers\RpLogCashbackTransformer;
use Gametech\Admin\Transformers\SeamlessTransformer;
use Gametech\API\Models\PGSoft;
use Gametech\API\Models\PGSoftProxy;
use Gametech\Member\Contracts\MemberFreeCredit;
use Gametech\Payment\Contracts\WithdrawFree;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\CollectionDataTable;

//use Yajra\DataTables\DataTableAbstract;
use Yajra\DataTables\DataTables;
use Yajra\DataTables\DataTableAbstract;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder;
use Yajra\DataTables\Services\DataTable;
class SeamlessDataTable extends DataTable
{
    /**
     * Build DataTable class.
     *
     * @param mixed $query Results from query() method.
     * @return DataTableAbstract
     */
    public function dataTable($query)
    {

//        $dataTable = new EloquentDataTable($query);
        $dataTable = new CollectionDataTable($query);

        return $dataTable
            ->with('stake', function () use ($query) {
                return core()->currency((clone $query)->sum('stake'));
            })
            ->with('payout', function () use ($query) {
                return core()->currency((clone $query)->sum('payout'));
            })
            ->setTransformer(new SeamlessTransformer);

//        $datatables = app('datatables');
//        $no = 0;
////        $dataTable = new MongodbDataTable();
////        dd($dataTable->toJson());
////        return $dataTable->setTotalRecords(10);
//        $data = new Collection($query);
////        $resource = Seamless::collection($data);
////        dd($resource);
////        return DataTabless::collection($data);
//        return Datatables::of($data);
//            ->setTransformer(function ($item) use ($no) {
//            return [
//                'code' => ++$no,
//                'username' => $item->username,
//                'betStatus' => $item->betStatus,
//                'gameName' => $item->gameName,
//                'stake' => $item->stake,
//                'payoutStatus' => $item->payoutStatus,
//                'payout' => $item->payout,
//                'updatedDate' => core()->formatDate($item->updatedDate,'Y-m-d H:i:s'),
//            ];
//        });
//        return json_encode($query);

    }

//    public function ajax()
//    {
//        return $this->datatables
//            ->eloquent($this->query())
//            ->make(true);
//    }



    public function query()
    {
        $productId = request()->input('productId');
        $startdate = request()->input('startDate');
        $enddate = request()->input('endDate');

        if (empty($startdate)) {
            $startdate = now()->toDateString() . ' 00:00:00';
        }
        if (empty($enddate)) {
            $enddate = now()->toDateString() . ' 23:59:59';
        }

//        $data = [
//            'username' => 'boattester',
//            'productId' => $productId,
//            'startTime' => $startdate,
//            'endTime' => $enddate,
//            'offset' => 0,
//            'limit' => 50,
//        ];

        $data = [
            'productId' => $productId,
            'startTime' => Carbon::parse($startdate)->setTimezone('UTC')->toIso8601String(),
            'endTime' =>  Carbon::parse($enddate)->setTimezone('UTC')->toIso8601String(),
            'nextId' => ''
        ];

//        dd($data);

        $lists = app('Gametech\Game\Repositories\GameUserRepository')->GameLog($data);
//        dd($lists);
//        $users = PGSoft::query();

        return collect($lists['data']);


    }

    /**
     * Optional method if you want to use html builder.
     *
     * @return Builder
     */
    public function html()
    {
        return $this->builder()
            ->columns($this->getColumns())
            ->ajaxWithForm('', '#frmsearch')
            ->parameters([
                'dom' => 'Bfrtip',
                'processing' => true,
                'serverSide' => true,
                'responsive' => false,
                'stateSave' => true,
                'scrollX' => true,
                'paging' => true,
                'searching' => true,
                'deferRender' => true,
                'retrieve' => true,
                'ordering' => false,
                'autoWidth' => false,
                'pageLength' => 300,
                'order' => [[0, 'desc']],
                'lengthMenu' => [
                    [50, 100, 200, 500, 1000,5000],
                    ['50 rows', '100 rows', '200 rows', '500 rows', '1000 rows', '5000 rows']
                ],
                'buttons' => [
                    'pageLength'
                ],
                'columnDefs' => [
                    ['targets' => '_all', 'className' => 'text-nowrap']
                ],
                'footerCallback' => "function (row, data, start, end, display) {
                           var api = this.api();

                           var intVal = function ( i ) {
                                return typeof i === 'string' ?
                                    i.replace(/[\$,]/g, '')*1 :
                                    typeof i === 'number' ?
                                        i : 0;
                            };
                           api.columns().every(function (i) {
                            if(i == 3 || i == 5){
                           var sum = this.data()
                                      .reduce(function(a, b) {
                                        var x = intVal(a) || 0;
                                        var y = intVal(b) || 0;
                                        return x + y;
                                      }, 0);

                                    var n = new Number(sum);
                                    var myObj = {
                                        style: 'decimal'
                                    };

                                $(this.footer()).html(n.toLocaleString(myObj));
                                }
                            });
                        }",
            ]);
    }

    /**
     * Get columns.
     *
     * @return array
     */
    protected function getColumns()
    {
        return [
//            ['data' => 'code', 'name' => 'code', 'title' => '#', 'orderable' => true, 'searchable' => false, 'className' => 'text-center text-nowrap'],
            ['data' => 'username', 'name' => 'username', 'title' => 'Username', 'orderable' => false, 'searchable' => true, 'className' => 'text-left text-nowrap'],
            ['data' => 'betStatus', 'name' => 'betStatus', 'title' => 'Bet Status', 'orderable' => false, 'searchable' => false, 'className' => 'text-left text-nowrap'],
            ['data' => 'gameName', 'name' => 'gameName', 'title' => 'Game Name', 'orderable' => false, 'searchable' => false, 'className' => 'text-left text-nowrap'],
            ['data' => 'stake', 'name' => 'stake', 'title' => 'Stake', 'orderable' => false, 'searchable' => false, 'className' => 'text-left text-nowrap'],
            ['data' => 'payoutStatus', 'name' => 'payoutStatus', 'title' => 'Payout Status', 'orderable' => false, 'searchable' => false, 'className' => 'text-left text-nowrap'],
            ['data' => 'payout', 'name' => 'payout', 'title' => 'Payout', 'orderable' => false, 'searchable' => false, 'className' => 'text-left text-nowrap'],
            ['data' => 'betId', 'name' => 'betId', 'title' => 'betId', 'orderable' => false, 'searchable' => true, 'className' => 'text-left text-nowrap'],
            ['data' => 'roundId', 'name' => 'roundId', 'title' => 'roundId', 'orderable' => false, 'searchable' => true, 'className' => 'text-left text-nowrap'],
            ['data' => 'updatedDate', 'name' => 'updatedDate', 'title' => 'Updated Date', 'orderable' => false, 'searchable' => false, 'className' => 'text-center text-nowrap'],
        ];
    }

    /**
     * Get filename for export.
     *
     * @return string
     */
    protected function filename()
    {
        return 'bankin_datatable_' . time();
    }
}
