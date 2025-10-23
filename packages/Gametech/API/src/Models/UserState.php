<?php

namespace Gametech\API\Models;

use Illuminate\Database\Eloquent\Model;
use Gametech\API\Contracts\UserState as UserStateContract;

class UserState extends Model implements UserStateContract
{
    protected $fillable = ['user_id','state','name','account_number','socials','image_url','details'];
}