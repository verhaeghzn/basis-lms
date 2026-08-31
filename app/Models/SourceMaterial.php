<?php

namespace App\Models;

use App\Models\Note;
use App\Models\Photo;
use App\Models\ProcessingStep;
use App\Models\Sample;
use App\Models\User;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class SourceMaterial extends Model
{
    use SoftDeletes;

    protected static function booted()
    {
        static::created(function ($sourceMaterial) {
            \App\Models\TimelineEvent::recordCreated($sourceMaterial);
        });
    }
    protected $fillable = [
        'unique_ref',
        'name',
        'description',
        'grade',
        'supplier',
        'supplier_identifier',
        'composition',
        'width_mm',
        'height_mm',
        'thickness_mm',
        'properties',
    ];

    protected $casts = [
        'composition' => 'array',
        'properties' => 'array',
    ];

    protected function isStarred(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => (bool) $value,
        );
    }

    // While saving, if the composition seems to be a string containing valid JSON, parse it into an array
    public function setCompositionAttribute($value)
    {
        // if value is array with one string element, convert it to a JSON string
        if (is_array($value) && count($value) === 1 && is_string($value[0])) {
            if (json_decode($value[0], true) !== null) {
                $value = json_decode($value[0], true);
            }
        }

        $this->attributes['composition'] = is_array($value) || is_object($value)
            ? json_encode($value)
            : $value;
    }
    
    // While saving, if the properties seems to be a string containing valid JSON, parse it into an array
    public function setPropertiesAttribute($value)
    {
        if (is_array($value) && count($value) === 1 && is_string($value[0])) {
            if (json_decode($value[0], true) !== null) {
                $value = json_decode($value[0], true);
            }
        }

        $this->attributes['properties'] = is_array($value) || is_object($value)
            ? json_encode($value)
            : $value;
    }

    public function samples()
    {
        return $this->hasMany(Sample::class);
    }

    public function processingSteps(): MorphMany
    {
        return $this->morphMany(ProcessingStep::class, 'processable')
            ->orderBy('created_at');
    }

    public function photos(): MorphMany
    {
        return $this->morphMany(Photo::class, 'imageable')->latest();
    }

    public function latestPhoto(): MorphOne
    {
        return $this->morphOne(Photo::class, 'imageable')->latestOfMany();
    }

    public function notes()
    {
        return $this->morphMany(Note::class, 'noteable');
    }

    public function starredByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'source_material_user')->withTimestamps();
    }

    public function isStarredBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if (array_key_exists('is_starred', $this->attributes)) {
            return (bool) $this->attributes['is_starred'];
        }

        return $this->starredByUsers()
            ->where('user_id', $user->getKey())
            ->exists();
    }

    public function scopeWithIsStarredFor(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query;
        }

        return $query->withExists([
            'starredByUsers as is_starred' => fn ($relationQuery) => $relationQuery->where('user_id', $user->getKey()),
        ]);
    }
}
