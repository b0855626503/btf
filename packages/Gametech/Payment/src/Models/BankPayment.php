<?php

namespace Gametech\Payment\Models;

use Alexmg86\LaravelSubQuery\Traits\LaravelSubQueryTrait;
use DateTimeInterface;
use Gametech\Admin\Models\AdminProxy;
use Gametech\Member\Models\MemberProxy;
use Gametech\Payment\Contracts\BankPayment as BankPaymentContract;
use Gametech\Promotion\Models\PromotionProxy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;


class BankPayment extends Model implements BankPaymentContract
{
    use LaravelSubQueryTrait;

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    protected $table = 'bank_payment';

    const CREATED_AT = 'date_create';
    const UPDATED_AT = 'date_update';

    protected $dateFormat = 'Y-m-d H:i:s';

    protected $dates = ['date_create', 'date_update'];

    protected $primaryKey = 'id';

    protected $fillable = [
        'bank',
        'enable',
        'bankstatus',
        'bankname',
        'bank_code',
        'account_code',
        'txid',
        'time',
        'channel',
        'value',
        'fee',
        'detail',
        'checktime',
        'tx_hash',
        'status',
        'webcode',
        'oldcredit',
        'tranferer',
        'aftercredit',
        'webbefore',
        'webafter',
        'score',
        'pro_id',
        'pro_amount',
        'user_id',
        'date_topup',
        'date_create',
        'msg',
        'atranferer',
        'tranferer',
        'checking',
        'check_user',
        'checkstatus',
        'topupstatus',
        'create_by',
        'ck_step1',
        'ck_step2',
        'ck_step3',
    ];

    protected $casts = [
        'checktime' => 'datetime',
        'date_topup' => 'datetime',
        'time' => 'datetime',
    ];

    /**
     * The attributes that should be casted to native types.
     *
     * @var array
     */

    public function scopeIncome($query)
    {
        return $query->where('bank_payment.value', '>', 0);
    }

    public function scopeProfit($query)
    {
        return $query->where('bank_payment.value', '<', 0);
    }

    public function scopeActive($query)
    {
        return $query->where('bank_payment.enable', 'Y');
    }

    public function scopeInactive($query)
    {
        return $query->where('bank_payment.enable', 'N');
    }

    public function scopeWaiting($query)
    {
        return $query->where('bank_payment.status', 0);
    }

    public function scopeComplete($query)
    {
        return $query->where('bank_payment.status', 1);
    }

    public function scopeReject($query)
    {
        return $query->where('bank_payment.status', 2);
    }

    public function scopeClearout($query)
    {
        return $query->where('bank_payment.status', 3);
    }

    public function scopeCheck($query)
    {
        return $query->where('bank_payment.pro_check', 'Y');
    }

    public function scopeUncheck($query)
    {
        return $query->where('bank_payment.pro_check', 'N');
    }


    public function member()
    {
        return $this->belongsTo(MemberProxy::modelClass(), 'member_topup');
    }

    public function BankAccount()
    {
        return $this->belongsTo(BankAccountProxy::modelClass(), 'account_code');
    }

    public function bank_account()
    {
        return $this->belongsTo(BankAccountProxy::modelClass(), 'account_code');
    }

    public function promotion()
    {
        return $this->belongsTo(PromotionProxy::modelClass(), 'pro_id');
    }

    public function admin()
    {
        return $this->belongsTo(AdminProxy::modelClass(), 'emp_topup');
    }

    public function banks()
    {
        return $this->belongsTo(BankProxy::modelClass(), 'bank_code','code');
    }


}
