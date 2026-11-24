<x-filament-panels::page>
    <section aria-label="Temas disponibles" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($this->getThemes() as $theme)
            <article @class([
                'flex flex-col gap-3 rounded-xl border bg-white p-4 shadow-sm dark:bg-gray-900',
                'border-primary-500 ring-2 ring-primary-500' => $theme->is_active,
                'border-gray-200 dark:border-white/10' => ! $theme->is_active,
            ])>
                <div class="flex h-16 overflow-hidden rounded-lg" aria-hidden="true">
                    @foreach ($this->swatches($theme) as $hex)
                        <span class="flex-1" style="background-color: {{ $hex }}"></span>
                    @endforeach
                </div>
                <div class="flex items-center justify-between gap-2">
                    <h3 class="text-base font-semibold">{{ $theme->name }}</h3>
                    @if ($theme->is_active)
                        <x-filament::badge color="success">En uso</x-filament::badge>
                    @endif
                </div>
                <p class="text-sm text-gray-600 dark:text-gray-400">{{ $theme->description }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Títulos: {{ $this->fontLabel($theme) }}{{ ($theme->tokens['uppercase_headings'] ?? false) ? ', en mayúsculas' : '' }} · Bordes de {{ $theme->tokens['radius'] ?? 8 }} px</p>
                @unless ($theme->is_active)
                    <div class="mt-auto">{{ ($this->activateAction)(['theme' => $theme->id]) }}</div>
                @endunless
            </article>
        @endforeach
    </section>

    @if ($this->activeTheme())
        <form wire:submit="save" class="space-y-4">
            {{ $this->form }}
            <div class="flex gap-3">
                <x-filament::button type="submit">Guardar colores</x-filament::button>
                <x-filament::button color="gray" wire:click="resetOverrides" type="button">Volver a los colores del tema</x-filament::button>
            </div>
        </form>
    @endif

</x-filament-panels::page>
