<?php

use App\Models\Sample;
use App\Models\SourceMaterial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->basisUser = User::factory()->create([
        'email' => 'researcher@tue.nl',
        'role' => 'user',
    ]);
});

it('requires an OAuth access token', function (): void {
    $this->getJson('/api/v1/samples')->assertUnauthorized();
});

it('returns the connected BASIS identity and granted permissions', function (): void {
    Passport::actingAs($this->basisUser, ['profile:read', 'samples:read', 'samples:attach']);

    $this->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('id', $this->basisUser->id)
        ->assertJsonPath('email', 'researcher@tue.nl')
        ->assertJsonPath('permissions.samples.view', true)
        ->assertJsonPath('permissions.samples.attach', true);
});

it('rejects a token without the required sample scope', function (): void {
    Passport::actingAs($this->basisUser, ['profile:read']);

    $this->getJson('/api/v1/samples')
        ->assertForbidden()
        ->assertJsonPath('message', 'The BASIS connection does not grant the required scope.');
});

it('rejects a BASIS account that does not have application access', function (): void {
    $outsideUser = User::factory()->create([
        'email' => 'outside@example.com',
        'role' => 'user',
    ]);

    Passport::actingAs($outsideUser, ['profile:read', 'samples:read']);

    $this->getJson('/api/v1/samples')
        ->assertForbidden();
});

it('searches samples and returns source material context', function (): void {
    $material = SourceMaterial::query()->create([
        'unique_ref' => 'MAT-316L',
        'name' => '316L steel',
        'grade' => '316L',
        'composition' => json_encode(['Fe' => 65, 'Cr' => 17], JSON_THROW_ON_ERROR),
    ]);
    $sample = Sample::query()->create([
        'unique_ref' => 'SAMPLE-042',
        'source_material_id' => $material->id,
        'description' => 'Polished cross-section',
    ]);
    $sample->semphonyAuthorizedUsers()->attach($this->basisUser);
    Sample::query()->create([
        'unique_ref' => 'SAMPLE-OTHER',
        'source_material_id' => $material->id,
    ]);

    Passport::actingAs($this->basisUser, ['samples:read']);
    $response = $this->getJson('/api/v1/samples?search=042');

    $response
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', 'MAT-316L-SAMPLE-042')
        ->assertJsonPath('data.0.source_material.unique_ref', 'MAT-316L')
        ->assertJsonPath('data.0.source_material.name', '316L steel')
        ->assertJsonPath('data.0.permissions.attach_to_semphony_session', true);
});

it('resolves a sample by unique reference', function (): void {
    $sample = Sample::query()->create(['unique_ref' => 'SAMPLE-042']);
    $sample->semphonyAuthorizedUsers()->attach($this->basisUser);
    Passport::actingAs($this->basisUser, ['samples:attach']);

    $this->getJson('/api/v1/samples/SAMPLE-042')
        ->assertOk()
        ->assertJsonPath('id', 'SAMPLE-042');
});

it('does not expose a sample that was not granted to the connected user', function (): void {
    Sample::query()->create(['unique_ref' => 'PRIVATE-042']);
    Passport::actingAs($this->basisUser, ['samples:read', 'samples:attach']);

    $this->getJson('/api/v1/samples?search=PRIVATE-042')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->getJson('/api/v1/samples/PRIVATE-042')
        ->assertForbidden();
});

it('resolves the full material and plate reference', function (): void {
    $material = SourceMaterial::query()->create([
        'unique_ref' => 'MAT-316L',
        'name' => '316L steel',
    ]);
    $sample = Sample::query()->create([
        'unique_ref' => 'PLATE-042',
        'source_material_id' => $material->id,
    ]);
    $sample->semphonyAuthorizedUsers()->attach($this->basisUser);
    Passport::actingAs($this->basisUser, ['samples:attach']);

    $this->getJson('/api/v1/samples/MAT-316L-PLATE-042')
        ->assertOk()
        ->assertJsonPath('id', 'MAT-316L-PLATE-042');
});
