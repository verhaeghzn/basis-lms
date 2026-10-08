<?php

namespace App\Filament\Resources\Samples\Actions;

use App\Models\Container;
use App\Models\Sample;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Validation\ValidationException;

class PlaceSampleInContainerAction
{
    public static function make(): Action
    {
        return Action::make('placeInContainer')
            ->label(fn (Sample $record): string => $record->storageLocation() ? 'Move sample' : 'Add to container')
            ->icon(fn (Sample $record): string => $record->storageLocation() ? 'heroicon-o-arrows-right-left' : 'heroicon-o-plus')
            ->color(fn (Sample $record): string => $record->storageLocation() ? 'gray' : 'primary')
            ->size('sm')
            ->modalHeading(fn (Sample $record): string => $record->storageLocation() ? 'Move sample' : 'Add to a container')
            ->modalDescription('Store this plate in a container that already exists, or create one and put the plate in the first open slot.')
            ->modalSubmitActionLabel('Store sample')
            ->modalWidth('lg')
            ->fillForm(fn (Sample $record): array => self::defaults($record))
            ->schema(self::schema())
            ->action(function (array $data, Sample $record): void {
                self::store($record, $data);
            });
    }

    /**
     * @return array<int, mixed>
     */
    public static function schema(): array
    {
        return [
            Radio::make('mode')
                ->label('Container')
                ->options([
                    'existing' => 'Existing container',
                    'new' => 'Create a new container',
                ])
                ->default(fn (): string => Container::query()->exists() ? 'existing' : 'new')
                ->inline()
                ->live()
                ->visible(fn (): bool => Container::query()->exists()),
            Select::make('container_id')
                ->label('Container')
                ->options(fn () => Container::query()->orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->preload()
                ->live()
                ->required(fn (Get $get): bool => self::mode($get) === 'existing')
                ->visible(fn (Get $get): bool => self::mode($get) === 'existing'),
            TextInput::make('name')
                ->label('Container name')
                ->placeholder('Cabinet 2, shelf B')
                ->maxLength(255)
                ->required(fn (Get $get): bool => self::mode($get) === 'new')
                ->visible(fn (Get $get): bool => self::mode($get) === 'new'),
            TextInput::make('compartments_x_size')
                ->label('Columns')
                ->numeric()
                ->integer()
                ->minValue(1)
                ->maxValue(30)
                ->default(1)
                ->live(onBlur: true)
                ->required(fn (Get $get): bool => self::mode($get) === 'new')
                ->visible(fn (Get $get): bool => self::mode($get) === 'new')
                ->helperText('Leave both sizes at 1 for a single compartment.'),
            TextInput::make('compartments_y_size')
                ->label('Rows')
                ->numeric()
                ->integer()
                ->minValue(1)
                ->maxValue(30)
                ->default(1)
                ->live(onBlur: true)
                ->required(fn (Get $get): bool => self::mode($get) === 'new')
                ->visible(fn (Get $get): bool => self::mode($get) === 'new'),
            Select::make('position')
                ->label('Slot')
                ->options(fn (Get $get, $livewire): array => self::optionsForForm($get, $livewire->getRecord()))
                ->required(fn (Get $get, $livewire): bool => count(self::optionsForForm($get, $livewire->getRecord())) > 1)
                ->visible(fn (Get $get, $livewire): bool => count(self::optionsForForm($get, $livewire->getRecord())) > 1)
                ->helperText('Only empty slots are listed.'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(Sample $record): array
    {
        $location = $record->storageLocation();

        return [
            'mode' => Container::query()->exists() ? 'existing' : 'new',
            'container_id' => $location['container']->id ?? null,
            'compartments_x_size' => 1,
            'compartments_y_size' => 1,
            'position' => ($location && $location['x'] && $location['y'])
                ? $location['x'].':'.$location['y']
                : '1:1',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function store(Sample $record, array $data): void
    {
        $mode = ($data['mode'] ?? null) === 'existing' ? 'existing' : 'new';

        if ($mode === 'existing') {
            $container = Container::query()->find($data['container_id'] ?? null);

            if (! $container) {
                throw ValidationException::withMessages([
                    'container_id' => 'Choose a container.',
                ]);
            }
        } else {
            $container = Container::query()->create([
                'name' => $data['name'],
                'compartments_x_size' => (int) ($data['compartments_x_size'] ?? 1),
                'compartments_y_size' => (int) ($data['compartments_y_size'] ?? 1),
            ]);
        }

        $options = self::slotsFor($record, $container);
        $position = (string) ($data['position'] ?? '');

        if ($options === []) {
            Notification::make()
                ->title('No open slot')
                ->body('Every slot in that container is taken.')
                ->danger()
                ->send();

            return;
        }

        if (count($options) === 1) {
            $position = (string) array_key_first($options);
        } elseif (! array_key_exists($position, $options)) {
            throw ValidationException::withMessages([
                'position' => 'Choose an open slot.',
            ]);
        }

        [$x, $y] = array_map('intval', explode(':', $position, 2));

        $record->placeInContainer($container, $x, $y);
        $record->refresh();
        $record->unsetRelation('containerPosition');
        $record->unsetRelation('container');

        Notification::make()
            ->title('Stored in '.$container->name)
            ->body('Column '.$x.', row '.$y)
            ->success()
            ->send();
    }

    /**
     * @return array<string, string>
     */
    public static function optionsForForm(Get $get, Sample $sample): array
    {
        if (self::mode($get) === 'new') {
            return self::draftSlots(
                (int) ($get('compartments_x_size') ?: 1),
                (int) ($get('compartments_y_size') ?: 1),
            );
        }

        $container = Container::query()->find($get('container_id'));

        if (! $container) {
            return [];
        }

        return self::slotsFor($sample, $container);
    }

    /**
     * @return array<string, string>
     */
    public static function slotsFor(Sample $sample, Container $container): array
    {
        $taken = $container->positions()
            ->get(['compartment_x', 'compartment_y', 'sample_id'])
            ->reject(fn ($position): bool => (int) $position->sample_id === (int) $sample->id)
            ->map(fn ($position): string => $position->compartment_x.':'.$position->compartment_y)
            ->all();

        $current = $sample->storageLocation();
        $options = [];

        for ($x = 1; $x <= (int) $container->compartments_x_size; $x++) {
            for ($y = 1; $y <= (int) $container->compartments_y_size; $y++) {
                $key = $x.':'.$y;

                if (in_array($key, $taken, true)) {
                    continue;
                }

                $label = "Column {$x}, row {$y}";

                if (
                    $current
                    && $current['container']->is($container)
                    && (int) $current['x'] === $x
                    && (int) $current['y'] === $y
                ) {
                    $label .= ' (current)';
                }

                $options[$key] = $label;
            }
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    public static function draftSlots(int $columns, int $rows): array
    {
        $columns = max(1, min(30, $columns));
        $rows = max(1, min(30, $rows));
        $options = [];

        for ($x = 1; $x <= $columns; $x++) {
            for ($y = 1; $y <= $rows; $y++) {
                $options["{$x}:{$y}"] = "Column {$x}, row {$y}";
            }
        }

        return $options;
    }

    private static function mode(Get $get): string
    {
        $mode = $get('mode');

        if ($mode === 'existing' && Container::query()->exists()) {
            return 'existing';
        }

        if ($mode === 'new') {
            return 'new';
        }

        return Container::query()->exists() ? 'existing' : 'new';
    }
}
