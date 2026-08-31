<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Container extends Model
{
    protected static function booted()
    {
        static::created(function ($container) {
            \App\Models\TimelineEvent::recordCreated($container);
        });
    }

    protected $fillable = [
        'name',
        'compartments_x_size',
        'compartments_y_size',
    ];

    public function notes()
    {
        return $this->hasMany(ContainerNote::class);
    }

    public function samples()
    {
        return $this->hasMany(Sample::class);
    }

    public function positions()
    {
        return $this->hasMany(ContainerPosition::class);
    }


    public function getCompartmentsCountAttribute()
    {
        return $this->compartments_x_size * $this->compartments_y_size;
    }

    public function getReadableSizeAttribute()
    {
        return "{$this->compartments_x_size} x {$this->compartments_y_size}";
    }

    public function notesOfCompartment($x, $y)
    {
        return $this->notes()->where('compartment_x', $x)->where('compartment_y', $y)->get();
    }

    public function addNote($x, $y, $content, $authorId = null)
    {
        return $this->notes()->create([
            'compartment_x' => $x,
            'compartment_y' => $y,
            'content' => $content,
            'author_id' => $authorId,
        ]);
    }

    public function getPositionAt($x, $y)
    {
        return $this->positions()->where('compartment_x', $x)->where('compartment_y', $y)->first();
    }

    public function setPositionSample($x, $y, $sampleId)
    {
        return $this->positions()->updateOrCreate(
            ['compartment_x' => $x, 'compartment_y' => $y],
            ['sample_id' => $sampleId, 'custom_name' => null]
        );
    }

    public function setPositionCustomName($x, $y, $customName)
    {
        return $this->positions()->updateOrCreate(
            ['compartment_x' => $x, 'compartment_y' => $y],
            ['sample_id' => null, 'custom_name' => $customName]
        );
    }

    public function clearPosition($x, $y)
    {
        return $this->positions()->where('compartment_x', $x)->where('compartment_y', $y)->delete();
    }

    public function getPositionGrid()
    {
        $grid = [];
        for ($x = 1; $x <= $this->compartments_x_size; $x++) {
            for ($y = 1; $y <= $this->compartments_y_size; $y++) {
                $position = $this->getPositionAt($x, $y);
                $grid[$x][$y] = $position ? $position->display_name : null;
            }
        }
        return $grid;
    }

    /**
     * Get all samples in this container (both old and new system)
     */
    public function getAllSamples()
    {
        // Get samples from the old system (direct relationship)
        $oldSamples = $this->samples;
        
        // Get samples from the new system (via container_positions)
        $newSamples = $this->positions()->with('sample')->get()->pluck('sample')->filter();
        
        // Merge and deduplicate
        return $oldSamples->merge($newSamples)->unique('id');
    }

    /**
     * Get the content at a specific position (sample or custom name)
     */
    public function getContentAt($x, $y)
    {
        // First check the new position system
        $position = $this->getPositionAt($x, $y);
        if ($position) {
            return $position->display_name;
        }

        // Fallback to old sample system
        $sample = $this->samples()
            ->where('compartment_x', $x)
            ->where('compartment_y', $y)
            ->first();
        
        return $sample ? $sample->fullUniqueRef() : null;
    }

    /**
     * Check if a position is occupied
     */
    public function isPositionOccupied($x, $y)
    {
        // Check new position system
        if ($this->getPositionAt($x, $y)) {
            return true;
        }

        // Check old sample system
        return $this->samples()
            ->where('compartment_x', $x)
            ->where('compartment_y', $y)
            ->exists();
    }
}
