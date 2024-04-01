<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Oldplace
 * 
 * @property int $id
 * @property string|null $name
 * @property string|null $address
 * @property int|null $city_id
 * @property string|null $location
 * @property string|null $desciption
 * @property float|null $price
 * 
 * @property City|null $city
 * @property Collection|Dayplace[] $dayplaces
 *
 * @package App\Models
 */
class Oldplace extends Model
{
	protected $table = 'oldplace';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'id' => 'int',
		'city_id' => 'int',
		'price' => 'float'
	];

	protected $fillable = [
		'name',
		'address',
		'city_id',
		'location',
		'desciption',
		'price'
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
