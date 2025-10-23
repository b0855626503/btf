<?php

namespace Gametech\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Gametech\Core\Contracts\CheckCase as CheckCaseContract;

class CheckCase extends Model implements CheckCaseContract
{
    protected $table = 'check_case';

    const CREATED_AT = 'date_create';
    const UPDATED_AT = 'date_update';

    protected $dateFormat = 'Y-m-d H:i:s';

    protected $primaryKey = 'code';

    protected $fillable = [
        'txid',
        'username',
        'name',
        'amount',
        'payamount',
        'status',
        'detail',
        'url',
        'qrcode',
        'download',
        'enable',
        'expired_date',
        'user_create',
        'user_update'
    ];
}