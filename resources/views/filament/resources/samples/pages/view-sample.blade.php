@php
    use App\Filament\Resources\Containers\ContainerResource;
    use App\Filament\Resources\Samples\SampleResource;
    use App\Filament\Resources\SourceMaterials\SourceMaterialResource;
    use App\Support\LabValues;
    use Illuminate\Support\Str;

    $sample = $this->sample();
    $material = $sample->sourceMaterial;
    $location = $sample->storageLocation();
    $photos = $sample->photos;
    $latestPhoto = $photos->first();
    $plateNumber = LabValues::property($material?->properties, LabValues::PLATE_NUMBER_KEYS);
    $materialBlocks = LabValues::blocks($material?->properties, LabValues::PLATE_NUMBER_KEYS);
    $composition = LabValues::entries($material?->composition);
    usort($composition, fn (array $left, array $right): int => strnatcasecmp($left['label'], $right['label']));
    $plateBlocks = LabValues::blocks($sample->properties);
    $materialTitle = $material?->name ?: $material?->unique_ref;
    $materialRefIsTitle = $material && $material->unique_ref === $materialTitle;
    $plateSize = LabValues::millimetres($sample->width_mm, $sample->height_mm, $sample->thickness_mm);
    $materialSize = $material
        ? LabValues::millimetres($material->width_mm, $material->height_mm, $material->thickness_mm)
        : null;
    $materialUrl = $material
        ? SourceMaterialResource::getUrl('view', ['record' => $material])
        : null;
    $editUrl = SampleResource::getUrl('edit', ['record' => $sample]);
    $containerUrl = $location
        ? ContainerResource::getUrl('view', ['record' => $location['container']])
        : null;
    $slotLabel = ($location && $location['x'] && $location['y'])
        ? 'Column '.$location['x'].', row '.$location['y']
        : null;
    $timeline = $sample->processingTimeline();
    $materialSteps = $timeline->where('scope', 'source-material')->values();
    $plateSteps = $timeline->where('scope', 'sample')->values();
    $notes = $sample->notes->sortByDesc('created_at');
    $siblingTotal = $material ? $material->samples()->count() : 0;
    $siblings = $material
        ? $material->samples()
            ->with(['latestPhoto', 'containerPosition.container', 'container'])
            ->whereKeyNot($sample->id)
            ->orderBy('unique_ref')
            ->limit(8)
            ->get()
        : collect();
@endphp

<x-filament-panels::page>
    <div class="sample-dossier">
        @if ($latestPhoto)
            <section class="sample-photo-band">
                <img src="{{ $latestPhoto->url() }}" alt="Photo of plate {{ $sample->unique_ref }}">
                <button
                    type="button"
                    class="sample-photo__add"
                    wire:click="mountAction('addPhoto')"
                >
                    Add photo
                </button>
            </section>
        @endif

        @if (filled($sample->description) || $plateSize)
            <section class="sample-card sample-card--quiet">
                @if ($plateSize)
                    <p class="sample-meta"><a class="sample-link" href="{{ $editUrl }}">{{ $plateSize }}</a></p>
                @endif
                @if (filled($sample->description))
                    <div class="sample-prose">
                        {!! Str::markdown($sample->description) !!}
                    </div>
                @endif
            </section>
        @endif

        <p class="sample-meta">
            <button
                type="button"
                class="sample-copy"
                x-data="{ copied: false }"
                x-on:click="navigator.clipboard.writeText(@js($sample->fullUniqueRef())); copied = true; setTimeout(() => copied = false, 1200)"
            >
                <span x-show="! copied">Copy full ID</span>
                <span x-show="copied" x-cloak>Copied</span>
            </button>
            · Updated {{ optional($sample->updated_at)->diffForHumans() }}
            · Created {{ optional($sample->created_at)->format('j M Y') }}
        </p>

        @if ($photos->isNotEmpty())
            <section class="sample-card">
                <div class="sample-card__head">
                    <h2>Photos</h2>
                    <button type="button" class="sample-text-button" wire:click="mountAction('addPhoto')">Add another</button>
                </div>
                <div class="sample-photos">
                    @foreach ($photos as $photo)
                        <figure wire:key="photo-{{ $photo->id }}">
                            <img src="{{ $photo->url() }}" alt="{{ $photo->description ?: 'Photo of plate '.$sample->unique_ref }}">
                            <figcaption>
                                {{ optional($photo->created_at)->diffForHumans() }}
                                @if ($photo->description)
                                    <span>{{ $photo->description }}</span>
                                @endif
                                <button
                                    type="button"
                                    wire:click="deletePhoto({{ $photo->id }})"
                                    wire:confirm="Remove this photo?"
                                >
                                    Remove
                                </button>
                            </figcaption>
                        </figure>
                    @endforeach
                </div>
            </section>
        @endif

        <div class="sample-grid">
            <section class="sample-card">
                <div class="sample-card__head">
                    <h2>Where it is</h2>
                </div>

                @if ($location && $containerUrl)
                    <a class="sample-link" href="{{ $containerUrl }}">{{ $location['container']->name }}</a>
                    <p class="sample-slot">{{ $slotLabel ?? 'Position not set' }}</p>
                    <p class="sample-meta">
                        {{ $location['container']->compartments_x_size }} × {{ $location['container']->compartments_y_size }} grid
                    </p>
                    <div class="sample-actions">
                        <x-filament::button size="sm" color="gray" icon="heroicon-o-arrows-right-left" wire:click="mountAction('placeInContainer')">
                            Move
                        </x-filament::button>
                        <x-filament::button size="sm" color="gray" tag="a" href="{{ $containerUrl }}" icon="heroicon-o-arrow-top-right-on-square">
                            Open container
                        </x-filament::button>
                        <button
                            type="button"
                            class="sample-text-button sample-text-button--danger"
                            wire:click="removeFromStorage"
                            wire:confirm="Take this plate out of {{ $location['container']->name }}?"
                        >
                            Remove
                        </button>
                    </div>
                @else
                    <p class="sample-status">This plate is not in a container yet.</p>
                    <div class="sample-actions">
                        <x-filament::button size="sm" icon="heroicon-o-plus" wire:click="mountAction('placeInContainer')">
                            Add to container
                        </x-filament::button>
                    </div>
                @endif
            </section>

            <section class="sample-card">
                <div class="sample-card__head">
                    <h2>Source material</h2>
                    @if ($materialUrl)
                        <a class="sample-link" href="{{ $materialUrl }}">Open material</a>
                    @endif
                </div>

                @if ($material)
                    <a class="sample-material-name" href="{{ $materialUrl }}">{{ $materialTitle }}</a>
                    @unless ($materialRefIsTitle)
                        <p class="sample-meta">{{ $material->unique_ref }}</p>
                    @endunless

                    @if ($plateNumber || $material->grade || $material->supplier || $material->supplier_identifier || $materialSize)
                        <dl class="sample-dl">
                            @if ($plateNumber)
                                <dt>Plate number</dt>
                                <dd>{{ $plateNumber }}</dd>
                            @endif
                            @if ($material->grade)
                                <dt>Grade</dt>
                                <dd>{{ $material->grade }}</dd>
                            @endif
                            @if ($material->supplier)
                                <dt>Supplier</dt>
                                <dd>{{ $material->supplier }}</dd>
                            @endif
                            @if ($material->supplier_identifier)
                                <dt>Supplier ID</dt>
                                <dd>{{ $material->supplier_identifier }}</dd>
                            @endif
                            @if ($materialSize)
                                <dt>Sheet size</dt>
                                <dd>{{ $materialSize }}</dd>
                            @endif
                        </dl>
                    @endif

                    @if ($siblingTotal <= 1)
                        <p class="sample-meta">This is the only plate from this material.</p>
                    @endif
                @else
                    <p class="sample-meta">No source material is linked to this plate.</p>
                @endif
            </section>
        </div>

        @if ($composition !== [])
            <section class="sample-card">
                <div class="sample-card__head">
                    <h2>Composition</h2>
                </div>
                @include('filament.resources.samples.partials.composition', ['entries' => $composition])
            </section>
        @endif

        @if ($materialBlocks !== [])
            <section class="sample-card">
                <div class="sample-card__head">
                    <h2>Material properties</h2>
                </div>
                @include('filament.resources.samples.partials.value-blocks', ['blocks' => $materialBlocks])
            </section>
        @endif

        @if ($plateBlocks !== [])
            <section class="sample-card">
                <div class="sample-card__head">
                    <h2>Plate properties</h2>
                    <a class="sample-link" href="{{ $editUrl }}">Edit</a>
                </div>
                @include('filament.resources.samples.partials.value-blocks', ['blocks' => $plateBlocks])
            </section>
        @endif

        <section class="sample-card">
            <div class="sample-card__head">
                <h2>Processing</h2>
                {{ $this->addProcessingStepAction }}
            </div>

            @if ($materialSteps->isEmpty() && $plateSteps->isEmpty())
                <p class="sample-meta">No processing recorded for this plate or its material.</p>
            @else
                @if ($materialSteps->isNotEmpty())
                    <h3>From the material</h3>
                    <div class="sample-steps">
                        @foreach ($materialSteps as $step)
                            @include('filament.resources.samples.partials.processing-step', ['step' => $step])
                        @endforeach
                    </div>
                @endif

                <h3>On this plate</h3>
                @if ($plateSteps->isEmpty())
                    <p class="sample-meta">No steps recorded on this plate yet.</p>
                @else
                    <div class="sample-steps">
                        @foreach ($plateSteps as $step)
                            @include('filament.resources.samples.partials.processing-step', ['step' => $step])
                        @endforeach
                    </div>
                @endif
            @endif
        </section>

        @if ($siblings->isNotEmpty())
            <section class="sample-card">
                <div class="sample-card__head">
                    <h2>Other plates from this material</h2>
                    @if ($materialUrl)
                        <a class="sample-link" href="{{ $materialUrl }}">{{ $siblingTotal }} total</a>
                    @endif
                </div>
                <div class="sample-siblings">
                    @foreach ($siblings as $sibling)
                        @php
                            $siblingLocation = $sibling->storageLocation();
                            $siblingSize = LabValues::millimetres($sibling->width_mm, $sibling->height_mm, $sibling->thickness_mm);
                        @endphp
                        <a class="sample-sibling" href="{{ SampleResource::getUrl('view', ['record' => $sibling]) }}">
                            <span class="sample-sibling__plate">{{ $sibling->unique_ref }}</span>
                            <span class="sample-sibling__meta">
                                {{ $siblingSize ?: 'Size not recorded' }}
                                @if ($siblingLocation)
                                    · {{ $siblingLocation['container']->name }}
                                @endif
                            </span>
                        </a>
                    @endforeach
                </div>
                @if ($siblingTotal - 1 > $siblings->count())
                    <p class="sample-meta">Showing {{ $siblings->count() }} of {{ $siblingTotal - 1 }} other plates.</p>
                @endif
            </section>
        @endif

        <section class="sample-card">
            <div class="sample-card__head">
                <h2>Notes</h2>
            </div>

            <form wire:submit="saveNote" class="sample-note-form">
                <textarea
                    wire:model="noteContent"
                    rows="3"
                    class="sample-note-input"
                    placeholder="Orientation, damage, what to do next…"
                ></textarea>
                @error('noteContent')
                    <p class="sample-error">{{ $message }}</p>
                @enderror
                <x-filament::button type="submit" size="sm">Save note</x-filament::button>
            </form>

            <div class="sample-notes">
                @forelse ($notes as $note)
                    <article class="sample-note" wire:key="note-{{ $note->id }}">
                        <p class="sample-note__meta">
                            {{ $note->author->name ?? 'Unknown' }}
                            · {{ optional($note->created_at)->diffForHumans() }}
                        </p>
                        <p>{{ $note->content }}</p>
                    </article>
                @empty
                    <p class="sample-meta">No notes yet.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-filament-panels::page>
