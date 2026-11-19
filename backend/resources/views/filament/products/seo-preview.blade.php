{{-- Vista previa de cómo podría verse el producto en un buscador. --}}
@php
    $site = rtrim(config('flux.storefront_url', config('app.url')), '/');
    $title = \Illuminate\Support\Str::limit((string) $title, 70);
    $description = \Illuminate\Support\Str::limit(strip_tags((string) $description), 160);
@endphp
<div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
    <p class="text-xs text-gray-500 dark:text-gray-400">Vista previa en buscadores</p>
    <p class="mt-2 truncate text-sm text-gray-600 dark:text-gray-300">{{ $site }}/producto/{{ $slug ?: '…' }}</p>
    <p class="mt-1 text-lg text-blue-700 dark:text-blue-400">{{ $title ?: 'Título del producto' }}</p>
    <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ $description ?: 'Agrega una descripción corta o una metadescripción para mostrar un resumen aquí.' }}</p>
</div>
