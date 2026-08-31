<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sample;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SampleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $limit = (int) ($validated['limit'] ?? 20);

        $samples = Sample::query()
            ->with(['sourceMaterial', 'containerPosition.container'])
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
            'data' => $samples->map(fn (Sample $sample): array => $this->serializeSample($sample))->values(),
        ]);
    }

    public function show(Sample $sample): JsonResponse
    {
        $sample->load(['sourceMaterial', 'containerPosition.container']);

        return response()->json($this->serializeSample($sample));
    }

    /** @return array<string, mixed> */
    private function serializeSample(Sample $sample): array
    {
        $material = $sample->sourceMaterial;
        $position = $sample->containerPosition;

        return [
            'id' => $sample->unique_ref,
            'unique_ref' => $sample->unique_ref,
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
        ];
    }
}
