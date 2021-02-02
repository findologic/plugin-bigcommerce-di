<?php

namespace Findologic\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Store
 *
 * @property string $context
 * @property string $access_token
 */
class Store extends Model
{
    protected $table = "stores";

    public function config()
    {
        return $this->hasOne(Config::class);
    }

    public function scripts()
    {
        return $this->hasMany(Script::class);
    }
}
