@php
    use Filament\Support\Enums\Width;

    $livewire ??= null;

    $renderHookScopes = $livewire?->getRenderHookScopes();
    $maxContentWidth ??= (filament()->getSimplePageMaxContentWidth() ?? Width::Large);

    if (is_string($maxContentWidth)) {
        $maxContentWidth = Width::tryFrom($maxContentWidth) ?? $maxContentWidth;
    }
@endphp

<x-filament-panels::layout.base :livewire="$livewire" class="basis-login-body">
    <div class="basis-login">
        <aside class="basis-login__hero" aria-hidden="true">
            <div class="basis-login__hero-media">
                <img
                    src="{{ asset('images/login-hero.jpg') }}"
                    alt=""
                    class="basis-login__hero-image"
                />
            </div>
            <div class="basis-login__hero-veil"></div>
            <div class="basis-login__hero-copy">
                <p class="basis-login__eyebrow">Multiscale Lab</p>
                <p class="basis-login__tagline">
                    Sample stewardship from source material to result.
                </p>
            </div>
        </aside>

        <div class="basis-login__panel">
            <div class="fi-simple-layout">
                {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIMPLE_LAYOUT_START, scopes: $renderHookScopes) }}

                <div class="fi-simple-main-ctn">
                    <main
                        @class([
                            'fi-simple-main basis-login__card',
                            ($maxContentWidth instanceof Width) ? "fi-width-{$maxContentWidth->value}" : $maxContentWidth,
                        ])
                    >
                        {{ $slot }}
                    </main>
                </div>

                {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIMPLE_LAYOUT_END, scopes: $renderHookScopes) }}
            </div>
        </div>
    </div>
</x-filament-panels::layout.base>
