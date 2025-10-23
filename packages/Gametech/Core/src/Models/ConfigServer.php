<?php

namespace Gametech\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Gametech\Core\Contracts\ConfigServer as ConfigServerContract;

class ConfigServer extends Model implements ConfigServerContract
{
    protected $fillable = ['token','url','accno','package_id'];

    public function package()
    {
        return $this->belongsTo(ConfigPackageProxy::modelClass(), 'package_id');
    }
}