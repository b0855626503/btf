<?php

namespace Gametech\Core\Models;

use Alexmg86\LaravelSubQuery\Traits\LaravelSubQueryTrait;
use DateTimeInterface;
use Gametech\Core\Contracts\Website as WebsiteContract;
use Illuminate\Database\Eloquent\Model;

class Website extends Model implements WebsiteContract
{
    use LaravelSubQueryTrait;

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    protected $table = 'websites';

    const CREATED_AT = 'date_create';
    const UPDATED_AT = 'date_update';

    protected $dateFormat = 'Y-m-d H:i:s';

    protected $primaryKey = 'code';

    protected $fillable = [
        'group_bot',
        'name',
        'user',
        'appID',
        'pass',
        'scode',
        'balance',
        'enable',
        'user_create',
        'user_update',
    ];

//    protected $casts = [
//        'date_update' => 'datetime:Y-m-d H:i:s',
//    ];
}
