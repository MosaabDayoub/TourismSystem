<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

/**
 * Class User
 *
 * @property int $id
 * @property string $name
 * @property string|null $image_refrence
 * @property string $email
 * @property string $password
 * @property int $age
 *
 * @property Collection|Trip[] $trips
 *
 * @package App\Models
 */
class User extends Model
{
    use HasApiTokens;

    protected $table = 'user';
    public $timestamps = false;

    protected $casts = [
        'age' => 'int'
    ];

    protected $hidden = [
        'password'
    ];

    protected $fillable = [
        'name',
        'image_refrence',
        'email',
        'password',
        'age',
        'gender',
        'country',
    ];

    public function trips()
    {
        return $this->hasMany(Trip::class, 'usre_id');
    }
}
