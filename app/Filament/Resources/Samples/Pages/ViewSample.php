<?php

namespace App\Filament\Resources\Samples\Pages;

use App\Filament\Resources\Samples\Actions\PlaceSampleInContainerAction;
use App\Filament\Resources\Samples\SampleResource;
use App\Filament\Resources\SourceMaterials\SourceMaterialResource;
use App\Models\Sample;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ViewSample extends ViewRecord
{
    protected static string $resource = SampleResource::class;

    protected string $view = 'filament.resources.samples.pages.view-sample';

    public string $noteContent = '';

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->rememberRelations();
    }

    public function getTitle(): string
    {
        return $this->sample()->fullUniqueRef();
    }

    public function getSubheading(): ?string
    {
        $material = $this->sample()->sourceMaterial;

        if (! $material) {
            return 'No source material linked';
        }

        return $material->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openSourceMaterial')
                ->label('Source material')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->size('sm')
                ->url(fn (): ?string => $this->sample()->source_material_id
                    ? SourceMaterialResource::getUrl('view', ['record' => $this->sample()->source_material_id])
                    : null)
                ->visible(fn (): bool => filled($this->sample()->source_material_id)),
            Action::make('toggleStar')
                ->label(fn (): string => $this->sample()->isStarredBy(Auth::user()) ? 'Starred' : 'Star')
                ->icon(fn (): string => $this->sample()->isStarredBy(Auth::user()) ? 'heroicon-s-star' : 'heroicon-o-star')
                ->color('gray')
                ->size('sm')
                ->action(function (): void {
                    $user = Auth::user();
                    $sample = $this->sample();

                    if (! $user) {
                        return;
                    }

                    if ($sample->isStarredBy($user)) {
                        $user->starredSamples()->detach($sample->getKey());
                    } else {
                        $user->starredSamples()->attach($sample->getKey());
                    }

                    $sample->unsetRelation('starredByUsers');
                }),
            Action::make('addPhoto')
                ->label('Add photo')
                ->icon('heroicon-o-camera')
                ->color('gray')
                ->size('sm')
                ->modalHeading('Add a photo')
                ->modalSubmitActionLabel('Save photo')
                ->schema([
                    FileUpload::make('photo')
                        ->label('Photo')
                        ->image()
                        ->required()
                        ->disk('public')
                        ->directory('photos')
                        ->visibility('public')
                        ->maxSize(12288)
                        ->helperText('A picture of the plate, label, or where it sits in the container.'),
                    Textarea::make('description')
                        ->rows(2)
                        ->maxLength(500),
                ])
                ->action(function (array $data): void {
                    $path = $this->uploadedPath($data['photo'] ?? null);

                    if (! $path) {
                        Notification::make()
                            ->title('Choose a photo to upload')
                            ->danger()
                            ->send();

                        return;
                    }

                    $this->sample()->photos()->create([
                        'user_id' => Auth::id(),
                        'file_path' => $path,
                        'description' => $data['description'] ?? null,
                    ]);

                    $this->sample()->unsetRelation('photos');
                    $this->sample()->unsetRelation('latestPhoto');

                    Notification::make()
                        ->title('Photo added')
                        ->success()
                        ->send();
                }),
            EditAction::make()
                ->color('gray')
                ->size('sm'),
            PlaceSampleInContainerAction::make(),
        ];
    }

    public function addProcessingStepAction(): Action
    {
        return Action::make('addProcessingStep')
            ->label('Add step')
            ->icon('heroicon-o-plus')
            ->size('sm')
            ->modalHeading('Add a processing step')
            ->modalDescription('This step is recorded on the plate. Steps inherited from the source material stay attached to the material.')
            ->modalWidth('lg')
            ->schema([
                TextInput::make('name')
                    ->label('Step name')
                    ->required()
                    ->maxLength(255),
                Textarea::make('content')
                    ->label('What happened')
                    ->required()
                    ->rows(4),
            ])
            ->action(function (array $data): void {
                $this->sample()->processingSteps()->create($data);
                $this->sample()->unsetRelation('processingSteps');

                Notification::make()
                    ->title('Processing step added')
                    ->success()
                    ->send();
            });
    }

    public function saveNote(): void
    {
        $this->validate([
            'noteContent' => ['required', 'string', 'max:2000'],
        ]);

        $this->sample()->notes()->create([
            'content' => $this->noteContent,
            'author_id' => Auth::id(),
        ]);

        $this->noteContent = '';
        $this->sample()->unsetRelation('notes');

        Notification::make()
            ->title('Note saved')
            ->success()
            ->send();
    }

    public function deletePhoto(int $photoId): void
    {
        $photo = $this->sample()->photos()->whereKey($photoId)->first();

        if (! $photo) {
            return;
        }

        Storage::disk('public')->delete($photo->file_path);
        $photo->delete();

        $this->sample()->unsetRelation('photos');
        $this->sample()->unsetRelation('latestPhoto');

        Notification::make()
            ->title('Photo removed')
            ->success()
            ->send();
    }

    public function removeFromStorage(): void
    {
        $this->sample()->removeFromStorage();
        $this->sample()->unsetRelation('containerPosition');
        $this->sample()->unsetRelation('container');

        Notification::make()
            ->title('Removed from container')
            ->success()
            ->send();
    }

    public function sample(): Sample
    {
        /** @var Sample $sample */
        $sample = $this->getRecord();

        return $sample;
    }

    private function rememberRelations(): void
    {
        $this->sample()->load([
            'sourceMaterial',
            'photos',
            'notes.author',
            'processingSteps',
            'sourceMaterial.processingSteps',
            'containerPosition.container',
            'container',
        ]);
    }

    private function uploadedPath(mixed $photo): ?string
    {
        if (is_array($photo)) {
            $photo = collect($photo)
                ->first(fn (mixed $value): bool => is_string($value) && $value !== '');
        }

        return is_string($photo) && $photo !== '' ? $photo : null;
    }
}
