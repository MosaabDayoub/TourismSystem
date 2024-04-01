<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Tripday
 * 
 * @property int $id
 * @property int|null $trip_id
 * @property int|null $city_id
 * @property Carbon|null $date
 * @property int|null $hotel_id
 * @property string|null $transportaition_method
 * @property int $flight
 * 
 * @property City|null $city
 * @property Hotel|null $hotel
 * @property Trip|null $trip
 * @property Collection|Dayplace[] $dayplaces
 *
 * @package App\Models
 */
class Tripday extends Model
{
	protected $table = 'tripday';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'id' => 'int',
		'trip_id' => 'int',
		'city_id' => 'int',
		'date' => 'datetime',
		'hotel_id' => 'int',
		'flight' => 'int'
	];

	protected $fillable = [
		'trip_id',
		'city_id',
		'date',
		'hotel_id',
		'transportaition_method',
		'flight'
	];

	public function city()
	{
		return $this->belongsTo(City::class);
	}

	public function hotel()
	{
		return $this->belongsTo(Hotel::class);
	}

	public function trip()
	{
		return $this->belongsTo(Trip::class);
	}

	public function dayplaces()
	{
		return $this->hasMany(Dayplace::class, 'day_id');
	}
}
