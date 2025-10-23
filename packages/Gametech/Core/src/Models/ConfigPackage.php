<?php

namespace Gametech\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Gametech\Core\Contracts\ConfigPackage as ConfigPackageContract;

class ConfigPackage extends Model implements ConfigPackageContract
{
    protected $fillable = ['title','value','type','sender'];

    public function servers()
    {
        return $this->hasMany(ConfigServerProxy::modelClass(), 'package_id');
    }
}