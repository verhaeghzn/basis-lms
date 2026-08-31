<?php

namespace App\Filament\Resources\Containers;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use App\Filament\Resources\Containers\Pages\ManageContainers;
use App\Filament\Resources\Containers\Pages\EditContainer;
use App\Filament\Resources\Containers\Pages\ViewContainer;
use App\Filament\Resources\Containers\Pages\ViewContainerLite;
use App\Filament\Resources\ContainerResource\Pages;
use App\Filament\Resources\ContainerResource\RelationManagers;
use App\Infolists\Components\ContainerContentEntry;
use App\Models\Container;
use App\Models\ContainerPosition;
use App\Models\Sample;
use Filament\Forms;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Infolists;
use Filament\Notifications\Notification;

class ContainerResource extends Resource
{
    protected static ?string $model = Container::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-archive-box';

    // Rename navigation label to Storage
    protected static ?string $navigationLabel = 'Storage';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull()
                    ->label('Container Name'),

                TextInput::make('compartments_x_size')
                    ->required()
                    ->numeric()
                    ->default(1)
                    ->label('Compartments X Size'),

                TextInput::make('compartments_y_size')
                    ->required()
                    ->numeric()
                    ->default(1)
                    ->label('Compartments Y Size'),
            ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->sortable()
                    ->searchable()
                    ->label('Container Name'),

                TextColumn::make('readable_size')
                    ->sortable()
                    ->label('Compartment Size')
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->recordUrl(fn (Container $record): string => ContainerResource::getUrl('view', ['record' => $record]))
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name'),
                TextEntry::make('readable_size')
                    ->label('Compartments Size'),
                ContainerContentEntry::make('positions')
                    ->columnSpanFull()
                    ->registerActions([
                        Action::make('addPosition')
                            ->label('Add Position')
                            ->button()
                            ->size('xs')
                            ->schema([
                                TextInput::make('x')
                                    ->label('X Position')
                                    ->required()
                                    ->numeric()
                                    ->minValue(1),
                                TextInput::make('y')
                                    ->label('Y Position')
                                    ->required()
                                    ->numeric()
                                    ->minValue(1),
                                Radio::make('position_type')
                                    ->label('Position Type')
                                    ->options([
                                        'sample' => 'Sample',
                                        'custom' => 'Custom Name',
                                    ])
                                    ->default('sample')
                                    ->required()
                                    ->reactive(),
                                Select::make('sampleId')
                                    ->label('Sample')
                                    ->options(function() {
                                        // Get samples that are not assigned to any container position
                                        $assignedSampleIds = ContainerPosition::whereNotNull('sample_id')->pluck('sample_id');
                                        return Sample::query()
                                            ->with('sourceMaterial')
                                            ->whereNotIn('id', $assignedSampleIds)
                                            ->get()
                                            ->mapWithKeys(fn (Sample $sample): array => [
                                                $sample->id => $sample->fullUniqueRef(),
                                            ]);
                                    })
                                    ->searchable()
                                    ->placeholder('Select Sample')
                                    ->visible(fn($get) => $get('position_type') === 'sample'),
                                TextInput::make('customName')
                                    ->label('Custom Name')
                                    ->placeholder('Enter custom name')
                                    ->visible(fn($get) => $get('position_type') === 'custom'),
                            ])
                            ->icon('heroicon-o-pencil')
                            ->action(function(array $data, Container $record, $arguments) {
                                if ($data['position_type'] === 'sample' && $data['sampleId']) {
                                    $record->setPositionSample($data['x'], $data['y'], $data['sampleId']);
                                    Notification::make()
                                        ->title('Sample added to container position')
                                        ->success()
                                        ->send();
                                } elseif ($data['position_type'] === 'custom' && $data['customName']) {
                                    $record->setPositionCustomName($data['x'], $data['y'], $data['customName']);
                                    Notification::make()
                                        ->title('Custom name added to container position')
                                        ->success()
                                        ->send();
                                }
                            })
                            ->modalWidth('md')
                            ->modalHeading('Add Position'),
                        Action::make('editPosition')
                            ->label('Edit Position')
                            ->button()
                            ->size('xs')
                            ->schema(function($arguments) {
                                $position = ContainerPosition::find($arguments['positionId'] ?? null);
                                return [
                                    TextInput::make('x')
                                        ->label('X Position')
                                        ->required()
                                        ->numeric()
                                        ->minValue(1)
                                        ->default($position?->compartment_x),
                                    TextInput::make('y')
                                        ->label('Y Position')
                                        ->required()
                                        ->numeric()
                                        ->minValue(1)
                                        ->default($position?->compartment_y),
                                    Radio::make('position_type')
                                        ->label('Position Type')
                                        ->options([
                                            'sample' => 'Sample',
                                            'custom' => 'Custom Name',
                                        ])
                                        ->default($position?->hasSample() ? 'sample' : 'custom')
                                        ->required()
                                        ->reactive(),
                                    Select::make('sampleId')
                                        ->label('Sample')
                                        ->options(function() use ($position) {
                                            // Get samples that are not assigned to any container position
                                            $assignedSampleIds = ContainerPosition::whereNotNull('sample_id')
                                                ->where('id', '!=', $position?->id ?? 0)
                                                ->pluck('sample_id');
                                            return Sample::query()
                                                ->with('sourceMaterial')
                                                ->whereNotIn('id', $assignedSampleIds)
                                                ->get()
                                                ->mapWithKeys(fn (Sample $sample): array => [
                                                    $sample->id => $sample->fullUniqueRef(),
                                                ]);
                                        })
                                        ->searchable()
                                        ->placeholder('Select Sample')
                                        ->visible(fn($get) => $get('position_type') === 'sample')
                                        ->default($position?->sample_id),
                                    TextInput::make('customName')
                                        ->label('Custom Name')
                                        ->placeholder('Enter custom name')
                                        ->visible(fn($get) => $get('position_type') === 'custom')
                                        ->default($position?->custom_name),
                                ];
                            })
                            ->icon('heroicon-o-pencil')
                            ->action(function(array $data, Container $record, $arguments) {
                                $position = ContainerPosition::find($arguments['positionId'] ?? null);
                                if ($position) {
                                    if ($data['position_type'] === 'sample' && $data['sampleId']) {
                                        $record->setPositionSample($data['x'], $data['y'], $data['sampleId']);
                                        Notification::make()
                                            ->title('Position updated with sample')
                                            ->success()
                                            ->send();
                                    } elseif ($data['position_type'] === 'custom' && $data['customName']) {
                                        $record->setPositionCustomName($data['x'], $data['y'], $data['customName']);
                                        Notification::make()
                                            ->title('Position updated with custom name')
                                            ->success()
                                            ->send();
                                    }
                                }
                            })
                            ->modalWidth('md')
                            ->modalHeading('Edit Position'),
                        Action::make('clearPosition')
                            ->label('Clear Position')
                            ->button()
                            ->size('xs')
                            ->color('danger')
                            ->icon('heroicon-o-trash')
                            ->action(function(array $data, Container $record, $arguments) {
                                $record->clearPosition($arguments['x'], $arguments['y']);
                                Notification::make()
                                    ->title('Position cleared')
                                    ->success()
                                    ->send();
                            })
                            ->requiresConfirmation()
                            ->modalHeading('Clear Position')
                            ->modalDescription('Are you sure you want to clear this position?'),
                    ])
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageContainers::route('/'),
            'edit' => EditContainer::route('/{record}/edit'),
            'view' => ViewContainer::route('/{record}'),
            'view-lite' => ViewContainerLite::route('/{record}/lite'),
        ];
    }
}
