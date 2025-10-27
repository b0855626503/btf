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

        // keep defaults here in case needed later
        $startdate = now()->toDateString() . ' 00:00:00';
        $enddate   = now()->toDateString() . ' 23:59:59';

        return $dataTable
            // ตัดการนับรวมทั้งหมด
            ->skipTotalRecords()
            // ตัดการ apply start/length จาก client (กัน limit 50 โผล่)
            ->skipPaging()
            // กัน Yajra บางเวอร์ชันยังคำนวณตัวเลข — เซ็ตเป็นศูนย์ไปเลย
            ->setTotalRecords(0)
            ->setFilteredRecords(0)
            ->setTransformer(new BankinTransformer);
    }

    /**
     * @param BankPayment $model
     * @return mixed
     */
    public function query(BankPayment $model)
    {
        $status    = request()->input('status'); // reserved
        $startdate = request()->input('startDate');
        $enddate   = request()->input('endDate');

        if (empty($startdate)) {
            $startdate = now()->toDateString() . ' 00:00:00';
        }
        if (empty($enddate)) {
            $enddate = now()->toDateString() . ' 23:59:59';
        }

        // hard cap ป้องกันโหลดหนัก: รับ ?limit= แต่คุมช่วง 50–5000
        $limit = (int) request()->input('limit', 100);
        if ($limit < 10) $limit = 10;
        if ($limit > 100) $limit = 100;

        return $model
            ->where('bankstatus', 1)
            ->where('value', '>', 0)
            ->where('status', 0)
            ->where('enable', 'Y')
            // ถ้าต้องการช่วงวัน ให้เปิดใช้บรรทัดนี้ (คอมเมนต์ไว้ถ้าอยากดึงทั้งหมดของสถานะ)
            // ->whereBetween('bank_payment.date_create', [$startdate, $enddate])
            ->with(['banks','BankAccount'])
            ->withCasts([
                'checktime' => 'datetime',
                'date_update' => 'datetime',
                'time'        => 'datetime',
            ])
            // ให้ลำดับล่าสุดมาก่อน แล้วค่อยลิมิต
            ->orderBy('bank_payment.id', 'desc')
            ->select([
                'bank_payment.id',
                'bank_payment.time',
                'bank_payment.date_create',
                'bank_payment.value',
                'bank_payment.bankstatus',
                'bank_payment.checking',
                'bank_payment.checkstatus',
                'bank_payment.topupstatus',
                'bank_payment.channel',
                'bank_payment.detail',
                'bank_payment.status',
                'bank_payment.bankname',
                'bank_payment.tranferer',
                'bank_payment.bank',
                'bank_payment.bank_code',
                'bank_payment.account_code',
                'bank_payment.check_user',
                'bank_payment.checktime',
                'bank_payment.create_by',
                'bank_payment.date_topup',
            ])
            ->limit($limit);
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
                'serverSide' => true,   // ยังใช้รูปแบบตอบแบบ server-side (Yajra)
                'responsive' => false,
                'stateSave' => true,
                'scrollX' => true,
                'paging' => false,      // ไม่แบ่งหน้า
                'searching' => false,
                'deferRender' => true,
                'retrieve' => true,
                // ปิดการ sort ฝั่ง client เพื่อไม่ให้ ORDER ซ้ำกับ SQL
                'ordering' => false,
                'order' => [],
                'pageLength' => 50,     // ไม่มีผลเมื่อ paging=false
                'lengthMenu' => [
                    [50, 100, 200, 500, 1000],
                    ['50 rows', '100 rows', '200 rows', '500 rows', '1000 rows']
                ],
                'buttons' => [],
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
            ['data' => 'code',      'name' => 'bank_payment.id',           'title' => '#',                 'orderable' => true,  'searchable' => true,  'className' => 'text-center text-nowrap'],
            ['data' => 'bank',      'name' => 'bank_payment.bank',         'title' => 'ธนาคาร',              'orderable' => false, 'searchable' => false, 'className' => 'text-left text-nowrap'],
            ['data' => 'bank_time', 'name' => 'bank_payment.bank_time',    'title' => 'วันเวลา ธนาคาร',         'orderable' => false, 'searchable' => true,  'className' => 'text-center text-nowrap'],
            ['data' => 'channel',   'name' => 'bank_payment.channel',      'title' => 'ช่องทาง',           'orderable' => false, 'searchable' => true,  'className' => 'text-center text-nowrap'],
            ['data' => 'detail',    'name' => 'bank_payment.detail',       'title' => 'รายละเอียด',            'orderable' => false, 'searchable' => true,  'className' => 'text-left text-nowrap'],
            ['data' => 'value',     'name' => 'bank_payment.value',        'title' => 'จำนวนเงิน',            'orderable' => false, 'searchable' => true,  'className' => 'text-right text-nowrap'],
            ['data' => 'date',      'name' => 'bank_payment.date_update',  'title' => 'ผู้บันทึกรายการ',  'orderable' => false, 'searchable' => true,  'className' => 'text-center text-nowrap'],
            ['data' => 'check',     'name' => 'check',                     'title' => 'ผู้ตรวจสอบ',        'orderable' => false, 'searchable' => false, 'className' => 'text-center text-nowrap'],
            ['data' => 'topup',     'name' => 'topup',                     'title' => 'ผู้อนุมัติ',             'orderable' => false, 'searchable' => false, 'className' => 'text-center text-nowrap'],
            ['data' => 'delete',    'name' => 'delete',                    'title' => 'ลบรายการ',            'orderable' => false, 'searchable' => false, 'className' => 'text-center text-nowrap'],
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
