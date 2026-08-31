<?php

namespace App\Filament\Resources\Containers\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use App\Models\Sample;
use Filament\Actions\DeleteAction;
use App\Filament\Resources\Containers\ContainerResource;
use App\Filament\Resources\Samples\SampleResource;
use App\Models\ContainerPosition;
use Filament\Actions;
use Filament\Resources\Pages\Page;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewContainer extends Page
{
    use InteractsWithRecord;
   
    protected static string $resource = ContainerResource::class;

    protected string $view = 'filament.pages.view-container';

    protected static ?string $title = 'View Container';

    public function mount(int | string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getHeaderActions(): array
    {
        return [
            Action::make('addPosition')
                ->label('Add Position')
                ->icon('heroicon-o-plus')
                ->schema([
                    TextInput::make('x')
                        ->label('X Position')
                        ->required()
                        ->numeric()
                        ->minValue(1)
                        ->default(fn ($arguments) => $arguments['x'] ?? null),
                    TextInput::make('y')
                        ->label('Y Position')
                        ->required()
                        ->numeric()
                        ->minValue(1)
                        ->default(fn ($arguments) => $arguments['y'] ?? null),
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
                ->fillForm(function (array $arguments): array {
                    return [
                        'x' => $arguments['x'] ?? null,
                        'y' => $arguments['y'] ?? null,
                    ];
                })
                ->action(function (array $data): void {
                    if ($data['position_type'] === 'sample' && $data['sample_id']) {
                        $this->record->setPositionSample($data['x'], $data['y'], $data['sample_id']);
                        \Filament\Notifications\Notification::make()
                            ->title('Sample added to position')
                            ->success()
                            ->send();
                    } elseif ($data['position_type'] === 'custom' && $data['custom_name']) {
                        $this->record->setPositionCustomName($data['x'], $data['y'], $data['custom_name']);
                        \Filament\Notifications\Notification::make()
                            ->title('Custom name added to position')
                            ->success()
                            ->send();
                    }
                }),
            Action::make('printQR')
                ->label('Print QR Code')
                ->icon('heroicon-o-printer')
                ->action(fn () => redirect()->route('qr-code.show', ['containerId' => $this->record->id])),
            Action::make('edit')
                ->label('Edit Container')
                ->icon('heroicon-o-pencil')
                ->action(fn () => redirect(ContainerResource::getUrl('edit', [
                    'record' => $this->record->id,
                ]))),
            DeleteAction::make()
                ->visible(fn () => Auth::user()?->isAdmin() ?? false)
        ];
    }

    public function deletePosition(int $x, int $y)
    {
        $this->record->clearPosition($x, $y);
        \Filament\Notifications\Notification::make()
            ->title('Position cleared')
            ->success()
            ->send();
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

    public function openAddPositionModal(int $x, int $y): void
    {
        $this->mountAction('addPosition', ['x' => $x, 'y' => $y]);
    }

    public function confirmDeletePosition(int $x, int $y): void
    {
        $this->deletePosition($x, $y);
    }
}
