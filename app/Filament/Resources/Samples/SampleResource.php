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
use App\Models\SourceMaterial;
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
use Filament\Schemas\Components\Utilities\Get;
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
                Select::make('source_material_id')
                    ->relationship('sourceMaterial', 'unique_ref')
                    ->getOptionLabelFromRecordUsing(
                        fn (SourceMaterial $record): string => "{$record->unique_ref} — {$record->name}"
                    )
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live(),
                TextInput::make('unique_ref')
                    ->label('Plate ID')
                    ->required()
                    ->maxLength(255)
                    ->prefix(function (Get $get): string {
                        $material = SourceMaterial::query()->find($get('source_material_id'));

                        return $material ? $material->unique_ref.'-' : '';
                    })
                    ->helperText(function (Get $get): string {
                        $material = SourceMaterial::query()->find($get('source_material_id'));
                        $suffix = trim((string) $get('unique_ref'));

                        if (! $material || $suffix === '') {
                            return 'Suffix only — the source-material prefix is shown left of the field.';
                        }

                        return 'Full unique ID: '.$material->unique_ref.'-'.$suffix;
                    })
                    ->live(onBlur: true)
                    ->suffixAction(
                        Action::make('copyFullUniqueId')
                            ->icon('heroicon-o-clipboard-document')
                            ->tooltip('Copy full unique ID')
                            ->alpineClickHandler(function (Get $get): string {
                                $material = SourceMaterial::query()->find($get('source_material_id'));
                                $suffix = trim((string) $get('unique_ref'));
                                $full = ($material && $suffix !== '')
                                    ? $material->unique_ref.'-'.$suffix
                                    : '';

                                return "window.navigator.clipboard.writeText(".json_encode($full).")";
                            })
                    ),
                Select::make('container_id')
                    ->relationship('container', 'name')
                    ->nullable()
                    ->label('Container'),
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
                    ->label('Unique ID')
                    ->formatStateUsing(fn (Sample $record): string => $record->fullUniqueRef())
                    ->copyable()
                    ->copyMessage('Full unique ID copied')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        $like = '%'.$search.'%';

                        return $query->where(function (Builder $inner) use ($like): void {
                            $inner->where('unique_ref', 'like', $like)
                                ->orWhereHas('sourceMaterial', fn (Builder $material) => $material->where('unique_ref', 'like', $like));
                        });
                    }),
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
