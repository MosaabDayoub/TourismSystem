<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Naturalplace
 * 
 * @property int $id
 * @property string|null $name
 * @property string|null $address
 * @property int|null $city_id
 * @property string|null $location
 * @property string|null $desciption
 * 
 * @property City|null $city
 * @property Collection|Dayplace[] $dayplaces
 *
 * @package App\Models
 */
class Naturalplace extends Model
{
	protected $table = 'naturalplace';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'id' => 'int',
		'city_id' => 'int'
	];

	protected $fillable = [
		'name',
		'address',
		'city_id',
		'location',
		'desciption'
	];

	public function city()
	{
		return $this->belongsTo(City::class);
	}

	public function dayplaces()
	{
		return $this->hasMany(Dayplace::class, 'place_id');
	}
}
