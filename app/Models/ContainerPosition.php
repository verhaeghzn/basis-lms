<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContainerPosition extends Model
{
    protected $fillable = [
        'container_id',
        'compartment_x',
        'compartment_y',
        'sample_id',
        'custom_name',
    ];

    public function container()
    {
        return $this->belongsTo(Container::class);
    }

    public function sample()
    {
        return $this->belongsTo(Sample::class);
    }

    public function getDisplayNameAttribute()
    {
        if ($this->sample) {
            return $this->sample->fullUniqueRef();
        }
        
        return $this->custom_name ?? "Position {$this->compartment_x},{$this->compartment_y}";
    }

    public function hasSample()
    {
        return !is_null($this->sample_id);
    }

    public function hasCustomName()
    {
        return !is_null($this->custom_name);
    }
}
