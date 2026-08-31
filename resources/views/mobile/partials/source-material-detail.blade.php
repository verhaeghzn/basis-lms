@php
    use Illuminate\Support\Facades\Storage;
    use Illuminate\Support\Str;

    $photoPath = optional($material->latestPhoto)->file_path;
    $photoUrl = $photoPath ? Storage::disk('public')->url($photoPath) : asset('images/placeholder-doc.jpg');
    $composition = collect($material->composition ?? []);
    $properties = collect($material->properties ?? []);
    $notes = $material->notes->sortByDesc('created_at');
@endphp

<div class="flex h-full flex-col">
    <div class="border-b border-slate-200 bg-white px-6 pb-5 pt-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-xs uppercase tracking-widest text-primary-500/80">Source Material</p>
                <h2 class="mt-1 text-2xl font-semibold text-slate-900">{{ $material->name }}</h2>
                <p class="text-sm text-slate-600">Reference: {{ $material->unique_ref }}</p>
            </div>
            <div class="text-right text-xs text-slate-400">
                <p>Updated {{ optional($material->updated_at)->diffForHumans() }}</p>
                <p>Created {{ optional($material->created_at)->format('Y-m-d') }}</p>
            </div>
        </div>
    </div>

    <div class="flex flex-col gap-6 overflow-y-auto bg-slate-50 px-6 py-6">
        <div x-data>
            <div class="relative overflow-hidden rounded-3xl border border-slate-200 bg-white">
                <img src="{{ $photoUrl }}" alt="{{ $material->name }} photo" class="h-56 w-full object-cover" />
                <button
                    type="button"
                    class="absolute bottom-4 right-4 inline-flex items-center gap-2 rounded-full bg-primary-500 px-4 py-2 text-xs font-semibold text-white shadow transition hover:bg-primary-600"
                    x-on:click="$refs.photoInput.click()"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 13.5H7.5A2.25 2.25 0 0 1 5.25 11.25v-6A2.25 2.25 0 0 1 7.5 3h6A2.25 2.25 0 0 1 15.75 5.25V6" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 7.5h8.25A2.25 2.25 0 0 1 19.5 9.75v8.25a2.25 2.25 0 0 1-2.25 2.25H9.75A2.25 2.25 0 0 1 7.5 18.75V9.75A2.25 2.25 0 0 1 9.75 7.5Z" />
                    </svg>
                    Update Photo
                </button>
                <input type="file" x-ref="photoInput" class="hidden" accept="image/*" wire:model="photoUpload" />
            </div>
            <p class="mt-2 text-xs text-slate-500" wire:loading wire:target="photoUpload">Uploading image…</p>
        </div>

        <nav class="flex gap-2 overflow-x-auto text-sm font-semibold text-slate-600">
            @foreach (['history' => 'History', 'data' => 'Data', 'samples' => 'Samples', 'notes' => 'Notes'] as $tabKey => $tabLabel)
                <button
                    type="button"
                    wire:click="setDetailTab('{{ $tabKey }}')"
                    class="rounded-full px-4 py-2 transition {{ $detailTab === $tabKey ? 'bg-primary-500 text-white shadow-sm' : 'bg-white text-slate-600 hover:bg-primary-50' }}"
                >
                    {{ $tabLabel }}
                </button>
            @endforeach
        </nav>

        <div class="space-y-6">
            @if ($detailTab === 'history')
                <div class="space-y-4">
                    @forelse ($material->processingSteps as $step)
                        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="flex items-start justify-between">
                                <div>
                                    <h3 class="text-base font-semibold text-slate-800">{{ $step->name }}</h3>
                                    <p class="text-xs uppercase tracking-wide text-slate-400">{{ optional($step->created_at)->format('Y-m-d') }}</p>
                                </div>
                                <span class="rounded-full bg-primary-50 px-3 py-1 text-xs font-semibold text-primary-600">Source</span>
                            </div>
                            @if ($step->description)
                                <p class="mt-3 text-sm italic text-slate-500">{{ $step->description }}</p>
                            @endif
                            @if ($step->content)
                                <p class="mt-3 text-sm leading-relaxed text-slate-700">{!! nl2br(e($step->content)) !!}</p>
                            @endif
                        </article>
                    @empty
                        <p class="rounded-2xl border border-dashed border-slate-200 bg-white p-6 text-sm text-slate-500">No processing history recorded yet.</p>
                    @endforelse
                </div>
            @elseif ($detailTab === 'data')
                <div class="grid gap-6 md:grid-cols-2">
                    <div class="space-y-4">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-primary-500/80">Composition</h3>
                        @if ($composition->isNotEmpty())
                            @include('mobile.components.data-table', ['data' => $composition->toArray()])
                        @else
                            <p class="text-sm text-slate-500">No composition data available.</p>
                        @endif
                    </div>
                    <div class="space-y-4">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-primary-500/80">Properties</h3>
                        @if ($properties->isNotEmpty())
                            @include('mobile.components.data-table', ['data' => $properties->toArray()])
                        @else
                            <p class="text-sm text-slate-500">No properties stored for this material.</p>
                        @endif
                    </div>
                </div>
            @elseif ($detailTab === 'samples')
                <div class="space-y-3">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-primary-500/80">Linked Samples</h3>
                    @forelse ($material->samples as $sample)
                        @php
                            $samplePhoto = optional($sample->latestPhoto)->file_path;
                            $sampleUrl = $samplePhoto ? Storage::disk('public')->url($samplePhoto) : asset('images/placeholder-doc.jpg');
                        @endphp
                        <button
                            type="button"
                            wire:click="selectRecord('sample', {{ $sample->id }})"
                            class="flex w-full items-center gap-4 rounded-2xl border border-slate-200 bg-white p-4 text-left shadow-sm transition hover:border-primary-200 hover:bg-primary-50/60"
                        >
                            <div class="h-14 w-14 overflow-hidden rounded-2xl bg-slate-100">
                                <img src="{{ $sampleUrl }}" alt="Sample {{ $sample->unique_ref }}" class="h-full w-full object-cover" />
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-semibold text-slate-800">Sample {{ $sample->fullUniqueRef() }}</p>
                                <p class="text-xs text-slate-500">{{ Str::limit($sample->description, 90) }}</p>
                            </div>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4 text-primary-500">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7" />
                            </svg>
                        </button>
                    @empty
                        <p class="rounded-2xl border border-dashed border-slate-200 bg-white p-6 text-sm text-slate-500">No samples linked to this material yet.</p>
                    @endforelse
                </div>
            @elseif ($detailTab === 'notes')
                <div class="space-y-4">
                    <form wire:submit.prevent="saveNote" class="space-y-3">
                        <textarea
                            wire:model.defer="noteContent"
                            rows="3"
                            class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-100"
                            placeholder="Add an observation or reminder"
                        ></textarea>
                        @error('noteContent')
                            <p class="text-xs font-semibold text-rose-500">{{ $message }}</p>
                        @enderror
                        <div class="flex justify-end">
                            <button type="submit" class="inline-flex items-center gap-2 rounded-full bg-primary-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-600" wire:loading.attr="disabled" wire:target="saveNote">
                                <span wire:loading.class="hidden" wire:target="saveNote">Save Note</span>
                                <span wire:loading wire:target="saveNote" class="flex items-center gap-2">
                                    <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M12 4.5V6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                                        <path d="M12 18v1.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                                        <path d="m6.364 6.364 1.06 1.06" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                                        <path d="m16.576 16.576 1.06 1.06" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                                        <path d="M4.5 12H6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                                        <path d="M18 12h1.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                                        <path d="m6.364 17.636 1.06-1.06" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                                        <path d="m16.576 7.424 1.06-1.06" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                                    </svg>
                                    Saving…
                                </span>
                            </button>
                        </div>
                    </form>

                    <div class="space-y-3">
                        @forelse ($notes as $note)
                            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                                <div class="flex items-center justify-between">
                                    <div class="text-xs text-slate-400">
                                        <span>{{ optional($note->author)->name ?? 'Unknown author' }}</span>
                                        <span class="ml-2">{{ optional($note->created_at)->diffForHumans() }}</span>
                                    </div>
                                </div>
                                <p class="mt-2 text-sm text-slate-700">{!! nl2br(e($note->content)) !!}</p>
                            </article>
                        @empty
                            <p class="rounded-2xl border border-dashed border-slate-200 bg-white p-6 text-sm text-slate-500">No notes yet. Add one above to capture your context.</p>
                        @endforelse
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
