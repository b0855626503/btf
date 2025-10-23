<?php

namespace Gametech\Member\Models;

use Alexmg86\LaravelSubQuery\Traits\LaravelSubQueryTrait;
use DateTimeInterface;
use Gametech\Member\Contracts\MemberWinlose as MemberWinloseContract;
use Illuminate\Database\Eloquent\Model;

class MemberWinlose extends Model implements MemberWinloseContract
{
    use LaravelSubQueryTrait;

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    protected $table = 'members_winlose';

    const CREATED_AT = 'date_create';
    const UPDATED_AT = 'date_update';

    protected $dateFormat = 'Y-m-d H:i:s';


    protected $primaryKey = 'code';

    protected $fillable = [
        'member_user',
        'company',
        'date_event',
        'method',
        'roundid',
        'amount',
        'user_create',
        'user_update',
    ];

    /**
     * The attributes that should be casted to native types.
     *
     * @var array
     */
    protected $casts = [
        'code' => 'integer',
        'member_user' => 'string',
        'method' => 'string',
        'roundid' => 'string',
        'user_create' => 'string',

    ];



}
