<?php

namespace App\Filament\Resources\Samples;

use App\Filament\Resources\Containers\ContainerResource;
use App\Filament\Resources\SourceMaterials\SourceMaterialResource;
use App\Models\Sample;
use App\Support\LabValues;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\TextSize;

final class SampleInfolistSchema
{
    public static function schema(bool $collapsed = true): array
    {
        return [
            Section::make('Sample')
                ->columnSpan(1)
                ->icon('heroicon-o-puzzle-piece')
                ->columns([
                    'sm' => 1,
                    'md' => 2,
                ])
                ->schema([
                    ImageEntry::make('latestPhoto.file_path')
                        ->hiddenLabel()
                        ->disk('public')
                        ->height('180px')
                        ->columnSpanFull()
                        ->visible(fn (Sample $record): bool => $record->latestPhoto !== null),
                    TextEntry::make('unique_ref')
                        ->label('Plate number')
                        ->copyable()
                        ->copyMessage('Plate number copied')
                        ->size(TextSize::Large)
                        ->weight('bold'),
                    TextEntry::make('full_unique_ref')
                        ->label('Full ID')
                        ->state(fn (Sample $record): string => $record->fullUniqueRef())
                        ->copyable()
                        ->copyMessage('Full ID copied')
                        ->size(TextSize::Large)
                        ->weight('bold'),
                    TextEntry::make('plate_size')
                        ->label('Plate size')
                        ->state(fn (Sample $record): ?string => LabValues::millimetres($record->width_mm, $record->height_mm, $record->thickness_mm))
                        ->placeholder('Not recorded'),
                    TextEntry::make('description')
                        ->markdown()
                        ->columnSpanFull()
                        ->placeholder('No description'),
                ]),

            Section::make('Source material')
                ->icon('heroicon-o-inbox-arrow-down')
                ->columnSpan(1)
                ->columns([
                    'sm' => 1,
                    'md' => 2,
                ])
                ->schema([
                    TextEntry::make('source_material_name')
                        ->label('Material')
                        ->state(fn (Sample $record): string => $record->sourceMaterial
                            ? trim($record->sourceMaterial->name.' · '.$record->sourceMaterial->unique_ref, ' ·')
                            : 'Not linked')
                        ->url(fn (Sample $record): ?string => $record->source_material_id
                            ? SourceMaterialResource::getUrl('view', ['record' => $record->source_material_id])
                            : null)
                        ->color('primary')
                        ->columnSpanFull(),
                    TextEntry::make('sourceMaterial.grade')
                        ->label('Grade')
                        ->placeholder('Not recorded'),
                    TextEntry::make('sourceMaterial.supplier')
                        ->label('Supplier')
                        ->placeholder('Not recorded'),
                    TextEntry::make('sourceMaterial.supplier_identifier')
                        ->label('Supplier ID')
                        ->placeholder('Not recorded')
                        ->copyable(),
                    TextEntry::make('material_size')
                        ->label('Sheet size')
                        ->state(fn (Sample $record): ?string => $record->sourceMaterial
                            ? LabValues::millimetres(
                                $record->sourceMaterial->width_mm,
                                $record->sourceMaterial->height_mm,
                                $record->sourceMaterial->thickness_mm,
                            )
                            : null)
                        ->placeholder('Not recorded'),
                    KeyValueEntry::make('material_properties')
                        ->label('Properties')
                        ->state(fn (Sample $record): array => LabValues::pairs($record->sourceMaterial?->properties))
                        ->visible(fn (Sample $record): bool => LabValues::pairs($record->sourceMaterial?->properties) !== [])
                        ->columnSpanFull(),
                    KeyValueEntry::make('material_composition')
                        ->label('Composition')
                        ->state(fn (Sample $record): array => LabValues::pairs($record->sourceMaterial?->composition))
                        ->visible(fn (Sample $record): bool => LabValues::pairs($record->sourceMaterial?->composition) !== [])
                        ->columnSpanFull(),
                ]),

            Section::make('Processing steps')
                ->icon('heroicon-o-list-bullet')
                ->columnSpan(1)
                ->collapsible()
                ->collapsed(true)
                ->headerActions([
                    Action::make('addProcessingStep')
                        ->label('Add step')
                        ->size('sm')
                        ->icon('heroicon-o-plus')
                        ->modalWidth('sm')
                        ->schema([
                            TextInput::make('name')
                                ->label('Step name')
                                ->required(),
                            Textarea::make('content')
                                ->label('What happened')
                                ->required()
                                ->rows(3),
                        ])
                        ->action(function (array $data, Sample $record): void {
                            $record->processingSteps()->create($data);
                        }),
                ])
                ->schema([
                    \Filament\Infolists\Components\ViewEntry::make('processingTimeline')
                        ->hiddenLabel()
                        ->state(fn (Sample $record) => $record->processingTimeline())
                        ->view('filament.infolists.components.processing-timeline'),
                ]),

            Section::make('Container & location')
                ->icon('heroicon-o-archive-box')
                ->columnSpan(1)
                ->collapsed($collapsed)
                ->schema([
                    TextEntry::make('stored_in')
                        ->label('Stored in')
                        ->state(function (Sample $record): string {
                            $location = $record->storageLocation();

                            if (! $location) {
                                return 'Not stored';
                            }

                            $slot = ($location['x'] && $location['y'])
                                ? ' · column '.$location['x'].', row '.$location['y']
                                : '';

                            return $location['container']->name.$slot;
                        })
                        ->url(function (Sample $record): ?string {
                            $location = $record->storageLocation();

                            if (! $location) {
                                return null;
                            }

                            return ContainerResource::getUrl('view', ['record' => $location['container']]);
                        })
                        ->color(fn (Sample $record): string => $record->storageLocation() ? 'primary' : 'gray'),
                ]),

            Section::make('Plate properties')
                ->icon('heroicon-o-cog-6-tooth')
                ->columnSpan(1)
                ->collapsed($collapsed)
                ->schema([
                    KeyValueEntry::make('properties')
                        ->hiddenLabel()
                        ->visible(fn (Sample $record): bool => filled($record->properties)),
                    TextEntry::make('properties_empty')
                        ->hiddenLabel()
                        ->state('No properties recorded on this plate.')
                        ->visible(fn (Sample $record): bool => blank($record->properties)),
                ]),
        ];
    }
}
