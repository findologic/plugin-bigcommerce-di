<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Config
 *
 * @property string $shopkey
 * @property string $language
 * @property bool $active
 * @property int $store_id
 */
class Config extends Model
{
    protected $table = "configs";

    protected $fillable = ['shopkey', 'language', 'active', 'store_id'];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Accessor to ensure active is always returned as boolean.
     * This is necessary as sqlite returns 0 and 1 during testing.
     *
     * @param mixed $value
     * @return bool
     */
    public function getActiveAttribute($value): bool
    {
        return boolval($value);
    }
}
