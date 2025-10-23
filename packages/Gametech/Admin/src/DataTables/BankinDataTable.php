<?php

namespace Gametech\Admin\DataTables;


use Gametech\Admin\Transformers\BankinTransformer;
use Gametech\Payment\Contracts\BankPayment;
use Yajra\DataTables\DataTableAbstract;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder;
use Yajra\DataTables\Services\DataTable;


class BankinDataTable extends DataTable
{
    /**
     * Build DataTable class.
     *
     * @param mixed $query Results from query() method.
     * @return DataTableAbstract
     */
    public function dataTable($query): DataTableAbstract
    {
        $dataTable = new EloquentDataTable($query);

        $startdate = now()->toDateString() . ' 00:00:00';
        $enddate = now()->toDateString() . ' 23:59:59';

        return $dataTable->skipTotalRecords()

            ->setTransformer(new BankinTransformer);

    }


    /**
     * @param BankPayment $model
     * @return mixed
     */
    public function query(BankPayment $model)
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

        return $model
            ->where('bankstatus',1)
            ->where('value','>',0)
            ->where('status',0)
            ->where('enable','Y')
            ->with(['banks'])
            ->withCasts([
                'date_update' => 'datetime:Y-m-d H:00',
                'time' => 'datetime:Y-m-d H:00'
            ])
            ->select(['bank_payment.id', 'bank_payment.time', 'bank_payment.date_create', 'bank_payment.value', 'bank_payment.bankstatus', 'bank_payment.checking', 'bank_payment.channel', 'bank_payment.detail', 'bank_payment.status', 'bank_payment.bankname', 'bank_payment.tranferer', 'bank_payment.bank', 'bank_payment.check_user']);
//            ->when($startdate, function ($query, $startdate) use ($enddate) {
//                $query->whereBetween('bank_payment.date_create', array($startdate, $enddate));
//            });


    }

    /**
     * Optional method if you want to use html builder.
     *
     * @return Builder
     */
    public function html(): Builder
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
                'paging' => false,
                'searching' => false,
                'deferRender' => true,
                'retrieve' => true,
                'ordering' => true,

                'pageLength' => 50,
                'order' => [[0, 'desc']],
                'lengthMenu' => [
                    [50, 100, 200, 500, 1000],
                    ['50 rows', '100 rows', '200 rows', '500 rows', '1000 rows']
                ],
                'buttons' => [

                ],
                'columnDefs' => [
                    ['targets' => '_all', 'className' => 'text-center text-nowrap']
                ]
            ]);
    }

    /**
     * Get columns.
     *
     * @return array
     */
    protected function getColumns(): array
    {
        return [
            ['data' => 'code', 'name' => 'bank_payment.id', 'title' => '#', 'orderable' => true, 'searchable' => true, 'className' => 'text-center text-nowrap'],
            ['data' => 'bank', 'name' => 'bank_payment.bank', 'title' => 'Bank', 'orderable' => false, 'searchable' => false, 'className' => 'text-left text-nowrap'],

            ['data' => 'bank_time', 'name' => 'bank_payment.bank_time', 'title' => 'Bank Time', 'orderable' => false, 'searchable' => true, 'className' => 'text-center text-nowrap'],
//            ['data' => 'acc_no', 'name' => 'bank_account.acc_no', 'title' => 'เลขบัญชี', 'orderable' => false, 'searchable' => true, 'className' => 'text-center text-nowrap'],
            ['data' => 'channel', 'name' => 'bank_payment.channel', 'title' => 'Channel', 'orderable' => false, 'searchable' => true, 'className' => 'text-center text-nowrap'],
            ['data' => 'detail', 'name' => 'bank_payment.detail', 'title' => 'Detail', 'orderable' => false, 'searchable' => true, 'className' => 'text-left text-nowrap'],
            ['data' => 'value', 'name' => 'bank_payment.value', 'title' => 'Amount', 'orderable' => false, 'searchable' => true, 'className' => 'text-right text-nowrap'],
//            ['data' => 'user_name', 'name' => 'bank_payment.user_name', 'title' => 'User ID', 'orderable' => false, 'searchable' => true, 'className' => 'text-center text-nowrap'],
            ['data' => 'date', 'name' => 'bank_payment.date_update', 'title' => 'Server CheckTime', 'orderable' => false, 'searchable' => true, 'className' => 'text-center text-nowrap'],
            ['data' => 'check', 'name' => 'check', 'title' => 'Check User', 'orderable' => false, 'searchable' => false, 'className' => 'text-center text-nowrap'],
            ['data' => 'topup', 'name' => 'topup', 'title' => 'Topup', 'orderable' => false, 'searchable' => false, 'className' => 'text-center text-nowrap'],
//            ['data' => 'cancel', 'name' => 'cancel', 'title' => 'ปฏิเสธ', 'orderable' => false, 'searchable' => false, 'className' => 'text-center text-nowrap'],
            ['data' => 'delete', 'name' => 'delete', 'title' => 'Delete', 'orderable' => false, 'searchable' => false, 'className' => 'text-center text-nowrap'],
        ];
    }

    /**
     * Get filename for export.
     *
     * @return string
     */
    protected function filename(): string
    {
        return 'bankin_datatable_' . time();
    }
}
