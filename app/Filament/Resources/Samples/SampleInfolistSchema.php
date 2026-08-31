<?php

namespace App\Filament\Resources\Samples;

use App\Models\Sample;
use Filament\Schemas\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Support\Enums\TextSize;
use Filament\Actions\Action;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists;

final class SampleInfolistSchema
{
    public static function schema(bool $collapsed = true): array
    {
        return [
            Section::make('Sample Information')
                ->columnSpan(1)
                ->icon('heroicon-o-puzzle-piece')
                ->columns([
                    'sm' => 1,
                    'md' => 2,
                ])
                ->schema([
                    TextEntry::make('unique_ref')
                        ->label('Unique Reference')
                        ->size(TextSize::Large)
                        ->weight('bold'),
                    TextEntry::make('sourceMaterial.unique_ref')
                        ->label('Source Material Reference')
                        ->size(TextSize::Large),
                    TextEntry::make('description')
                        ->markdown()
                        ->columnSpanFull(),
                ]),

            Section::make('Processing Steps')
                ->icon('heroicon-o-list-bullet')
                ->columnSpan(1)
                ->collapsible()
                ->collapsed(true)
                ->headerActions(
                    [
                        Action::make('addProcessingStep')
                            ->label('Add Step')
                            ->size('sm')
                            ->icon('heroicon-o-plus')
                            ->modalWidth('sm')
                            ->schema([
                                TextInput::make('name')
                                    ->label('Step Name')
                                    ->required(),
                                Textarea::make('content')
                                    ->label('Description')
                                    ->required()
                                    ->rows(3),
                            ])
                            ->action(function (array $data, $record) {
                                $record->processingSteps()->create($data);
                            })
                    ]
                )
                ->schema([
                    \Filament\Infolists\Components\ViewEntry::make('processingTimeline')
                        ->hiddenLabel()
                        ->state(fn (Sample $record) => $record->processingTimeline())
                        ->view('filament.infolists.components.processing-timeline'),
                ]),

            Section::make('Container & Location')
                ->icon('heroicon-o-archive-box')
                ->columnSpan(1)
                ->columns([
                    'sm' => 1,
                    'md' => 2,
                ])
                ->collapsed($collapsed)
                ->schema([
                    TextEntry::make('container.name')
                        ->label('Container Name')
                        ->size(TextSize::Large)
                        ->weight('bold'),
                    TextEntry::make('compartment_x')
                        ->label('Position')
                        ->formatStateUsing(fn($record) => "x:{$record->compartment_x}, y:{$record->compartment_y}")
                        ->badge()
                        ->color('info'),
                ]),

            Section::make('Technical Specifications')
                ->icon('heroicon-o-cog-6-tooth')
                ->columns([
                    'sm' => 1,
                    'md' => 2,
                    'lg' => 4,
                ])
                ->columnSpan(1)
                ->collapsed($collapsed)
                ->schema([
                    TextEntry::make('width_mm')
                        ->label('Width')
                        ->suffix(' mm'),
                    TextEntry::make('height_mm')
                        ->label('Height')
                        ->suffix(' mm'),
                    TextEntry::make('thickness_mm')
                        ->label('Thickness')
                        ->suffix(' mm'),
                    KeyValueEntry::make('properties')
                        ->label('Properties')
                        ->columnSpanFull(),
                ]),
            ];
    }
}