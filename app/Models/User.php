<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\User
 *
 * @property string $username
 * @property string $email
 * @property string $role
 * @property int $bigcommerce_user_id
 * @property int $store_id
 */
class User extends Model
{
    protected $table = 'users';
    protected $fillable = ['username', 'email', 'role', 'bigcommerce_user_id', 'store_id'];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
