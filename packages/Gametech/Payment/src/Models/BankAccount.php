<?php

namespace Gametech\Payment\Models;

use DateTimeInterface;
use Gametech\Payment\Contracts\BankAccount as BankAccountContract;
use Illuminate\Database\Eloquent\Model;
use Spiritix\LadaCache\Database\LadaCacheTrait;

class BankAccount extends Model implements BankAccountContract
{

    use LadaCacheTrait;

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    protected $table = 'bankaccount';

    const CREATED_AT = 'date_create';
    const UPDATED_AT = 'date_update';

    protected $dateFormat = 'Y-m-d H:i:s';

    protected $dates = ['date_create', 'date_update'];

    protected $primaryKey = 'code';

    protected $fillable = [
        'bankid',
        'bank',
        'accountno',
        'accountname',
        'username',
        'password',
        'status',
        'status_auto',
        'enable',
        'user_create',
        'user_update',
        'date_create',
        'date_update',
    ];


    protected $hidden = [
        'username', 'password'
    ];


    public function scopeIn($query)
    {
        return $query->where('bankaccount.status', 1);
    }

    public function scopeOut($query)
    {
        return $query->where('bankaccount.status', 2);
    }

    public function scopeActive($query)
    {
        return $query->where('bankaccount.enable', 'Y');
    }

    public function scopeInactive($query)
    {
        return $query->where('bankaccount.enable', 'N');
    }



    public function bank_payment()
    {
        return $this->hasMany(BankPaymentProxy::modelClass(), 'account_code');
    }

    public function banks()
    {
        return $this->belongsTo(BankProxy::modelClass(), 'bankid');
    }
}
