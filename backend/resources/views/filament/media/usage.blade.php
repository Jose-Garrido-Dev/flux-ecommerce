<div class="space-y-3 text-sm">
    @if (count($uses) === 0)
        <p>No se usa en ningún producto, categoría, marca ni página. Se puede eliminar.</p>
    @else
        <ul class="list-disc space-y-1 ps-5">
            @foreach ($uses as $use)
                <li>{{ $use }}</li>
            @endforeach
        </ul>
    @endif
    <p class="text-gray-500 dark:text-gray-400">Dirección del archivo: <span class="break-all font-mono text-xs">{{ $url }}</span></p>
</div>
