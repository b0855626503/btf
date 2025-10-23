<?php

namespace Gametech\Admin\DataTables;


use Gametech\Admin\Transformers\WithdrawSeamlessTransformer;
use Gametech\Payment\Contracts\Withdraw;
use Gametech\Payment\Contracts\WithdrawSeamless;
use Yajra\DataTables\DataTableAbstract;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder;
use Yajra\DataTables\Services\DataTable;

class WithdrawSeamlessDataTable extends DataTable
{
    /**
     * Build DataTable class.
     *
     * @param mixed $query Results from query() method.
     * @return DataTableAbstract
     */
    public function dataTable($query)
    {
        $dataTable = new EloquentDataTable($query);

        return $dataTable
            ->with('in_all', function () use ($query) {
                return core()->currency((clone $query)->sum('amount'));
            })
            ->with('in_wait', function () use ($query) {
                return core()->currency((clone $query)->where('status', 0)->sum('amount'));
            })
            ->with('in_yes', function () use ($query) {
                return core()->currency((clone $query)->where('status', 1)->sum('amount'));
            })
            ->with('in_no', function () use ($query) {
                return core()->currency((clone $query)->where('status', 2)->sum('amount'));
            })
            ->setTransformer(new WithdrawSeamlessTransformer);

    }


    /**
     * @param Withdraw $model
     * @return mixed
     */
    public function query(WithdrawSeamless $model)
    {
        $status = request()->input('status');
        $startdate = request()->input('startDate');
        $enddate = request()->input('endDate');
        if (empty($startdate)) {
            $startdate = now()->toDateString() . ' 00:00:00';
        }
        if (empty($enddate)) {
            $enddate = now()->toDateString() . ' 23:59:59';
        }

        return $model->newQuery()
            ->active()->where('status',0)
            ->with(['member', 'bank', 'admin','payment_last'])
            ->with(['bills' => function ($model) {
                $model->with('promotion')->getpro()->active()->orderBy('date_create', 'desc');
            }])->withCasts([
                'date_create' => 'datetime:Y-m-d H:i:s',
                'date_record' => 'date:Y-m-d',
                'date_approve' => 'datetime:Y-m-d H:i:s'
            ])->select('withdraws_seamless.*');
//            ->when($startdate, function ($query, $startdate) use ($enddate) {
//                $query->whereBetween('date_create', array($startdate, $enddate));
//            })
//            ->when($status, function ($query, $status) {
//                $query->where('status', ($status == 'A' ? 0 : $status));
//            });

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
                'searching' => false,
                'deferRender' => true,
                'retrieve' => true,
                'ordering' => true,
                'autoWidth' => false,
                'pageLength' => 20,
                'order' => [[0, 'desc']],
                'lengthMenu' => [
                    [ 20, 50, 100, 200, 500, 1000],
                    ['20 rows', '50 rows', '100 rows', '200 rows', '500 rows', '1000 rows']
                ],
                'buttons' => [
                    'pageLength'
                ],
                'columnDefs' => [
                    ['targets' => '_all', 'className' => 'text-nowrap']
                ]
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
            ['data' => 'code', 'name' => 'withdraws_seamless.code', 'title' => '#', 'orderable' => true, 'searchable' => true, 'className' => 'text-center text-nowrap'],
            ['data' => 'check', 'name' => 'check', 'title' => 'ตรวจสอบ', 'orderable' => false, 'searchable' => false, 'className' => 'text-center text-nowrap'],
            ['data' => 'acc_no', 'name' => 'withdraws_seamless.acc_no', 'title' => 'ธนาคาร', 'orderable' => false, 'searchable' => false, 'className' => 'text-left text-nowrap'],
            ['data' => 'date', 'name' => 'withdraws_seamless.date_record', 'title' => 'วันที่แจ้ง', 'orderable' => false, 'searchable' => true, 'className' => 'text-center text-nowrap'],
            ['data' => 'time', 'name' => 'withdraws_seamless.timedept', 'title' => 'เวลา', 'orderable' => false, 'searchable' => true, 'className' => 'text-center text-nowrap'],
            ['data' => 'username', 'name' => 'withdraws_seamless.member_user', 'title' => 'User ID', 'orderable' => false, 'searchable' => true, 'className' => 'text-center text-nowrap'],
            ['data' => 'name', 'name' => 'member.name', 'title' => 'ชื่อลูกค้า', 'orderable' => false, 'searchable' => true, 'className' => 'text-left text-nowrap'],
            ['data' => 'balance', 'name' => 'withdraws_seamless.balance', 'title' => 'ยอดที่แจ้ง', 'orderable' => false, 'searchable' => true, 'className' => 'text-right text-nowrap'],
            ['data' => 'amount_balance', 'name' => 'withdraws_seamless.amount_balance', 'title' => 'ยอดเทิน', 'orderable' => false, 'searchable' => true, 'className' => 'text-right text-nowrap'],
//            ['data' => 'amount_limit', 'name' => 'withdraws_seamless.amount_limit', 'title' => 'ยอดอั้นคงที่', 'orderable' => false, 'searchable' => true, 'className' => 'text-right text-nowrap'],
            ['data' => 'amount_limit_rate', 'name' => 'withdraws_seamless.amount_limit_rate', 'title' => 'ยอดอั้นถอน', 'orderable' => false, 'searchable' => true, 'className' => 'text-right text-nowrap'],
            ['data' => 'amount', 'name' => 'withdraws_seamless.amount', 'title' => 'จำนวนเงินที่ได้รับ', 'orderable' => false, 'searchable' => true, 'className' => 'text-right text-nowrap'],
            ['data' => 'before', 'name' => 'withdraws_seamless.amount', 'title' => 'เครดิตก่อน', 'orderable' => false, 'searchable' => true, 'className' => 'text-right text-nowrap'],
            ['data' => 'after', 'name' => 'withdraws_seamless.amount', 'title' => 'เครดิตหลัง', 'orderable' => false, 'searchable' => true, 'className' => 'text-right text-nowrap'],
            ['data' => 'ip', 'name' => 'withdraws_seamless.ip', 'title' => 'IP', 'orderable' => false, 'searchable' => true, 'className' => 'text-left text-nowrap'],
            ['data' => 'bonus', 'name' => 'bonus', 'title' => 'โปรที่รับล่าสุด', 'orderable' => false, 'searchable' => false, 'className' => 'text-left text-nowrap'],
            ['data' => 'refill', 'name' => 'refill', 'title' => 'เติมล่าสุดจาก', 'orderable' => false, 'searchable' => false, 'className' => 'text-left text-nowrap'],
            ['data' => 'waiting', 'name' => 'waiting', 'title' => 'อนุมัติ', 'orderable' => false, 'searchable' => false, 'className' => 'text-center text-nowrap'],
            ['data' => 'cancel', 'name' => 'cancel', 'title' => 'คืนยอด', 'orderable' => false, 'searchable' => false, 'className' => 'text-center text-nowrap'],
            ['data' => 'delete', 'name' => 'delete', 'title' => 'ลบ', 'orderable' => false, 'searchable' => false, 'className' => 'text-center text-nowrap'],
        ];
    }

    /**
     * Get filename for export.
     *
     * @return string
     */
    protected function filename()
    {
        return 'withdraw_datatable_' . time();
    }
}
