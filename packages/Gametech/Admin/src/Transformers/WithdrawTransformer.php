<?php

namespace Gametech\Admin\Transformers;

use Gametech\Payment\Contracts\Withdraw;
use Illuminate\Support\Str;
use League\Fractal\TransformerAbstract;

class WithdrawTransformer extends TransformerAbstract
{
    protected function toggleButton(bool $active, string $onClick): string
    {
        $class = $active ? 'btn-success' : 'btn-danger';
        $icon  = $active ? '<i class="fa fa-check"></i>' : '<i class="fa fa-times"></i>';
        return '<button type="button" class="btn '.$class.' btn-xs icon-only" onclick="'.$onClick.'">'.$icon.'</button>';
    }

    protected function buildConfirmHtml(int $code, int $status, int $empApprove): string
    {
        if ($status === 0 && $empApprove === 0) {
            return '<button class="btn btn-xs btn-secondary icon-only" onclick="editModal('.$code.')"><i class="fas fa-check"></i></button>';
        }
        if ($status === 0 && $empApprove !== 0) {
            return '<button class="btn btn-xs btn-secondary icon-only" onclick="fixModal('.$code.')"><i class="fas fa-check-double"></i></button>';
        }
        return '';
    }

    protected function buildDeductHtml(int $code, int $status, string $ck_withdraw, string $checkUser, string $checktime): string
    {
        if ($status === 0 && $ck_withdraw === 'N') {
            return '<button class="btn btn-xs btn-warning icon-only" onclick="DeductModal('.$code.')"><i class="fas fa-dollar"></i></button>';
        }

        if ($status === 0 && $ck_withdraw === 'Y') {
            return '[ '.$checkUser.' ]<br>'.$checktime;
        }
        return '';
    }

    protected function buildApproveHtml(int $code, int $status, string $ck_withdraw): string
    {
        if ($status === 0 && $ck_withdraw === 'Y') {
            return '<button class="btn btn-xs btn-secondary icon-only" onclick="ApproveModal('.$code.')"><i class="fas fa-plus"></i></button>';
        }
        return 'Pending';
    }

    protected function buildDeleteHtml(int $code, int $status,string $ck_withdraw): string
    {
        if ($status === 0 && $ck_withdraw === 'N') {
            return '<button class="btn btn-xs btn-danger icon-only" onclick="delModal('.$code.')"><i class="fas fa-trash"></i></button>';
        }
        return '';
    }

    /** ดึงค่า string จากอ็อบเจ็กต์หรืออาเรย์ (ปลอดภัย, เบา) */
    protected function getMixed($source, string $key): ?string
    {
        if (is_array($source)) {
            return isset($source[$key]) ? (string)$source[$key] : null;
        }
        if (is_object($source)) {
            return isset($source->{$key}) ? (string)$source->{$key} : null;
        }
        return null;
    }

    public function transform(Withdraw $model): array
    {
        $statusMap = [0 => 'รอดำเนินการ', 1 => 'อนุมัติ', 2 => 'ไม่อนุมัติ'];

        $code    = (int)$model->code;
        $status  = (int)($model->status ?? 0);
        $empCode = ($model->user_create ?? '');
        $ck_withdraw = ($model->ck_withdraw ?? 'Y');
        $checkUser      = (string) ($model->ck_user ?? '');
        $checktime =  core()->formatDate($model->ck_date,'d/m/y H:i:s');

        // --- โลโก้ธนาคาร + เลขบัญชี ---
        static $bankCache = [];
        $accNoHtml = '';
        if ($model->memberBank && $model->memberBank->bank->shortcode && $model->memberBank->bank->filepic) {
            $label = $model->memberBank->bank->name_th.' [ '.(string)($model->memberBank->account_no ?? '').' ] '.($model->memberBank->account_name ?? '');
            $key   = $label.'|'.$model->memberBank->bank->filepic;
            if (!isset($bankCache[$key])) {
                $bankCache[$key] = core()->displayBank($label, $model->memberBank->bank->filepic);
            }
            $accNoHtml = $bankCache[$key];
        }

        // --- ตัวเลข/เครดิตพร้อมสี ---
//        $balanceHtml         = '<span style="color:blue">'.(string)$model->balance.'</span>';
        $amountHtml          = '<span style="color:red">'.(string)$model->amount.'</span>';
//        $amountBalanceHtml   = '<span style="color:black">'.(string)$model->amount_balance.'</span>';
//        $amountLimitHtml     = '<span style="color:black">'.(string)$model->amount_limit.'</span>';
//        $amountLimitRateHtml = '<span style="color:black">'.(string)$model->amount_limit_rate.'</span>';
//        $beforeHtml          = '<span style="color:gray">'.(string)$model->oldcredit.'</span>';
//        $afterHtml           = '<span style="color:gray">'.(string)$model->aftercredit.'</span>';

        // --- วันเวลา + สมาชิก ---
        $date     = $model->date_record ? $model->date_record->format('d/m/y') : '';
        $time     = (string)($model->timedept ?? '');
        $username = (string)($model->member_user ?? '');
//        $gameUser = (string)($model->member->game_user ?? '');
//        $name     = (string)($model->member->name ?? '');

        // --- หมายเหตุสมาชิก (รองรับทั้ง latestMemberRemark หรือ collection เดิม) ---
//        $remarkModel = method_exists($model, 'latestMemberRemark')
//            ? ($model->latestMemberRemark ?? null)
//            : ($model->member_remark ? $model->member_remark->first() : null);
//        $remark = $remarkModel->remark ?? (string)($model->remark ?? '');

        // --- IP (escape + tooltip) ---
//        $ipText = (string)($model->ip ?? '');
//        $ipHtml = '<span class="text-long" data-toggle="tooltip" title="'.e($ipText).'">'
//            . e(Str::limit($ipText, 10))
//            . '</span>';


        // --- สถานะ / วันอนุมัติ / แอดมิน ---
//        $statusText  = $statusMap[$status] ?? '-';
//        $dateApprove = ($model->date_approve instanceof \Carbon\Carbon)
//            ? $model->date_approve->format('d/m/y H:i:s')
//            : '';
        $empApprove  = $empCode;

        // --- ปุ่ม toggle check ---
        $checkBtn = $this->toggleButton(
            (string)$model->check_status === 'Y',
            "editdata({$code},'".core()->flip($model->check_status)."','check_status')"
        );

        // --- ปุ่มยืนยัน/ยกเลิก/ลบ (inline HTML) ---
        $waitingHtml = $this->buildDeductHtml($code, $status,$ck_withdraw,$checkUser,$checktime);
        $approveHtml  = $this->buildApproveHtml($code, $status,$ck_withdraw);
        $deleteHtml  = $this->buildDeleteHtml($code, $status,$ck_withdraw);

        return [
            'code'               => $code,
            'bankm'             => $accNoHtml,
            'amount'             => $amountHtml,
            'date'               => $date,
            'time'               => $time,
            'username'           => $username,
            'user_create'            => $empApprove,
            'waiting'            => $waitingHtml,
            'approve'             => $approveHtml,
            'delete'             => $deleteHtml,
        ];
    }
}
