<?php

use App\Models\Sample;
use App\Models\SourceMaterial;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('services.semphony.api_token', 'test-semphony-token');
});

it('requires the configured Semphony token', function (): void {
    $this->getJson('/api/v1/samples')->assertUnauthorized();
});

it('searches samples and returns source material context', function (): void {
    $material = SourceMaterial::query()->create([
        'unique_ref' => 'MAT-316L',
        'name' => '316L steel',
        'grade' => '316L',
        'composition' => json_encode(['Fe' => 65, 'Cr' => 17], JSON_THROW_ON_ERROR),
    ]);
    Sample::query()->create([
        'unique_ref' => 'SAMPLE-042',
        'source_material_id' => $material->id,
        'description' => 'Polished cross-section',
    ]);
    Sample::query()->create([
        'unique_ref' => 'SAMPLE-OTHER',
        'source_material_id' => $material->id,
    ]);

    $response = $this->withToken('test-semphony-token')
        ->getJson('/api/v1/samples?search=042');

    $response
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', 'SAMPLE-042')
        ->assertJsonPath('data.0.source_material.unique_ref', 'MAT-316L')
        ->assertJsonPath('data.0.source_material.name', '316L steel');
});

it('resolves a sample by unique reference', function (): void {
    Sample::query()->create(['unique_ref' => 'SAMPLE-042']);

    $this->withToken('test-semphony-token')
        ->getJson('/api/v1/samples/SAMPLE-042')
        ->assertOk()
        ->assertJsonPath('id', 'SAMPLE-042');
});
