@php
    use Illuminate\Support\Facades\Storage;
    use Illuminate\Support\Str;

    $sourceMaterials = $this->sourceMaterials;
    $samples = $this->samples;
    $selectedSourceMaterial = $this->selectedSourceMaterial;
    $selectedSample = $this->selectedSample;
    $selectedType = $this->selectedType;
    $selectedId = $this->selectedId;
    $detailTab = $this->detailTab;
    $noteContent = $this->noteContent;
@endphp

<div class="min-h-screen bg-gradient-to-b from-white via-slate-100 to-white">
    <header class="sticky top-0 z-30 border-b border-primary-200/40 bg-gradient-to-r from-primary-600 via-primary-500 to-primary-500 text-white shadow-sm">
        <div class="mx-auto flex max-w-5xl items-center justify-between px-5 py-4">
            <div>
                <p class="text-xs uppercase tracking-[0.32em] text-white/70">Basis Mobile</p>
                <h1 class="mt-1 text-xl font-semibold text-white">Research Data Companion</h1>
            </div>

            <button type="button" data-mobile-qr class="inline-flex items-center gap-2 rounded-full border border-white/40 px-4 py-2 text-sm font-semibold text-white/90 transition hover:bg-white/10">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-4 w-4">
                    <path d="M6.75 3h10.5a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 17.25 21h-10.5A2.25 2.25 0 0 1 4.5 18.75V5.25A2.25 2.25 0 0 1 6.75 3Z" />
                    <path d="M9 4.5h6" />
                    <path d="M12 7.5v3" />
                    <path d="M9.75 10.5h4.5" />
                </svg>
                Scan QR
            </button>
        </div>
    </header>

    <main class="mx-auto w-full max-w-5xl px-5 pb-16 pt-8">
        @if (! $selectedType)
            <div class="space-y-6">
                <div class="flex w-full items-center gap-3">
                    <div class="relative flex-1">
                        <input
                            type="search"
                            wire:model.debounce.500ms="search"
                            class="w-full rounded-full border border-slate-200 bg-white px-5 py-3 pr-12 text-sm text-slate-700 shadow-sm focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-100"
                            placeholder="Search materials or samples"
                        />
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="absolute right-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M18 10.5a7.5 7.5 0 1 1-15 0 7.5 7.5 0 0 1 15 0Z" />
                        </svg>
                    </div>
                </div>

                <section class="overflow-hidden rounded-3xl border border-slate-200/60 bg-white shadow-lg shadow-slate-200/40">
                    <div class="flex items-center justify-between gap-4 border-b border-slate-200/70 bg-slate-50/80 px-6 py-4">
                        <div class="flex rounded-full bg-white p-1 text-sm font-semibold text-slate-500 shadow-inner">
                            <button
                                type="button"
                                wire:click="switchTab('source-materials')"
                                class="inline-flex items-center gap-2 rounded-full px-4 py-2 transition {{ $activeTab === 'source-materials' ? 'bg-primary-500 text-white shadow-sm' : 'text-slate-600 hover:text-primary-600' }}"
                            >
                                Materials
                            </button>
                            <button
                                type="button"
                                wire:click="switchTab('samples')"
                                class="inline-flex items-center gap-2 rounded-full px-4 py-2 transition {{ $activeTab === 'samples' ? 'bg-primary-500 text-white shadow-sm' : 'text-slate-600 hover:text-primary-600' }}"
                            >
                                Samples
                            </button>
                        </div>
                        <button type="button" wire:click="resetSelection" class="text-xs font-semibold text-slate-500 hover:text-primary-600">Clear</button>
                    </div>

                    <div class="divide-y divide-slate-200">
                        @if ($activeTab === 'source-materials')
                            @forelse ($sourceMaterials as $material)
                                @php
                                    $photoPath = optional($material->latestPhoto)->file_path;
                                    $photoUrl = $photoPath ? Storage::disk('public')->url($photoPath) : asset('images/placeholder-doc.jpg');
                                @endphp
                                <button
                                    wire:key="material-{{ $material->id }}"
                                    wire:click="selectRecord('source-material', {{ $material->id }})"
                                    type="button"
                                    class="flex w-full items-center gap-4 px-6 py-4 text-left transition hover:bg-primary-50/60"
                                >
                                    <div class="h-12 w-12 overflow-hidden rounded-xl border border-slate-200 bg-slate-50">
                                        <img src="{{ $photoUrl }}" alt="{{ $material->name }}" class="h-full w-full object-cover" />
                                    </div>
                                    <div class="flex-1 space-y-1">
                                        <p class="text-sm font-semibold text-slate-800">{{ $material->name }}</p>
                                        <p class="text-xs uppercase tracking-wide text-primary-600">{{ $material->unique_ref }}</p>
                                        <p class="text-xs text-slate-500">{{ Str::limit($material->description, 80) }}</p>
                                    </div>
                                    <div class="flex flex-col items-end text-xs text-slate-500">
                                        <span class="rounded-full bg-slate-100 px-3 py-1 font-semibold text-slate-600">{{ $material->samples_count }} samples</span>
                                        <span class="mt-2 text-[11px] text-slate-400">{{ optional($material->updated_at)->format('Y-m-d') }}</span>
                                    </div>
                                </button>
                            @empty
                                <p class="px-6 py-12 text-center text-sm text-slate-500">No source materials found.</p>
                            @endforelse

                            <div class="bg-slate-50/70 px-6 py-4">
                                {{ $sourceMaterials->onEachSide(1)->links(data: ['scrollTo' => false]) }}
                            </div>
                        @else
                            @forelse ($samples as $sample)
                                @php
                                    $photoPath = optional($sample->latestPhoto)->file_path;
                                    $photoUrl = $photoPath ? Storage::disk('public')->url($photoPath) : asset('images/placeholder-doc.jpg');
                                @endphp
                                <button
                                    wire:key="sample-{{ $sample->id }}"
                                    wire:click="selectRecord('sample', {{ $sample->id }})"
                                    type="button"
                                    class="flex w-full items-center gap-4 px-6 py-4 text-left transition hover:bg-primary-50/60"
                                >
                                    <div class="h-12 w-12 overflow-hidden rounded-xl border border-slate-200 bg-slate-50">
                                        <img src="{{ $photoUrl }}" alt="{{ $sample->unique_ref }}" class="h-full w-full object-cover" />
                                    </div>
                                    <div class="flex-1 space-y-1">
                                        <p class="text-sm font-semibold text-slate-800">Sample {{ $sample->fullUniqueRef() }}</p>
                                        <p class="text-xs uppercase tracking-wide text-primary-600">{{ optional($sample->sourceMaterial)->unique_ref }}</p>
                                        <p class="text-xs text-slate-500">{{ Str::limit($sample->description, 80) }}</p>
                                    </div>
                                    <span class="text-[11px] text-slate-400">{{ optional($sample->updated_at)->format('Y-m-d') }}</span>
                                </button>
                            @empty
                                <p class="px-6 py-12 text-center text-sm text-slate-500">No samples found.</p>
                            @endforelse

                            <div class="bg-slate-50/70 px-6 py-4">
                                {{ $samples->onEachSide(1)->links(data: ['scrollTo' => false]) }}
                            </div>
                        @endif
                    </div>
                </section>
            </div>
        @else
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <button type="button" wire:click="resetSelection" class="inline-flex items-center gap-2 rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-600 shadow-sm transition hover:border-primary-200 hover:text-primary-600">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m15.75 19.5-7.5-7.5 7.5-7.5" />
                        </svg>
                        Back to list
                    </button>
                    <div class="hidden text-sm font-semibold text-primary-600 sm:block">
                        {{ $selectedType === 'source-material' ? 'Source material detail' : 'Sample detail' }}
                    </div>
                </div>

                <section class="overflow-hidden rounded-3xl border border-slate-200/60 bg-white shadow-xl shadow-slate-200/50">
                    @if ($selectedType === 'source-material' && $selectedSourceMaterial)
                        @include('mobile.partials.source-material-detail', [
                            'material' => $selectedSourceMaterial,
                            'detailTab' => $detailTab,
                            'noteContent' => $noteContent,
                        ])
                    @elseif ($selectedType === 'sample' && $selectedSample)
                        @include('mobile.partials.sample-detail', [
                            'sample' => $selectedSample,
                            'detailTab' => $detailTab,
                            'noteContent' => $noteContent,
                        ])
                    @endif
                </section>
            </div>
        @endif
    </main>
</div>
