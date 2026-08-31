<?php

namespace App\Filament\Resources\Samples;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\KeyValue;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\ViewAction;
use Illuminate\Support\Facades\Auth;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\Samples\Pages\ListSamples;
use App\Filament\Resources\Samples\Pages\CreateSample;
use App\Filament\Resources\Samples\Pages\EditSample;
use App\Filament\Resources\Samples\Pages\ViewSample;
use App\Filament\Resources\SampleResource\Pages;
use App\Filament\Resources\SampleResource\RelationManagers;
use App\Filament\Resources\Samples\SampleInfolistSchema;
use App\Models\Sample;
use App\Models\ProcessingStepTemplate;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Grouping\Group;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Infolists;

class SampleResource extends Resource
{
    protected static ?string $model = Sample::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('unique_ref')
                    ->required()
                    ->maxLength(255),
                Select::make('container_id')
                    ->relationship('container', 'name')
                    ->nullable()
                    ->label('Container'),
                Select::make('source_material_id')
                    ->relationship('sourceMaterial', 'unique_ref')
                    ->required(),
                Section::make('Technical Information')
                    ->collapsed()
                    ->columns(2)
                    ->schema([
                        TextInput::make('description')
                            ->maxLength(255),
                        TextInput::make('width_mm')
                            ->numeric(),
                        TextInput::make('height_mm')
                            ->numeric(),
                        TextInput::make('thickness_mm')
                            ->numeric(),
                        KeyValue::make('properties'),
                    ]),
                Section::make('Processing')
                    ->collapsed()
                    ->schema([
                        Repeater::make('processingSteps')
                            ->relationship('processingSteps')
                            ->hiddenLabel()
                            ->collapsed()
                            ->schema([

                                // Manual entry fields
                                TextInput::make('name')
                                    ->label('Name')
                                    ->columnSpanFull()
                                    ->maxWidth('lg')
                                    ->required(),

                                Textarea::make('content')
                                    ->label('Text')
                                    ->rows(3)

                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->addActionLabel('Add Processing Step')
                            ->reorderable()
                            ->collapsible()
                            ->itemLabel(fn(array $state): ?string => $state['name'] ?? 'Processing Step')
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $user = Auth::user();

                $query->withIsStarredFor($user);

                if ($user) {
                    $query->orderByDesc('is_starred');
                }

                $query->orderBy('unique_ref');
            })
            ->columns([
                IconColumn::make('is_starred')
                    ->label('')
                    ->visible(fn () => Auth::check())
                    ->size('sm')
                    ->icon(fn (bool $state) => $state ? 'heroicon-s-star' : 'heroicon-o-star')
                    ->tooltip(fn (Sample $record) => $record->isStarredBy(Auth::user()) ? 'Remove star' : 'Star this sample')
                    ->extraAttributes(['class' => 'cursor-pointer'])
                    ->action(function (Sample $record) {
                        $user = Auth::user();

                        if (! $user) {
                            return;
                        }

                        $alreadyStarred = $user->starredSamples()
                            ->where('sample_id', $record->getKey())
                            ->exists();

                        if ($alreadyStarred) {
                            $user->starredSamples()->detach($record->getKey());
                        } else {
                            $user->starredSamples()->attach($record->getKey());
                        }

                        $record->setAttribute('is_starred', ! $alreadyStarred);
                    }),
                TextColumn::make('unique_ref')
                    ->formatStateUsing(fn(Sample $record) => $record->sourceMaterial->unique_ref . '-' . $record->unique_ref)
                    ->searchable(),
                TextColumn::make('sourceMaterial.name')
                    ->searchable(),
                TextColumn::make('width_mm')
                ->label('Dimensions (mm)')
                ->formatStateUsing(fn(Sample $record) => $record->width_mm . ' x ' . $record->height_mm . ' x ' . $record->thickness_mm)
            ])
            ->filters([
                //
            ])
            ->groups([
                Group::make('sourceMaterial.grade')
                    ->collapsible(),
            ])
            ->defaultGroup('sourceMaterial.grade')
            ->recordUrl(null)
            ->recordAction('view-slideover')
            ->recordActions([
                \Filament\Actions\Action::make('view-slideover')
                ->label('View')
                ->hiddenLabel()
                ->icon('heroicon-o-eye')
                ->color('info')
                ->button()
                ->schema(SampleInfolistSchema::schema(collapsed: false))
                ->slideOver()
                ,
                ViewAction::make()
                ->hiddenLabel()
                ->color('success')
                ->button()
                ->icon('heroicon-o-arrow-top-right-on-square'),
                EditAction::make()
                ->hiddenLabel()
                ->button()
                ->icon('heroicon-o-pencil'),
                DeleteAction::make()
                ->hiddenLabel()
                ->button()
                ->icon('heroicon-o-trash')
                ->requiresConfirmation()
                ->modalHeading('Delete Sample')
                ->modalDescription('Are you sure you want to delete this sample? This action cannot be undone.')
                ->modalSubmitActionLabel('Yes, delete it'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components(SampleInfolistSchema::schema());
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSamples::route('/'),
            'create' => CreateSample::route('/create'),
            'edit' => EditSample::route('/{record}/edit'),
            'view' => ViewSample::route('/{record}/view'),
        ];
    }
}
