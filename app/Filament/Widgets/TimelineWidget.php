<?php

namespace App\Filament\Widgets;

use App\Models\TimelineEvent;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

class TimelineWidget extends Widget
{
    protected string $view = 'filament.widgets.timeline-widget';

    protected static ?int $sort = 1;

    public function getViewData(): array
    {
        $events = TimelineEvent::with(['user', 'subject' => function ($morphTo) {
            // Eager load relationships for better performance
            $morphTo->morphWith([
                \App\Models\Sample::class => ['sourceMaterial'],
            ]);
        }])
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function (TimelineEvent $event) {
                return [
                    'id' => $event->id,
                    'description' => $event->description,
                    'user' => $event->user?->name ?? 'Unknown',
                    'user_email' => $event->user?->email,
                    'time' => $event->created_at,
                    'time_ago' => $event->created_at->diffForHumans(),
                    'subject' => $this->getSubjectLink($event),
                    'icon' => $this->getIconForEvent($event),
                    'color' => $this->getColorForEvent($event),
                ];
            });

        return [
            'events' => $events,
        ];
    }

    protected function getSubjectLink(TimelineEvent $event): ?array
    {
        if (!$event->subject) {
            return null;
        }

        $subject = $event->subject;
        $resourceClass = $this->getResourceClassForModel($subject);

        if (!$resourceClass) {
            return null;
        }

        return [
            'label' => $this->getSubjectLabel($subject),
            'url' => $resourceClass::getUrl('view', ['record' => $subject]),
        ];
    }

    protected function getResourceClassForModel($model): ?string
    {
        $modelClass = get_class($model);
        
        // Map models to their resource classes
        $resourceMap = [
            \App\Models\SourceMaterial::class => \App\Filament\Resources\SourceMaterials\SourceMaterialResource::class,
            \App\Models\Sample::class => \App\Filament\Resources\Samples\SampleResource::class,
            \App\Models\Container::class => \App\Filament\Resources\Containers\ContainerResource::class,
            \App\Models\Asset::class => \App\Filament\Resources\Assets\AssetResource::class,
        ];

        return $resourceMap[$modelClass] ?? null;
    }

    protected function getSubjectLabel($subject): string
    {
        // Special handling for Sample to show full reference
        if ($subject instanceof \App\Models\Sample) {
            return $subject->fullUniqueRef();
        }

        if (method_exists($subject, 'unique_ref') && $subject->unique_ref) {
            return $subject->unique_ref;
        }

        if (method_exists($subject, 'name') && $subject->name) {
            return $subject->name;
        }

        return class_basename($subject) . ' #' . $subject->id;
    }

    protected function getIconForEvent(TimelineEvent $event): string
    {
        $subjectType = class_basename($event->subject_type ?? '');

        return match ($subjectType) {
            'SourceMaterial' => 'heroicon-o-inbox-arrow-down',
            'Sample' => 'heroicon-o-puzzle-piece',
            'Container' => 'heroicon-o-archive-box',
            'Asset' => 'heroicon-o-photo',
            'Note' => 'heroicon-o-document-text',
            default => 'heroicon-o-plus-circle',
        };
    }

    protected function getColorForEvent(TimelineEvent $event): string
    {
        $subjectType = class_basename($event->subject_type ?? '');

        return match ($subjectType) {
            'SourceMaterial' => 'primary',
            'Sample' => 'success',
            'Container' => 'warning',
            'Asset' => 'info',
            'Note' => 'gray',
            default => 'gray',
        };
    }
}

