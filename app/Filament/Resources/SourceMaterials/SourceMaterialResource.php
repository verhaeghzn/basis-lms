<?php

namespace App\Filament\Resources\SourceMaterials;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Fieldset;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Auth;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\ReplicateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\SourceMaterials\RelationManagers\NotesRelationManager;
use App\Filament\Resources\SourceMaterials\RelationManagers\ProcessingStepsRelationManager;
use App\Filament\Resources\SourceMaterials\RelationManagers\SamplesRelationManager;
use App\Filament\Resources\SourceMaterials\Pages\ListSourceMaterials;
use App\Filament\Resources\SourceMaterials\Pages\CreateSourceMaterial;
use App\Filament\Resources\SourceMaterials\Pages\EditSourceMaterial;
use App\Filament\Resources\SourceMaterials\Pages\ViewSourceMaterial;
use App\Filament\Resources\SourceMaterialResource\Pages;
use App\Filament\Resources\SourceMaterialResource\RelationManagers;
use App\Models\SourceMaterial;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Forms;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Illuminate\Support\Collection;
use Filament\Infolists;
use Filament\Infolists\Components\CodeEntry;
use Filament\Tables\Filters\SelectFilter;
use Phiki\Grammar\Grammar;

class SourceMaterialResource extends Resource
{
    protected static ?string $model = SourceMaterial::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?int $navigationSort = 1;

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                // ─────────────────────────── Main Grid Layout ───────────────────────────
                Grid::make()
                    ->columns(2)
                    ->schema([
                        Grid::make()
                            ->columnSpan(1)
                            ->columns(1)
                            ->schema([
                                // ─────────────────────────── Overview ───────────────────────────
                                Section::make('Overview')
                                    ->columnSpan(1)
                                    ->columns(2)
                                    ->schema([
                                        TextEntry::make('unique_ref')->label('Reference'),
                                        TextEntry::make('name')->label('Name'),
                                        TextEntry::make('supplier')->label('Supplier'),
                                        TextEntry::make('supplier_identifier')->label('Supplier ID'),
                                        TextEntry::make('grade')->label('Grade'),
                                        TextEntry::make('description')
                                            ->label('Description')
                                            ->markdown()
                                            ->columnSpanFull(),
                                    ]),
                                // ─────────────────────────── Properties ───────────────────────────
                                Section::make('Properties')
                                    ->columnSpan(1)
                                    ->collapsible()
                                    ->collapsed(true)
                                    ->schema([
                                        CodeEntry::make('properties')
                                            ->grammar(Grammar::Json)
                                            ->label('')
                                            ->visible(fn($record) => !empty($record->properties)),
                                    ]),
                            ]),

                        // ─────────────────────────── Right Column: Dimensions & Composition ───────────────────────────
                        Grid::make()
                            ->columnSpan(1)
                            ->columns(1)
                            ->schema([
                                Section::make('Dimensions (mm)')
                                    ->columns(3)
                                    ->schema([
                                        TextEntry::make('width_mm')->label('Width'),
                                        TextEntry::make('height_mm')->label('Height'),
                                        TextEntry::make('thickness_mm')->label('Thickness'),
                                    ]),
                                ViewEntry::make('composition_bar')
                                    ->view('filament.view-entries.composition-bar-widget'),
                            ]),
                    ]),
            ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Administration')
                    ->schema([
                        TextInput::make('unique_ref')
                            ->required()
                            ->helperText('Warning: changes in this ref will not propagate to existing samples')
                            ->unique('source_materials', 'unique_ref'),
                        TextInput::make('name')
                            ->required(),
                        TextInput::make('supplier')
                            ->default('TATA Steel // Arjan Rijkenberg'),
                        TextInput::make('supplier_identifier'),
                        Textarea::make('description')
                            ->rows(3)
                    ]),
                Section::make('Technical')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        TextInput::make('grade')->columnSpan(1),
                        Fieldset::make('Dimensions')
                            ->columnSpan(1)
                            ->columns(3)
                            ->schema([
                                TextInput::make('width_mm')->numeric()->columnSpan(1),
                                TextInput::make('height_mm')->numeric()->columnSpan(1),
                                TextInput::make('thickness_mm')->numeric()->columnSpan(1),
                            ]),
                        CodeEditor::make('composition')
                            ->formatStateUsing(function ($state) {
                                return json_encode($state, JSON_PRETTY_PRINT);
                            })
                            ->language(Language::Json)
                            ->columnSpanFull(),
                        CodeEditor::make('properties')
                            ->formatStateUsing(function ($state) {
                                return json_encode($state, JSON_PRETTY_PRINT);
                            })
                            ->language(Language::Json)
                            ->columnSpanFull(),
                    ]),
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

                // Grouping is sequential: same grades must be contiguous in the result set.
                $query->orderBy('grade')->orderBy('name');
            })
            ->paginationPageOptions([25, 50, 100, 250, 500])
            ->defaultPaginationPageOption(100)
            ->columns([
                IconColumn::make('is_starred')
                    ->label('')
                    ->visible(fn () => Auth::check())
                    ->size('sm')
                    ->icon(fn (bool $state) => $state ? 'heroicon-s-star' : 'heroicon-o-star')
                    ->tooltip(fn (SourceMaterial $record) => $record->isStarredBy(Auth::user()) ? 'Remove star' : 'Star this material')
                    ->extraAttributes(['class' => 'cursor-pointer'])
                    ->action(function (SourceMaterial $record) {
                        $user = Auth::user();

                        if (! $user) {
                            return;
                        }

                        $alreadyStarred = $user->starredSourceMaterials()
                            ->where('source_material_id', $record->getKey())
                            ->exists();

                        if ($alreadyStarred) {
                            $user->starredSourceMaterials()->detach($record->getKey());
                        } else {
                            $user->starredSourceMaterials()->attach($record->getKey());
                        }

                        $record->setAttribute('is_starred', ! $alreadyStarred);
                    }),
                TextColumn::make('unique_ref')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('name')
                    ->width(200)
                    ->searchable(),
                TextColumn::make('grade')
                    ->width(100)
                    ->searchable(),
                TextColumn::make('supplier_identifier')
                    ->searchable(),
                TextColumn::make('samples_count')
                    ->counts('samples')
                    ->label('Samples')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->groups([
                Group::make('grade')
                    ->collapsible(),
                Group::make('name')
                    ->collapsible(),
                Group::make('supplier_identifier')
                    ->collapsible()
            ])
            ->defaultGroup('grade')
            ->persistFiltersInSession()
            ->filters([
                SelectFilter::make('grade')
                    ->options(fn () => SourceMaterial::query()
                        ->whereNotNull('grade')
                        ->distinct()
                        ->orderBy('grade')
                        ->pluck('grade', 'grade')
                        ->toArray())
            ])
            ->recordActions([
                EditAction::make(),
                ViewAction::make(),
                ReplicateAction::make()
                    ->schema([
                        TextInput::make('unique_ref')
                            ->required()
                            ->unique('source_materials', 'unique_ref'),
                    ]),
                DeleteAction::make('archive')
                    ->label('Archive')
                    ->icon('heroicon-o-archive-box')
                    ->requiresConfirmation()
                    ->modalHeading('Archive Source Material')
                    ->modalDescription('Are you sure you want to archive this source material? It will be soft deleted and can be restored later.')
                    ->modalSubmitActionLabel('Archive')
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    // BulkAction::make('Composition report')
                    // ->icon('heroicon-m-arrow-down-tray')
                    // ->openUrlInNewTab()
                    // ->deselectRecordsAfterCompletion()
                    //     ->action(function (Collection $records, $livewire) {
                    //        $livewire->redirect(route('composition-report', ['source_materials' => $records->pluck('id')->toArray()]), true);
                    //     })
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ProcessingStepsRelationManager::class,
            NotesRelationManager::class,
            SamplesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSourceMaterials::route('/'),
            'create' => CreateSourceMaterial::route('/create'),
            'edit' => EditSourceMaterial::route('/{record}/edit'),
            'view' => ViewSourceMaterial::route('/{record}/view'),
        ];
    }
}
