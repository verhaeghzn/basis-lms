<?php

namespace App\Filament\Resources\Samples\Pages;

use Filament\Actions\EditAction;
use App\Filament\Resources\Samples\SampleInfolistSchema;
use App\Filament\Resources\Samples\SampleResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSample extends ViewRecord
{
    protected static string $resource = SampleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->size('sm'),
        ];
    }

    public function getTitle(): string
    {
        return $this->record->fullUniqueRef();
    }

    protected function getInfolistSchema(): array
    {
        return SampleInfolistSchema::schema(false);
    }
}
