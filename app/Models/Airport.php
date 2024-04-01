<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Airport
 * 
 * @property int $id
 * @property string|null $name
 * @property string|null $address
 * @property int|null $city_id
 * @property string|null $location
 * 
 * @property City|null $city
 * @property Collection|FlightReservation[] $flight_reservations
 *
 * @package App\Models
 */
class Airport extends Model
{
	protected $table = 'airport';
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
		'location'
	];

	public function city()
	{
		return $this->belongsTo(City::class);
	}

	public function flight_reservations()
	{
		return $this->hasMany(FlightReservation::class, 'to_airport');
	}
}
