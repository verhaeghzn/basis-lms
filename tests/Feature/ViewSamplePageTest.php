<?php

use App\Filament\Resources\Samples\Pages\ViewSample;
use App\Filament\Resources\SourceMaterials\SourceMaterialResource;
use App\Models\Container;
use App\Models\ContainerPosition;
use App\Models\Sample;
use App\Models\SourceMaterial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create([
        'email' => 'lab@tue.nl',
        'role' => 'admin',
    ]);

    $this->actingAs($this->user);

    $this->material = SourceMaterial::query()->create([
        'unique_ref' => 'HR-CP800-BASE',
        'name' => 'CP800 base sheet',
        'grade' => 'CP800',
        'supplier' => 'Tata Steel',
        'supplier_identifier' => 'HEAT-44',
        'width_mm' => 200,
        'height_mm' => 300,
        'thickness_mm' => 1.5,
        'properties' => [
            'yield_strength' => '820 MPa',
        ],
        'composition' => [
            'Fe' => 97.5,
            'C' => 0.12,
        ],
    ]);

    $this->sample = Sample::query()->create([
        'unique_ref' => 'DOGB-01',
        'source_material_id' => $this->material->id,
        'description' => 'Dogbone cut along the rolling direction.',
        'width_mm' => 12,
        'height_mm' => 80,
        'thickness_mm' => 1.5,
        'properties' => [
            'orientation' => 'RD',
        ],
    ]);
});

it('shows the plate number, source material, and material properties', function (): void {
    $sibling = Sample::query()->create([
        'unique_ref' => 'DOGB-02',
        'source_material_id' => $this->material->id,
    ]);

    Livewire::test(ViewSample::class, ['record' => $this->sample->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('Plate number')
        ->assertSee('DOGB-01')
        ->assertSee('HR-CP800-BASE-DOGB-01')
        ->assertSee('CP800 base sheet')
        ->assertSee('HEAT-44')
        ->assertSee('yield strength')
        ->assertSee('820 MPa')
        ->assertSee('Fe')
        ->assertSee('Not stored')
        ->assertSee('DOGB-02')
        ->assertSee('orientation')
        ->assertSeeHtml(SourceMaterialResource::getUrl('view', ['record' => $this->material]));

    expect($sibling->source_material_id)->toBe($this->material->id);
});

it('stores a plate in a new container from the sample page', function (): void {
    Livewire::test(ViewSample::class, ['record' => $this->sample->getRouteKey()])
        ->callAction('placeInContainer', data: [
            'mode' => 'new',
            'name' => 'Dogbone box',
            'compartments_x_size' => 1,
            'compartments_y_size' => 1,
        ])
        ->assertNotified('Stored in Dogbone box')
        ->assertSee('Dogbone box')
        ->assertSee('Column 1, row 1');

    $container = Container::query()->where('name', 'Dogbone box')->first();

    expect($container)->not->toBeNull()
        ->and($this->sample->fresh()->storageLocation()['container']->is($container))->toBeTrue()
        ->and($this->sample->fresh()->compartment_x)->toBe(1)
        ->and(ContainerPosition::query()->where('sample_id', $this->sample->id)->count())->toBe(1);
});

it('keeps a note on the plate', function (): void {
    Livewire::test(ViewSample::class, ['record' => $this->sample->getRouteKey()])
        ->set('noteContent', 'Edge looks clean')
        ->call('saveNote')
        ->assertSee('Edge looks clean');

    expect($this->sample->notes()->first()->content)->toBe('Edge looks clean');
});

it('prefers a container position over the legacy container columns', function (): void {
    $legacy = Container::query()->create([
        'name' => 'Old drawer',
        'compartments_x_size' => 2,
        'compartments_y_size' => 2,
    ]);
    $current = Container::query()->create([
        'name' => 'Current rack',
        'compartments_x_size' => 3,
        'compartments_y_size' => 1,
    ]);

    $this->sample->forceFill([
        'container_id' => $legacy->id,
        'compartment_x' => 2,
        'compartment_y' => 2,
    ])->save();

    $current->setPositionSample(3, 1, $this->sample->id);

    $location = $this->sample->fresh()->storageLocation();

    expect($location['container']->is($current))->toBeTrue()
        ->and($location['x'])->toBe(3)
        ->and($location['y'])->toBe(1);
});

it('falls back to the legacy container when no position exists', function (): void {
    $legacy = Container::query()->create([
        'name' => 'Old drawer',
        'compartments_x_size' => 2,
        'compartments_y_size' => 2,
    ]);

    $this->sample->forceFill([
        'container_id' => $legacy->id,
        'compartment_x' => 2,
        'compartment_y' => 1,
    ])->save();

    $location = $this->sample->fresh()->storageLocation();

    expect($location['container']->is($legacy))->toBeTrue()
        ->and($location['x'])->toBe(2)
        ->and($location['y'])->toBe(1);
});

it('refuses a slot that is already taken and can leave storage', function (): void {
    $container = Container::query()->create([
        'name' => 'Shared box',
        'compartments_x_size' => 1,
        'compartments_y_size' => 1,
    ]);
    $other = Sample::query()->create([
        'unique_ref' => 'DOGB-09',
        'source_material_id' => $this->material->id,
    ]);
    $other->placeInContainer($container, 1, 1);

    expect(fn () => $this->sample->placeInContainer($container, 1, 1))
        ->toThrow(ValidationException::class);

    $other->removeFromStorage();

    expect($other->fresh()->storageLocation())->toBeNull()
        ->and($other->fresh()->container_id)->toBeNull()
        ->and(ContainerPosition::query()->where('sample_id', $other->id)->exists())->toBeFalse();
});
