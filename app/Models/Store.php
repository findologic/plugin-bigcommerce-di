<?php

namespace App\Models;

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

    protected $fillable = ['context', 'access_token'];

    public function config()
    {
        return $this->hasOne(Config::class);
    }

    public function scripts()
    {
        return $this->hasMany(Script::class);
    }
}
