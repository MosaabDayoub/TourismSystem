<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class City
 * 
 * @property int $city_id
 * @property string|null $name
 * @property float|null $latitude
 * @property float|null $longitude
 * @property string|null $country
 * @property bool|null $capital
 * 
 * @property Collection|Airport[] $airports
 * @property Collection|Hotel[] $hotels
 * @property Collection|Naturalplace[] $naturalplaces
 * @property Collection|Nightplace[] $nightplaces
 * @property Collection|Oldplace[] $oldplaces
 * @property Collection|Resturant[] $resturants
 * @property Collection|ShoopingPlace[] $shooping_places
 * @property Collection|Trip[] $trips
 * @property Collection|Tripday[] $tripdays
 *
 * @package App\Models
 */
class City extends Model
{
	protected $table = 'city';
	protected $primaryKey = 'city_id';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'city_id' => 'int',
		'latitude' => 'float',
		'longitude' => 'float',
		'capital' => 'bool'
	];

	protected $fillable = [
		'name',
		'latitude',
		'longitude',
		'country',
		'capital'
	];

	public function country()
	{
		return $this->belongsTo(Country::class, 'country');
	}

	public function airports()
	{
		return $this->hasMany(Airport::class);
	}

	public function hotels()
	{
		return $this->hasMany(Hotel::class);
	}

	public function naturalplaces()
	{
		return $this->hasMany(Naturalplace::class);
	}

	public function nightplaces()
	{
		return $this->hasMany(Nightplace::class);
	}

	public function oldplaces()
	{
		return $this->hasMany(Oldplace::class);
	}

	public function resturants()
	{
		return $this->hasMany(Resturant::class);
	}

	public function shooping_places()
	{
		return $this->hasMany(ShoopingPlace::class);
	}

	public function trips()
	{
		return $this->hasMany(Trip::class, 'from_city');
	}

	public function tripdays()
	{
		return $this->hasMany(Tripday::class);
	}
}
