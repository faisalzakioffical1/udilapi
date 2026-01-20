<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Meter extends Model {
	/**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table;

	/**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
		'gd_id',
		'communication_interval',
		'communication_type',
		'reference_time',
		'wakeup_number_1',
		'wakeup_number_2',
		'wakeup_number_3',
		'created_at',
	];

	/**
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->table = 'mers2';
    }
}

