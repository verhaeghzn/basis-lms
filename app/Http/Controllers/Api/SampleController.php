<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sample;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SampleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $basisUser = $this->authorizeBasisUser($request, 'samples:read');

        $validated = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $limit = (int) ($validated['limit'] ?? 20);

        $samples = Sample::query()
            ->with(['sourceMaterial', 'containerPosition.container'])
            ->when(! $basisUser->isAdmin(), function (Builder $query) use ($basisUser): void {
                $query->whereHas('semphonyAuthorizedUsers', fn (Builder $accessQuery): Builder => $accessQuery
                    ->whereKey($basisUser->getKey()));
            })
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.$search.'%';
                $query->where(function (Builder $searchQuery) use ($like): void {
                    $searchQuery
                        ->where('unique_ref', 'like', $like)
                        ->orWhere('description', 'like', $like)
                        ->orWhereHas('sourceMaterial', function (Builder $materialQuery) use ($like): void {
                            $materialQuery
                                ->where('unique_ref', 'like', $like)
                                ->orWhere('name', 'like', $like)
                                ->orWhere('grade', 'like', $like);
                        });
                });
            })
            ->orderBy('unique_ref')
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $samples
                ->map(fn (Sample $sample): array => $this->serializeSample($sample))
                ->values(),
        ]);
    }

    public function show(Request $request, string $sampleReference): JsonResponse
    {
        $basisUser = $this->authorizeBasisUser($request, 'samples:attach');
        $sample = $this->findSample($sampleReference);
        abort_unless($sample instanceof Sample, 404);
        abort_unless($basisUser->canAttachSemphonySample($sample), 403, 'The connected BASIS user may not attach this sample.');
        $sample->load(['sourceMaterial', 'containerPosition.container']);

        return response()->json($this->serializeSample($sample));
    }

    private function findSample(string $reference): ?Sample
    {
        $sample = Sample::query()->where('unique_ref', $reference)->first();
        if ($sample instanceof Sample) {
            return $sample;
        }

        foreach (array_keys(str_split($reference)) as $position) {
            if ($reference[$position] !== '-') {
                continue;
            }
            $materialReference = substr($reference, 0, $position);
            $plateReference = substr($reference, $position + 1);
            $sample = Sample::query()
                ->where('unique_ref', $plateReference)
                ->whereHas('sourceMaterial', fn (Builder $query): Builder => $query->where('unique_ref', $materialReference))
                ->first();
            if ($sample instanceof Sample) {
                return $sample;
            }
        }

        return null;
    }

    private function authorizeBasisUser(Request $request, string $scope): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->canUseSemphony(), 403, 'The connected BASIS user may not access samples.');
        abort_unless($user->tokenCan($scope), 403, 'The BASIS connection does not grant the required scope.');

        return $user;
    }

    /** @return array<string, mixed> */
    private function serializeSample(Sample $sample): array
    {
        $material = $sample->sourceMaterial;
        $position = $sample->containerPosition;

        return [
            'id' => $sample->fullUniqueRef(),
            'unique_ref' => $sample->fullUniqueRef(),
            'plate_id' => $sample->unique_ref,
            'description' => $sample->description,
            'dimensions_mm' => [
                'width' => $sample->width_mm,
                'height' => $sample->height_mm,
                'thickness' => $sample->thickness_mm,
            ],
            'properties' => $sample->properties,
            'source_material' => $material ? [
                'id' => $material->unique_ref,
                'unique_ref' => $material->unique_ref,
                'name' => $material->name,
                'grade' => $material->grade,
                'composition' => $material->composition,
            ] : null,
            'container' => $position?->container ? [
                'id' => $position->container->id,
                'name' => $position->container->name,
                'x' => $position->compartment_x,
                'y' => $position->compartment_y,
            ] : null,
            'url' => url('/app/samples/'.$sample->getKey().'/view'),
            'updated_at' => $sample->updated_at?->toIso8601String(),
            'permissions' => [
                'attach_to_semphony_session' => true,
            ],
        ];
    }
}
