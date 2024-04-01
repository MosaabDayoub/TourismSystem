<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Trip
 * 
 * @property int $id
 * @property string|null $country
 * @property int|null $usre_id
 * @property int $from_city
 * @property string|null $number_of_people
 * @property int|null $number_of_days
 * @property int|null $budget
 * @property string|null $preferred_food
 * @property string|null $transportation
 * @property int|null $flight_id
 * 
 * @property City $city
 * @property User|null $user
 * @property Collection|HotelReservation[] $hotel_reservations
 * @property Collection|Tripday[] $tripdays
 *
 * @package App\Models
 */
class Trip extends Model
{
	protected $table = 'trip';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'id' => 'int',
		'usre_id' => 'int',
		'from_city' => 'int',
		'number_of_days' => 'int',
		'budget' => 'int',
		'flight_id' => 'int'
	];

	protected $fillable = [
		'country',
		'usre_id',
		'from_city',
		'number_of_people',
		'number_of_days',
		'budget',
		'preferred_food',
		'transportation',
		'flight_id'
	];

	public function country()
	{
		return $this->belongsTo(Country::class, 'country');
	}

	public function city()
	{
		return $this->belongsTo(City::class, 'from_city');
	}

	public function user()
	{
		return $this->belongsTo(User::class, 'usre_id');
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
