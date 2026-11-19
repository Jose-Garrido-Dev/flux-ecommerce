@php
    $import = $this->import();
    $summary = $import?->summary ?? [];
    $errors = collect($import?->errors ?? []);
    $statusColor = fn ($s) => match ($s) { 'done' => 'success', 'failed' => 'danger', 'processing', 'queued' => 'warning', default => 'gray' };
    $fields = \App\Domain\Catalog\Import\ImportColumns::FIELDS;
@endphp

<x-filament-panels::page>
    @if (! $import)
        <x-filament::section heading="1. Sube el archivo" description="Primero se analiza sin cambiar nada del catálogo. Verás un resumen y los errores antes de confirmar.">
            <form wire:submit="analyze" class="space-y-6">
                {{ $this->uploadForm }}
                <x-filament::button type="submit" icon="heroicon-o-magnifying-glass" wire:loading.attr="disabled" wire:target="analyze,upload.file">
                    <span wire:loading.remove wire:target="analyze">Analizar archivo</span>
                    <span wire:loading wire:target="analyze">Analizando…</span>
                </x-filament::button>
            </form>
        </x-filament::section>
    @else
        <div @if ($import->isRunning()) wire:poll.2s @endif class="space-y-6">
            <x-filament::section>
                <x-slot name="heading">
                    {{ $import->original_name }}
                </x-slot>
                <x-slot name="description">
                    {{ \App\Domain\Catalog\Models\CatalogImport::MODES[$import->mode] }}
                    · subido {{ $import->created_at->timezone(config('app.timezone'))->format('d-m-Y H:i') }}
                </x-slot>
                <x-slot name="afterHeader">
                    <x-filament::badge :color="$statusColor($import->status)">
                        {{ \App\Domain\Catalog\Models\CatalogImport::STATUSES[$import->status] ?? $import->status }}
                    </x-filament::badge>
                </x-slot>

                @if ($import->status === 'failed')
                    <p class="text-sm text-danger-600 dark:text-danger-400">{{ $import->failure }}</p>
                @endif

                @if ($import->status === 'analyzed')
                    <dl class="grid grid-cols-2 gap-4 sm:grid-cols-5">
                        @foreach ([
                            'Filas' => $import->total_rows,
                            'Productos' => $summary['products'] ?? 0,
                            'Variantes nuevas' => $summary['variants_to_create'] ?? 0,
                            'Variantes a actualizar' => $summary['variants_to_update'] ?? 0,
                            'Filas con errores' => $summary['rows_with_errors'] ?? 0,
                        ] as $label => $value)
                            <div class="rounded-lg bg-gray-50 p-3 dark:bg-white/5">
                                <dt class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                                <dd class="mt-1 text-2xl font-semibold tabular-nums @if ($label === 'Filas con errores' && $value) text-danger-600 dark:text-danger-400 @endif">{{ number_format($value, 0, ',', '.') }}</dd>
                            </div>
                        @endforeach
                    </dl>

                    @if (! empty($summary['missing_required']))
                        <p class="mt-4 text-sm text-danger-600 dark:text-danger-400">
                            Faltan columnas obligatorias:
                            {{ collect($summary['missing_required'])->map(fn ($f) => $fields[$f][0] ?? $f)->implode(', ') }}.
                            Asígnalas en "Columnas del archivo" y vuelve a analizar.
                        </p>
                    @endif

                    <div class="mt-6 flex flex-wrap gap-3">
                        <x-filament::button wire:click="confirm" icon="heroicon-o-check" :disabled="! empty($summary['missing_required'])" wire:loading.attr="disabled">
                            Importar {{ number_format(($summary['variants_to_create'] ?? 0) + ($summary['variants_to_update'] ?? 0), 0, ',', '.') }} filas válidas
                        </x-filament::button>
                        <x-filament::button color="gray" wire:click="startOver">Subir otro archivo</x-filament::button>
                    </div>
                    @if (($summary['rows_with_errors'] ?? 0) > 0)
                        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">Las filas con errores se omiten. Corrígelas en el archivo y vuelve a importar solo esas.</p>
                    @endif
                @endif

                @if ($import->isRunning())
                    @php $pct = $import->total_rows ? min(100, (int) floor($import->processed_rows * 100 / $import->total_rows)) : 0; @endphp
                    <div>
                        <div class="flex justify-between text-sm">
                            <span>{{ $import->status === 'queued' ? 'Esperando turno en la cola…' : 'Importando…' }}</span>
                            <span class="tabular-nums">{{ number_format($import->processed_rows, 0, ',', '.') }} / {{ number_format($import->total_rows, 0, ',', '.') }}</span>
                        </div>
                        <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-white/10" role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100">
                            <div class="h-full rounded-full bg-primary-600 transition-all" style="width: {{ $pct }}%"></div>
                        </div>
                        @if ($import->status === 'queued' && $import->updated_at->lt(now()->subMinute()))
                            <p class="mt-3 text-sm text-warning-600 dark:text-warning-400">
                                Lleva más de un minuto en cola. Revisa que el proceso de colas esté corriendo (ver "Manual de uso").
                            </p>
                        @endif
                    </div>
                @endif

                @if ($import->status === 'done')
                    <dl class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        @foreach (['Creadas' => $import->created_count, 'Actualizadas' => $import->updated_count, 'Con errores' => $import->failed_count, 'Duración' => null] as $label => $value)
                            <div class="rounded-lg bg-gray-50 p-3 dark:bg-white/5">
                                <dt class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                                <dd class="mt-1 text-2xl font-semibold tabular-nums">
                                    @if ($label === 'Duración')
                                        {{ $import->started_at && $import->finished_at ? $import->started_at->diffForHumans($import->finished_at, true) : '—' }}
                                    @else
                                        {{ number_format($value, 0, ',', '.') }}
                                    @endif
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <x-filament::button tag="a" :href="$this->productsUrl()" icon="heroicon-o-cube">Ver productos</x-filament::button>
                        <x-filament::button color="gray" wire:click="startOver">Nueva importación</x-filament::button>
                    </div>
                    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">Los productos importados como borrador no se ven en la tienda hasta publicarlos.</p>
                @endif
            </x-filament::section>

            @if ($import->status === 'analyzed')
                <x-filament::section heading="Columnas del archivo" description="Cada columna se asignó automáticamente por su nombre. Cambia lo que no corresponda y vuelve a analizar." collapsible :collapsed="empty($summary['missing_required'])">
                    <form wire:submit="reanalyze" class="space-y-6">
                        {{ $this->mappingForm }}
                        <x-filament::button type="submit" color="gray" icon="heroicon-o-arrow-path">Volver a analizar</x-filament::button>
                    </form>
                </x-filament::section>

                @if (! empty($summary['preview']))
                    <x-filament::section heading="Vista previa" description="Primeras filas tal como se van a guardar." collapsible>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="text-xs text-gray-500 dark:text-gray-400">
                                    <tr>
                                        @foreach (['sku' => 'SKU', 'name' => 'Nombre', 'brand' => 'Marca', 'category' => 'Categoría', 'price' => 'Precio', 'status' => 'Estado'] as $label)
                                            <th class="px-3 py-2 font-medium">{{ $label }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                                    @foreach ($summary['preview'] as $row)
                                        <tr>
                                            <td class="px-3 py-2 font-mono text-xs">{{ $row['sku'] ?? '' }}</td>
                                            <td class="px-3 py-2">{{ $row['name'] ?? '' }}</td>
                                            <td class="px-3 py-2">{{ $row['brand'] ?? '—' }}</td>
                                            <td class="px-3 py-2">{{ $row['category'] ?? '—' }}</td>
                                            <td class="px-3 py-2 tabular-nums">{{ isset($row['price']) && $row['price'] !== null ? '$'.number_format($row['price'], 0, ',', '.') : '—' }}</td>
                                            <td class="px-3 py-2">{{ ['draft' => 'Borrador', 'active' => 'Publicado', 'archived' => 'Archivado'][$row['status'] ?? ''] ?? 'Sin cambio' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </x-filament::section>
                @endif
            @endif

            @if ($errors->isNotEmpty())
                <x-filament::section :heading="'Errores por fila ('.number_format($errors->count(), 0, ',', '.').')'" collapsible>
                    <x-slot name="afterHeader">
                        <x-filament::button size="sm" color="gray" icon="heroicon-o-arrow-down-tray" wire:click="downloadErrors">Descargar CSV</x-filament::button>
                    </x-slot>
                    <div class="max-h-96 overflow-y-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="sticky top-0 bg-white text-xs text-gray-500 dark:bg-gray-900 dark:text-gray-400">
                                <tr><th class="px-3 py-2 font-medium">Fila</th><th class="px-3 py-2 font-medium">SKU</th><th class="px-3 py-2 font-medium">Problema</th></tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                                @foreach ($errors->take(200) as $error)
                                    <tr>
                                        <td class="px-3 py-2 tabular-nums">{{ $error['row'] }}</td>
                                        <td class="px-3 py-2 font-mono text-xs">{{ $error['sku'] ?: '—' }}</td>
                                        <td class="px-3 py-2">{{ $error['message'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @if ($errors->count() > 200)
                            <p class="px-3 py-2 text-xs text-gray-500">Se muestran 200. Descarga el CSV para ver todos.</p>
                        @endif
                    </div>
                </x-filament::section>
            @endif
        </div>
    @endif

    <x-filament::section heading="Últimas importaciones" collapsible collapsed>
        @php $history = $this->history(); @endphp
        @if ($history->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">Todavía no hay importaciones.</p>
        @else
            <table class="w-full text-left text-sm">
                <thead class="text-xs text-gray-500 dark:text-gray-400">
                    <tr><th class="px-3 py-2 font-medium">Archivo</th><th class="px-3 py-2 font-medium">Usuario</th><th class="px-3 py-2 font-medium">Fecha</th><th class="px-3 py-2 font-medium">Resultado</th><th></th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($history as $item)
                        <tr>
                            <td class="px-3 py-2">{{ $item->original_name }}</td>
                            <td class="px-3 py-2">{{ $item->user?->name ?? '—' }}</td>
                            <td class="px-3 py-2 tabular-nums">{{ $item->created_at->timezone(config('app.timezone'))->format('d-m-Y H:i') }}</td>
                            <td class="px-3 py-2">
                                <x-filament::badge :color="$statusColor($item->status)">{{ \App\Domain\Catalog\Models\CatalogImport::STATUSES[$item->status] ?? $item->status }}</x-filament::badge>
                                @if ($item->status === 'done')
                                    <span class="ms-2 text-xs text-gray-500">{{ $item->created_count }} creadas · {{ $item->updated_count }} actualizadas · {{ $item->failed_count }} con error</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-end">
                                <x-filament::link tag="button" wire:click="show('{{ $item->uuid }}')" size="sm">Ver</x-filament::link>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-filament::section>
</x-filament-panels::page>
