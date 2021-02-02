<?php

namespace Findologic\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Script
 *
 * @property string $name
 * @property string $uuid
 * @property string $store_hash
 * @property int $store_id
 */
class Script extends Model
{
    protected $table = "scripts";

    protected $fillable = ['name', 'uuid', 'store_hash', 'store_id'];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
