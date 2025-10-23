<?php

namespace Gametech\Admin\Transformers;

use Gametech\Payment\Contracts\BankPayment;
use Illuminate\Support\Str;
use League\Fractal\TransformerAbstract;

class BankinTransformer extends TransformerAbstract
{
    /** ปุ่มสั้นๆ ไวๆ */
    protected function btn(string $class, string $iconHtml, string $onClick): string
    {
        return '<button class="btn '.$class.' btn-xs icon-only" onclick="'.$onClick.'">'.$iconHtml.'</button>';
    }

    /** ปุ่มยืนยัน/เติม (แทน blade: datatables_refill) */
    protected function buildConfirmHtml(int $code, int $status, string $txid, string $autocheck, string $checkUser, string $checktime): string
    {
        // เงื่อนไขจาก blade เดิม:
        // 1) status == 0 && txid == ''  -> ปุ่ม + เรียก editModal
        // 2) status == 0 && txid != '' && autocheck != 'W' -> ปุ่ม ✔ เรียก approveModal
        if ($status === 0 && $autocheck === 'N') {
            return $this->btn('btn-secondary', '<i class="fas fa-search"></i>', "editModal({$code})");
        }
        if ($status === 0 && $autocheck === 'Y') {
            return $txid. '<br>[ '.$checkUser.' ]<br>'.$checktime;
        }
        return '';
    }

    /** ปุ่มแก้ไข (แทน blade: datatables_edit) — เดาว่าเปิด modal แก้ไขรายการ */
    protected function buildEditHtml(int $code, int $status, string $autocheck): string
    {
        // ส่วนใหญ่ระบบจะให้แก้ไขได้ตอนสถานะยัง 0
        if ($status === 0 && $autocheck === 'Y') {
            return $this->btn('btn-primary', '<i class="fas fa-plus"></i>', "approveModal({$code})");
        }
        return 'Pending';
    }

    /** ปุ่มยกเลิก (แทน blade: datatables_clear) */
    protected function buildCancelHtml(int $code, int $status): string
    {
        if ($status === 0) {
            return $this->btn('btn-warning', '<i class="fas fa-times"></i>', "clearModal({$code})");
        }
        return '';
    }

    /** ปุ่มลบ (แทน blade: datatables_delete) */
    protected function buildDeleteHtml(int $code, int $status): string
    {
        if ($status === 0) {
            return $this->btn('btn-danger', '<i class="fas fa-trash"></i>', "delModal({$code})");
        }
        return '';
    }

    public function transform(BankPayment $model): array
    {
        $code      = (int) $model->id;
        $status    = (int) ($model->status ?? 0);
        $txid      = (string) ($model->tranferer ?? '');
        $checkUser      = (string) ($model->check_user ?? '');
        $autocheck = (string) ($model->checking ?? 'N');
//        $checktime =  core()->formatDate($model->checktime,'d/m/y H:i:s');
        $checktime =  date('d/m/y H:i:s', $model->checktime);

        // โลโก้ธนาคารของบัญชีรับเงิน (bank_account->bank)
        static $bankCache = [];
//        dd($model->banks?->filepic);
        $bankHtml = '';
        if ($model->bank && $model->bankname) {
            $bank = explode('_',$model->bank);
            $short = (string) $bank[1];
//            dd($short);
            $pic   = (string) $model->banks?->filepic;
            $key   = $short.'|'.$pic;
            if (!isset($bankCache[$key])) {
                $bankCache[$key] = core()->displayBank($short, $pic);
            }
            $bankHtml = $bankCache[$key];
        }

        // ช่องทาง + ผู้บันทึก
        $channelText = (string) ($model->channel ?? '');
        $channelHtml = e(Str::limit($channelText, 10));

        // รายละเอียด + remark/auto
        $detailText = (string) ($model->detail ?? '');

        $detailHtml = e($detailText);

        return [
            'code'       => $code,
            'bank'   => $bankHtml,
//            'acc_no'     => $model->bank_account->acc_no ?? '',
            'bank_time'  => $model->time ? $model->time->format('d/m/y H:i:s') : '',
//            'user_name'  => $model->member->user_name ?? '',
            'channel'    => $channelHtml,
            'detail'     => $detailHtml,
            'value'      => '<span style="color:blue">'.(string) $model->value.'</span>',
            'date'       => $model->date_create ? $model->date_create->format('d/m/y H:i:s') : '',

            // ปุ่ม inline ทั้งหมด (ไม่ใช้ view()->render())
            'check'    => $this->buildConfirmHtml($code, $status, $txid, $autocheck,$checkUser,$checktime),
            'topup'       => $this->buildEditHtml($code, $status,$autocheck),
//            'cancel'     => $this->buildCancelHtml($code, $status),
            'delete'     => $this->buildDeleteHtml($code, $status),
        ];
    }
}
