<?php

namespace Gametech\Admin\DataTables;


use App\DataTables\Concerns\ExportableLargeData;
use App\DataTables\Override;
use App\Exports\MembersExport;
use App\Exports\UsersExport;
use Gametech\Admin\Transformers\MemberTransformer;
use Gametech\Member\Contracts\Member;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\DataTableAbstract;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder;
use Yajra\DataTables\Services\DataTable;


class MemberDataTable extends DataTable
{
//    use ExportableLargeData;
    protected $exportClass = MembersExport::class;
//    protected $fastExcel = true;
//    protected $fastExcelCallback = false;



    /**
     * Build DataTable class.
     *
     * @param mixed $query Results from query() method.
     * @return DataTableAbstract
     */
    public function dataTable($query)
    {
        $config = core()->getConfigData();

        $prem = bouncer()->hasPermission('wallet.member.tel');
        $prem = Auth::guard('admin')->user()->role;

        $dataTable = new EloquentDataTable($query);

        return $dataTable
            ->setTransformer(new MemberTransformer($config, $prem));
//        return $dataTable->setTransformer(new WithdrawTransformer);
//        return $dataTables->addColumn('action', 'admins::withdraw.datatables_confirm');
//        return $dataTable
//            ->editColumn('member_acc', function($query) {
//            return $query->bankCode->shortcode.'['.$query->memberCode->acc_no.']';
//        });
    }


    /**
     * @param Member $model
     * @return mixed
     */
    public function query(Member $model)
    {
//        $user = request()->input('user_name');
        $startdate = request()->input('startDate');
        $enddate = request()->input('endDate');

        if (empty($startdate)) {
            $startdate = now()->subMonths(3)->startOfMonth()->startOfDay()->toDateString() . ' 00:00:00';
        }
        if (empty($enddate)) {
            $enddate = now()->toDateString() . ' 23:59:59';
        }

        return $model->newQuery()
            ->with('referCode')
            ->select('members.*')

//            ->select(['members.code','members.date_regis','members.firstname','members.lastname','members.upline_code','members.acc_no','members.user_name','members.user_pass','members.lineid','members.tel','members.count_deposit','members.point_deposit','members.diamond','members.balance','members.remark','members.enable','members.status_pro','members.confirm','members.date_create'])
            ->when($startdate, function ($query, $startdate) use ($enddate) {
                $query->whereBetween('members.date_create', array($startdate, $enddate));
            });


    }

    /**
     * Optional method if you want to use html builder.
     *
     * @return Builder
     */
    public function html()
    {
        $btn = ['pageLength'];


        return $this->builder()
            ->columns($this->getColumns())
            ->ajaxWithForm('', '#frmsearch')
            ->parameters([
                'dom' => 'Bfrtip',

                'processing' => true,
                'serverSide' => true,
                'responsive' => true,
                'stateSave' => false,
                'scrollX' => false,
                'paging' => true,
                'searching' => true,
                'deferRender' => true,
                'retrieve' => false,
                'ordering' => true,

                'pageLength' => 10,
                'order' => [[0, 'desc']],
                'lengthMenu' => [
                    [10, 50, 100, 200, 500, 1000],
                    ['10 rows', '50 rows', '100 rows', '200 rows', '500 rows', '1000 rows']
                ],
                'buttons' => $btn,
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
    protected function getColumns()
    {

        return [
            ['data' => 'code', 'name' => 'members.code', 'title' => '#', 'orderable' => true, 'searchable' => false, 'className' => 'text-center text-nowrap'],
            ['data' => 'name', 'name' => 'members.name', 'title' => 'ชื่อ นามสกุล', 'orderable' => false, 'searchable' => true, 'className' => 'text-center text-nowrap'],
            ['data' => 'tel', 'name' => 'members.tel', 'title' => 'เบอร์โทร', 'orderable' => false, 'searchable' => true, 'className' => 'text-center text-nowrap'],
            ['data' => 'email', 'name' => 'members.email', 'title' => 'E-mail', 'orderable' => false, 'searchable' => true, 'className' => 'text-center text-nowrap'],
            ['data' => 'refers', 'name' => 'members.refers', 'title' => 'รู้จักเราจาก', 'orderable' => false, 'searchable' => false, 'className' => 'text-center text-nowrap'],
            ['data' => 'date_regis', 'name' => 'members.date_regis', 'title' => 'วันที่สมัคร', 'orderable' => false, 'searchable' => false, 'className' => 'text-center text-nowrap'],
            ['data' => 'user_create', 'name' => 'members.user_create', 'title' => 'ผู็ทำรายการ', 'orderable' => false, 'searchable' => false, 'className' => 'text-center text-nowrap'],
            ['data' => 'action', 'name' => 'action', 'title' => 'Action', 'orderable' => false, 'searchable' => false, 'className' => 'text-center text-nowrap'],
        ];

    }

    public function fastExcelCallback()
    {


        return function ($row) {

                return [
                    'Date Regis' => $row['date_regis'],
                    'UserName' => $row['user_name'],
                    'FirstName' => $row['firstname'],
                    'LastName' => $row['lastname'],
                    'Line ID' => $row['lineid'],
                    'Mobile Number' => $row['tel'],
                ];

        };
    }


    /**
     * Get filename for export.
     *
     * @return string
     */
    protected function filename()
    {
        return 'member_datatable_' . time();
    }
}
