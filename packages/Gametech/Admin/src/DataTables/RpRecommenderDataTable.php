<?php

namespace Gametech\Admin\DataTables;

use Gametech\Admin\Transformers\RpRecommenderTransformer;
use Gametech\Admin\Transformers\RpSponsorTransformer;
use Gametech\Member\Contracts\Member;
use Gametech\Payment\Contracts\PaymentPromotion;
use Yajra\DataTables\DataTableAbstract;
use Yajra\DataTables\EloquentDataTable;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\Services\DataTable;


class RpRecommenderDataTable extends DataTable
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
            ->with('sum', function () use ($query) {
                return core()->currency((clone $query)->sum('payment_value_sum'));
            })
            ->setTransformer(new RpRecommenderTransformer);

    }


    /**
     * @param PaymentPromotion $model
     * @return mixed
     */
    public function query(Member $model)
    {
        $ip = request()->input('ip');
        $username = request()->input('user_name');
//        $down_id = request()->input('downline_id');
        $startdate = request()->input('startDate');
        $enddate = request()->input('endDate');
        if (empty($startdate)) {
            $startdate = now()->toDateString() . ' 00:00:00';
        }
        if (empty($enddate)) {
            $enddate = now()->toDateString() . ' 23:59:59';
        }

//        return $model->newQuery()->with('member','down')
//            ->active()->aff()->orderBy('code','desc')
//            ->select('payments_promotion.*')->withCasts([
//                'date_create' => 'datetime:Y-m-d H:00'
//            ])->when($startdate, function ($query, $startdate) use ($enddate) {
//                $query->whereBetween('payments_promotion.date_create', array($startdate, $enddate));
//            });

        return $model->newQuery()

//            ->withSum(
//            [ 'payment' => fn ($query)  => $query->where('column', 'value')],
//            'value'
//        )
            ->withSum(['payment:value' => function (Builder $query) use ($startdate, $enddate) {

                $query->whereBetween('date_create', array($startdate, $enddate));

            }])

//            ->withSum(['value', 'bankPayments' => function (Builder $query) use ($startdate, $enddate) {
//            $query->where('bank_payment.status', 1)->where('bank_payment.enable', 'Y')->whereBetween('bank_payment.date_create', array($startdate, $enddate));
//        }])
//            ->with(['bankPayments' => function ($query)  use ($startdate,$enddate){
//            $query->where('bank_payment.status',1)->where('bank_payment.enable','Y')->whereBetween('bank_payment.date_create', array($startdate, $enddate))->sum('bank_payment.value');
//        }])
            ->active()

            ->when($startdate, function ($query, $startdate) use ($enddate) {
                $query->whereBetween('members.date_create', array($startdate, $enddate));
            })
            ->when($ip, function ($query, $ip) {
                $query->where('ip', 'like', "%" . $ip . "%");
            })
//            ->when($username, function ($query, $username) {
//                $query->where('members.user_name', $username);
//            });
            ->when($username, function ($query, $username) {
                $query->whereIn('members.upline_code', function ($q) use ($username) {
                    $q->from('members')->select('members.code')->where('members.user_name', $username);
                });
            });
//            ->when($username, function ($query, $username) {
//                $query->whereHas('up', function ($q) use ($username) {
//                    $q->where('user_name', $username);
//                });
//            })
//            ;


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
                'deferLoading' => false,
                'paging' => true,
                'searching' => false,
                'deferRender' => true,
                'retrieve' => true,
                'ordering' => true,
                'autoWidth' => false,
                'pageLength' => 50,
                'order' => [[0, 'desc']],
                'lengthMenu' => [
                    [50, 100, 200, 500, 1000],
                    ['50 rows', '100 rows', '200 rows', '500 rows', '1000 rows']
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
            ['data' => 'code', 'name' => 'members.code', 'title' => '#', 'orderable' => true, 'searchable' => false, 'className' => 'text-center text-nowrap'],
            ['data' => 'date_regis', 'name' => 'members.date_regis', 'title' => 'วันที่สมัคร', 'orderable' => false, 'searchable' => false, 'className' => 'text-center text-nowrap'],
            ['data' => 'name', 'name' => 'members.name', 'title' => 'Name', 'orderable' => false, 'searchable' => false, 'className' => 'text-left text-nowrap'],
            ['data' => 'user_name', 'name' => 'members.user_name', 'title' => 'User ID ', 'orderable' => false, 'searchable' => false, 'className' => 'text-left text-nowrap'],
//            ['data' => 'bonus', 'name' => 'payments_promotion.credit_before', 'title' => 'Bonus (Upline)', 'orderable' => false, 'searchable' => false, 'className' => 'text-right text-nowrap'],
//            ['data' => 'down_name', 'name' => 'payments_promotion.credit', 'title' => 'Name (Downline)', 'orderable' => false, 'searchable' => false, 'className' => 'text-left text-nowrap'],
//            ['data' => 'down_id', 'name' => 'payments_promotion.credit', 'title' => 'User ID (Downline)', 'orderable' => false, 'searchable' => false, 'className' => 'text-left text-nowrap'],
            ['data' => 'amount', 'name' => 'members.value', 'title' => 'ยอดที่ฝากเข้ามา', 'orderable' => false, 'searchable' => false, 'className' => 'text-right text-nowrap'],
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
