<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Country
 * 
 * @property string $country_name
 * @property string|null $country_code
 * 
 * @property Collection|City[] $cities
 * @property Collection|Trip[] $trips
 *
 * @package App\Models
 */
class Country extends Model
{
	protected $table = 'country';
	protected $primaryKey = 'country_name';
	public $incrementing = false;
	public $timestamps = false;

	protected $fillable = [
		'country_code'
	];

	public function cities()
	{
		return $this->hasMany(City::class, 'country');
	}

	public function trips()
	{
		return $this->hasMany(Trip::class, 'country');
	}
}
