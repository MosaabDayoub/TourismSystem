<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Dayplace
 * 
 * @property int $place_id
 * @property int $day_id
 * @property int|null $transport_method
 * @property Carbon|null $time
 * @property float|null $money_amount
 * 
 * @property Tripday $tripday
 * @property Naturalplace $naturalplace
 * @property Nightplace $nightplace
 * @property Oldplace $oldplace
 * @property Resturant $resturant
 *
 * @package App\Models
 */
class Dayplace extends Model
{
	protected $table = 'day place';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'place_id' => 'int',
		'day_id' => 'int',
		'transport_method' => 'int',
		'time' => 'datetime',
		'money_amount' => 'float'
	];

	protected $fillable = [
		'transport_method',
		'time',
		'money_amount'
	];

	public function tripday()
	{
		return $this->belongsTo(Tripday::class, 'day_id');
	}

	public function naturalplace()
	{
		return $this->belongsTo(Naturalplace::class, 'place_id');
	}

	public function nightplace()
	{
		return $this->belongsTo(Nightplace::class, 'place_id');
	}

	public function oldplace()
	{
		return $this->belongsTo(Oldplace::class, 'place_id');
	}

	public function resturant()
	{
		return $this->belongsTo(Resturant::class, 'place_id');
	}
}
