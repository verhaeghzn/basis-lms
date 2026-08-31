<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Samples\SampleResource;
use App\Filament\Resources\SourceMaterials\SourceMaterialResource;
use App\Models\Sample;
use App\Models\SourceMaterial;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class StarredItemsWidget extends Widget
{
    protected string $view = 'filament.widgets.starred-items-widget';

    protected static ?int $sort = 1;

    public function getViewData(): array
    {
        $user = Auth::user();

        // Get starred source materials
        $starredSourceMaterials = SourceMaterial::query()
            ->when($user, function ($query) use ($user) {
                $query->whereHas('starredByUsers', function ($q) use ($user) {
                    $q->where('user_id', $user->getKey());
                });
            })
            ->withCount('samples')
            ->orderBy('name')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'type' => 'source_material',
                    'model' => $item,
                    'reference' => $item->unique_ref,
                    'name' => $item->name,
                    'grade' => $item->grade ?? 'No Grade',
                    'supplier_identifier' => $item->supplier_identifier,
                    'samples_count' => $item->samples_count,
                    'url' => SourceMaterialResource::getUrl('view', ['record' => $item]),
                ];
            });

        // Get starred samples
        $starredSamples = Sample::query()
            ->when($user, function ($query) use ($user) {
                $query->whereHas('starredByUsers', function ($q) use ($user) {
                    $q->where('user_id', $user->getKey());
                });
            })
            ->with('sourceMaterial')
            ->orderBy('unique_ref')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'type' => 'sample',
                    'model' => $item,
                    'reference' => $item->fullUniqueRef(),
                    'name' => $item->sourceMaterial?->name ?? 'Unknown',
                    'grade' => $item->sourceMaterial?->grade ?? 'No Grade',
                    'supplier_identifier' => $item->sourceMaterial?->supplier_identifier,
                    'samples_count' => null,
                    'url' => SampleResource::getUrl('view', ['record' => $item]),
                ];
            });

        return [
            'starredSourceMaterials' => $starredSourceMaterials,
            'starredSamples' => $starredSamples,
            'hasItems' => $starredSourceMaterials->isNotEmpty() || $starredSamples->isNotEmpty(),
        ];
    }
}

