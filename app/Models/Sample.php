<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Sample extends Model
{
    protected $fillable = [
        'unique_ref',
        'container_id',
        'compartment_x',
        'compartment_y',
        'source_material_id',
        'description',
        'width_mm',
        'height_mm',
        'thickness_mm',
        'properties',
    ];

    protected $casts = [
        'properties' => 'array',
    ];

    protected static function booted()
    {
        static::created(function ($sample) {
            \App\Models\TimelineEvent::recordCreated($sample);
        });
    }

    public function sourceMaterial()
    {
        return $this->belongsTo(SourceMaterial::class);
    }

    /**
     * Full unique ID: {source material ref}-{sample ref}, e.g. 25-164-07-CS.
     */
    public function fullUniqueRef(): string
    {
        $prefix = $this->sourceMaterial?->unique_ref;

        if (filled($prefix)) {
            return $prefix.'-'.$this->unique_ref;
        }

        return (string) $this->unique_ref;
    }

    public function getFullUniqueRefAttribute(): string
    {
        return $this->fullUniqueRef();
    }

    public function container()
    {
        return $this->belongsTo(Container::class);
    }

    public function notes()
    {
        return $this->morphMany(Note::class, 'noteable');
    }

    public function processingSteps(): MorphMany
    {
        return $this->morphMany(ProcessingStep::class, 'processable')
            ->orderBy('created_at');
    }

    public function processingTimeline(): Collection
    {
        $this->loadMissing('processingSteps', 'sourceMaterial.processingSteps');

        $materialSteps = $this->sourceMaterial
            ? $this->sourceMaterial
                ->processingSteps
                ->map(function (ProcessingStep $step): array {
                    return [
                        'id' => $step->id,
                        'name' => $step->name,
                        'description' => $step->description,
                        'content' => $step->content,
                        'performed_at' => optional($step->created_at)->format('Y-m-d'),
                        'scope' => 'source-material',
                        'sort_key' => optional($step->created_at)->timestamp ?? 0,
                    ];
                })
                ->sortBy('sort_key')
                ->values()
                ->map(function (array $step): array {
                    unset($step['sort_key']);

                    return $step;
                })
            : collect();

        $sampleSteps = $this->processingSteps
            ->map(function (ProcessingStep $step): array {
                return [
                    'id' => $step->id,
                    'name' => $step->name,
                    'description' => $step->description,
                    'content' => $step->content,
                    'performed_at' => optional($step->created_at)->format('Y-m-d'),
                    'scope' => 'sample',
                    'sort_key' => optional($step->created_at)->timestamp ?? 0,
                ];
            })
            ->sortBy('sort_key')
            ->values()
            ->map(function (array $step): array {
                unset($step['sort_key']);

                return $step;
            });

        return $materialSteps->concat($sampleSteps)->values();
    }

    public function photos(): MorphMany
    {
        return $this->morphMany(Photo::class, 'imageable')->latest();
    }

    public function latestPhoto(): MorphOne
    {
        return $this->morphOne(Photo::class, 'imageable')->latestOfMany();
    }

    public function containerPosition()
    {
        return $this->hasOne(ContainerPosition::class);
    }

    /**
     * Where this plate is stored.
     *
     * Container positions are the current record. Older rows may only have
     * container_id and compartment coordinates on the sample itself.
     *
     * @return array{container: Container, x: int|null, y: int|null}|null
     */
    public function storageLocation(): ?array
    {
        $this->loadMissing('containerPosition.container', 'container');

        $position = $this->containerPosition;

        if ($position?->container) {
            return [
                'container' => $position->container,
                'x' => $position->compartment_x,
                'y' => $position->compartment_y,
            ];
        }

        if ($this->container) {
            return [
                'container' => $this->container,
                'x' => $this->compartment_x,
                'y' => $this->compartment_y,
            ];
        }

        return null;
    }

    public function placeInContainer(Container $container, int $x, int $y): void
    {
        $columns = (int) $container->compartments_x_size;
        $rows = (int) $container->compartments_y_size;

        if ($x < 1 || $y < 1 || $x > $columns || $y > $rows) {
            throw ValidationException::withMessages([
                'position' => 'That slot is outside this container.',
            ]);
        }

        $taken = $container->positions()
            ->where('compartment_x', $x)
            ->where('compartment_y', $y)
            ->where(function (Builder $query): void {
                $query->whereNull('sample_id')
                    ->orWhere('sample_id', '!=', $this->id);
            })
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'position' => 'That slot is already taken.',
            ]);
        }

        DB::transaction(function () use ($container, $x, $y): void {
            ContainerPosition::query()->where('sample_id', $this->id)->delete();

            $container->setPositionSample($x, $y, $this->id);

            $this->forceFill([
                'container_id' => $container->id,
                'compartment_x' => $x,
                'compartment_y' => $y,
            ])->save();
        });
    }

    public function removeFromStorage(): void
    {
        DB::transaction(function (): void {
            ContainerPosition::query()->where('sample_id', $this->id)->delete();

            $this->forceFill([
                'container_id' => null,
                'compartment_x' => null,
                'compartment_y' => null,
            ])->save();
        });
    }

    public function starredByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'sample_user')->withTimestamps();
    }

    public function semphonyAuthorizedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'sample_semphony_user')->withTimestamps();
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
