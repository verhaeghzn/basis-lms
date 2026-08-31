<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TimelineEvent extends Model
{
    protected $fillable = [
        'user_id',
        'event_type',
        'subject_type',
        'subject_id',
        'description',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Create a timeline event for a model creation
     */
    public static function recordCreated(Model $model, ?User $user = null): self
    {
        $user = $user ?? auth()->user();

        $description = static::generateDescription($model, 'created');

        return static::create([
            'user_id' => $user?->id,
            'event_type' => 'created',
            'subject_type' => get_class($model),
            'subject_id' => $model->id,
            'description' => $description,
            'metadata' => static::extractMetadata($model),
        ]);
    }

    /**
     * Generate a human-readable description for the event
     */
    protected static function generateDescription(Model $model, string $eventType): string
    {
        $modelName = class_basename($model);
        
        // Custom descriptions for different models
        switch (get_class($model)) {
            case SourceMaterial::class:
                $name = $model->unique_ref ?? $model->name ?? 'Unknown';
                return "created source material {$name}";
            
            case Sample::class:
                $ref = $model->fullUniqueRef();
                return "created sample {$ref}";
            
            case Container::class:
                $name = $model->name ?? 'Unknown';
                return "created container {$name}";
            
            case Asset::class:
                $name = $model->name ?? 'Unknown';
                $subject = $model->subject;
                $subjectName = $subject ? (method_exists($subject, 'unique_ref') ? $subject->unique_ref : ($subject->name ?? class_basename($subject))) : 'Unknown';
                return "uploaded asset {$name} for {$subjectName}";
            
            case Note::class:
                $noteable = $model->noteable;
                $noteableName = $noteable ? (method_exists($noteable, 'unique_ref') ? $noteable->unique_ref : ($noteable->name ?? class_basename($noteable))) : 'Unknown';
                return "added note to {$noteableName}";
            
            default:
                $name = $model->name ?? $model->unique_ref ?? $model->id ?? 'Unknown';
                return "created {$modelName} {$name}";
        }
    }

    /**
     * Extract relevant metadata from the model
     */
    protected static function extractMetadata(Model $model): array
    {
        $metadata = [];

        // Add relevant fields based on model type
        switch (get_class($model)) {
            case SourceMaterial::class:
                $metadata['unique_ref'] = $model->unique_ref;
                $metadata['name'] = $model->name;
                break;
            
            case Sample::class:
                $metadata['unique_ref'] = $model->unique_ref;
                $metadata['source_material_id'] = $model->source_material_id;
                break;
            
            case Container::class:
                $metadata['name'] = $model->name;
                break;
            
            case Asset::class:
                $metadata['name'] = $model->name;
                $metadata['mime_type'] = $model->mime_type;
                break;
        }

        return $metadata;
    }
}

