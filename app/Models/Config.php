<?php

namespace Findologic\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Findologic\Models\Config
 *
 * @property string $shopkey
 * @property string $language
 * @property bool $active
 * @property int $store_id
 */
class Config extends Model
{
    protected $table = "configs";

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
