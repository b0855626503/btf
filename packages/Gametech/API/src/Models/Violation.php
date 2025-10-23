<?php

namespace Gametech\API\Models;

use Illuminate\Database\Eloquent\Model;
use Gametech\API\Contracts\Violation as ViolationContract;

class Violation extends Model implements ViolationContract
{
//    protected $fillable = ['user_id','username','name','account_number','socials','image_url','details'];
    protected $fillable = ['message','user_id','media_url','step','account_number','mobile_number','raw_numbers'];

    protected $casts = [
        'raw_numbers' => 'array',
    ];
}