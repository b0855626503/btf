<?php

namespace Gametech\API\Models;

use Gametech\API\Contracts\GameList as GameListContract;
use Illuminate\Support\Facades\Log;
use Jenssegers\Mongodb\Eloquent\Model;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection as MongoCollection;

class GameList extends Model implements GameListContract
{
    public $timestamps = true;

    protected $connection = 'mongodb';
    protected $collection = 'gamelist';

    // ค่าเริ่มต้น
    protected $attributes = [
        'enable' => true,
        'click'  => 0,
    ];

    // ฟิลด์ที่อนุญาตให้ fill
    protected $fillable = [
        'product', 'name', 'code', 'category',
        'type', 'img', 'rank', 'enable',
        'game', 'click', 'method',
        // ฟิลด์เสริมที่ใช้ในระบบซิงก์
        'disabled_at', 'hash',
    ];

    protected $casts = [
        'enable' => 'boolean',
        'click'  => 'integer',
    ];

    // === Timestamp แบบ Mongo (UTCDateTime) ===
    public static function mongoNow(): UTCDateTime
    {
        return new UTCDateTime((int) round(microtime(true) * 1000));
    }

    public function freshTimestamp()
    {
        return static::mongoNow();
    }


}
