<?php

namespace App\Filament\Resources\Containers\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use App\Models\Sample;
use App\Filament\Resources\Containers\ContainerResource;
use App\Filament\Resources\Samples\SampleResource;
use App\Models\ContainerPosition;
use Filament\Actions;
use Filament\Resources\Pages\Page;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\ViewRecord;

class ViewContainerLite extends Page
{
    use InteractsWithRecord;

    protected static string $resource = ContainerResource::class;

    protected string $view = 'filament.pages.view-container';

    protected static ?string $title = 'View Container';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string
    {
        return $this->record->name;
    }

    public function getHeaderActions(): array
    {
        return [
            Action::make('addPosition')
                ->label('Add Position')
                ->icon('heroicon-o-plus')
                ->modalWidth('sm')
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
                    Select::make('sample_id')
                        ->label('Sample')
                        ->options(function() {
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
                    TextInput::make('custom_name')
                        ->label('Custom Name')
                        ->placeholder('Enter custom name')
                        ->visible(fn($get) => $get('position_type') === 'custom'),
                ])
                ->action(function (array $data): void {
                    if ($data['position_type'] === 'sample' && $data['sample_id']) {
                        $this->record->setPositionSample($data['x'], $data['y'], $data['sample_id']);
                    } elseif ($data['position_type'] === 'custom' && $data['custom_name']) {
                        $this->record->setPositionCustomName($data['x'], $data['y'], $data['custom_name']);
                    }
                }),
            Action::make('confirmDelete')
                ->label('Delete Position')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->modalHeading('Delete Position')
                ->modalDescription('Are you sure you want to delete this position? This action cannot be undone.')
                ->modalSubmitActionLabel('Delete')
                ->modalCancelActionLabel('Cancel')
                ->action(function (array $data): void {
                    $this->record->clearPosition($data['x'], $data['y']);
                    $this->redirect(ContainerResource::getUrl('view-lite', [
                        'record' => $this->record->id,
                    ]));
                })
                ->hidden(),
        ];
    }

    public function gotoSample(int $sampleId): void
    {
        $sample = Sample::find($sampleId);
        if ($sample) {
            // Redirect to the sample edit page or perform any other action
            $this->redirect(SampleResource::getUrl('view', [
                'record' => $sampleId,
            ]));
        }
    }

    public function deletePosition(int $x, int $y)
    {
        $this->record->clearPosition($x, $y);
        return redirect(ContainerResource::getUrl('view-lite', [
            'record' => $this->record->id,
        ]))->with('success', 'Position cleared');
    }

    public function confirmDeletePosition(int $x, int $y)
    {
        $this->mountAction('confirmDelete', [
            'x' => $x,
            'y' => $y
        ]);
    }

    public function openAddPositionModal(int $x, int $y): void
    {
        $this->mountAction('addPosition', [
            'x' => $x, 
            'y' => $y,
            'position_type' => 'sample'
        ]);
    }
}
