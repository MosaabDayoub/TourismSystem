<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Hotel
 * 
 * @property int $id
 * @property string|null $name
 * @property string|null $address
 * @property int|null $city_id
 * @property string|null $location
 * @property int|null $PricePearPerson
 * @property int|null $stars
 * 
 * @property City|null $city
 * @property Collection|HotelReservation[] $hotel_reservations
 * @property Collection|Tripday[] $tripdays
 *
 * @package App\Models
 */
class Hotel extends Model
{
	protected $table = 'hotel';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'id' => 'int',
		'city_id' => 'int',
		'PricePearPerson' => 'int',
		'stars' => 'int'
	];

	protected $fillable = [
		'name',
		'address',
		'city_id',
		'location',
		'price',
		'stars'
	];

	public function city()
	{
		return $this->belongsTo(City::class);
	}

	public function hotel_reservations()
	{
		return $this->hasMany(HotelReservation::class);
	}

	public function tripdays()
	{
		return $this->hasMany(Tripday::class);
	}
}
