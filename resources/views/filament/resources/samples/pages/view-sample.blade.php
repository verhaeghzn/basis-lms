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
    $materialProperties = LabValues::pairs($material?->properties);
    $materialComposition = LabValues::pairs($material?->composition);
    $plateProperties = LabValues::pairs($sample->properties);
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
        <section class="sample-hero">
            <div class="sample-photo">
                @if ($latestPhoto)
                    <img src="{{ $latestPhoto->url() }}" alt="Photo of plate {{ $sample->unique_ref }}">
                    <button
                        type="button"
                        class="sample-photo__add"
                        wire:click="mountAction('addPhoto')"
                    >
                        Add photo
                    </button>
                @else
                    <div class="sample-photo__empty">
                        <svg class="sample-photo__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a48 48 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z" />
                        </svg>
                        <p>No photo of this plate yet.</p>
                        <button type="button" class="sample-text-button" wire:click="mountAction('addPhoto')">
                            Add photo
                        </button>
                    </div>
                @endif
            </div>

            <div class="sample-hero__body">
                <div>
                    <p class="sample-kicker">Plate number</p>
                    <h2 class="sample-plate">{{ $sample->unique_ref }}</h2>
                </div>

                <div class="sample-id-row">
                    <div>
                        <p class="sample-kicker">Full ID</p>
                        <p class="sample-full-id">{{ $sample->fullUniqueRef() }}</p>
                    </div>
                    <button
                        type="button"
                        class="sample-copy"
                        x-data="{ copied: false }"
                        x-on:click="navigator.clipboard.writeText(@js($sample->fullUniqueRef())); copied = true; setTimeout(() => copied = false, 1200)"
                    >
                        <span x-show="! copied">Copy</span>
                        <span x-show="copied" x-cloak>Copied</span>
                    </button>
                </div>

                <div class="sample-chips">
                    @if ($material && $materialUrl)
                        <a class="sample-chip sample-chip--ok" href="{{ $materialUrl }}">
                            {{ $material->unique_ref }}
                            @if ($material->name)
                                · {{ $material->name }}
                            @endif
                        </a>
                    @endif

                    @if ($material?->grade)
                        <span class="sample-chip">Grade {{ $material->grade }}</span>
                    @endif

                    @if ($material?->supplier_identifier)
                        <span class="sample-chip">Supplier ID {{ $material->supplier_identifier }}</span>
                    @endif

                    @if ($plateSize)
                        <a class="sample-chip" href="{{ $editUrl }}">{{ $plateSize }}</a>
                    @else
                        <a class="sample-chip" href="{{ $editUrl }}">Size not recorded</a>
                    @endif

                    @if ($location && $containerUrl)
                        <a class="sample-chip sample-chip--ok" href="{{ $containerUrl }}">
                            {{ $location['container']->name }}
                            @if ($slotLabel)
                                · {{ $slotLabel }}
                            @endif
                        </a>
                    @else
                        <button type="button" class="sample-chip sample-chip--alert" wire:click="mountAction('placeInContainer')">
                            Not stored — add to a container
                        </button>
                    @endif
                </div>

                @if (filled($sample->description))
                    <div class="sample-prose">
                        {!! Str::markdown($sample->description) !!}
                    </div>
                @endif

                <p class="sample-meta">
                    Updated {{ optional($sample->updated_at)->diffForHumans() }}
                    · Created {{ optional($sample->created_at)->format('j M Y') }}
                </p>
            </div>
        </section>

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
                    <div class="sample-storage-empty">
                        <p>This plate is not in a container yet.</p>
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
                    <a class="sample-material-name" href="{{ $materialUrl }}">{{ $material->name ?: $material->unique_ref }}</a>
                    <p class="sample-meta">{{ $material->unique_ref }}</p>

                    <dl class="sample-dl">
                        <dt>Grade</dt>
                        <dd>{{ $material->grade ?: 'Not recorded' }}</dd>
                        <dt>Supplier</dt>
                        <dd>{{ $material->supplier ?: 'Not recorded' }}</dd>
                        <dt>Supplier ID</dt>
                        <dd>{{ $material->supplier_identifier ?: 'Not recorded' }}</dd>
                        <dt>Sheet size</dt>
                        <dd>{{ $materialSize ?: 'Not recorded' }}</dd>
                    </dl>

                    <h3>Properties</h3>
                    @if ($materialProperties !== [])
                        <div class="sample-scroll">
                            <dl class="sample-dl">
                                @foreach ($materialProperties as $label => $value)
                                    <dt>{{ $label }}</dt>
                                    <dd>{{ $value }}</dd>
                                @endforeach
                            </dl>
                        </div>
                    @else
                        <p class="sample-meta">No properties recorded on this material.</p>
                    @endif

                    <h3>Composition</h3>
                    @if ($materialComposition !== [])
                        <div class="sample-pills">
                            @foreach ($materialComposition as $label => $value)
                                <span class="sample-pill">{{ $label }} {{ $value }}</span>
                            @endforeach
                        </div>
                    @else
                        <p class="sample-meta">No composition recorded.</p>
                    @endif

                    @if ($siblingTotal <= 1)
                        <p class="sample-meta">This is the only plate from this material.</p>
                    @endif
                @else
                    <p class="sample-meta">No source material is linked to this plate.</p>
                @endif
            </section>
        </div>

        @if ($plateProperties !== [])
            <section class="sample-card">
                <div class="sample-card__head">
                    <h2>Plate properties</h2>
                    <a class="sample-link" href="{{ $editUrl }}">Edit</a>
                </div>
                <dl class="sample-dl">
                    @foreach ($plateProperties as $label => $value)
                        <dt>{{ $label }}</dt>
                        <dd>{{ $value }}</dd>
                    @endforeach
                </dl>
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
