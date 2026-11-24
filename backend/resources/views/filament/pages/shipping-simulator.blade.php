<x-filament-panels::page>
    <form wire:submit="simulate" class="space-y-6">
        {{ $this->form }}

        <div>
            <x-filament::button type="submit" icon="heroicon-o-calculator">Calcular</x-filament::button>
        </div>
    </form>

    @if ($results !== null)
        <x-filament::section heading="Resultado">
            <x-slot name="description">
                {{ $zoneName ? 'Zona de envío: '.$zoneName.'.' : 'Ninguna zona de envío incluye este destino.' }}
                {{ $weightNote }}
            </x-slot>

            @if ($results === [])
                <p class="text-sm text-gray-500">No hay métodos de envío activos. Créalos en Logística → Métodos de envío.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-500">
                                <th class="py-2 pe-4 font-medium">Método</th>
                                <th class="py-2 pe-4 font-medium">Cliente lo ve</th>
                                <th class="py-2 pe-4 font-medium">Precio</th>
                                <th class="py-2 pe-4 font-medium">Plazo</th>
                                <th class="py-2 pe-4 font-medium">Peso cobrado</th>
                                <th class="py-2 font-medium">Detalle</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                            @foreach ($results as $row)
                                <tr>
                                    <td class="py-2 pe-4">
                                        <div class="font-medium">{{ $row['name'] }}</div>
                                        <div class="text-xs text-gray-500">{{ $row['type'] }} · {{ $row['delivery'] }}</div>
                                    </td>
                                    <td class="py-2 pe-4">
                                        <x-filament::badge :color="$row['available'] ? 'success' : 'danger'">{{ $row['available'] ? 'Sí' : 'No' }}</x-filament::badge>
                                    </td>
                                    <td class="py-2 pe-4">
                                        @if ($row['available'])
                                            {{ $row['free'] ? 'Gratis' : '$'.number_format($row['amount'], 0, ',', '.') }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="py-2 pe-4">{{ $row['days'] ?? '—' }}</td>
                                    <td class="py-2 pe-4">{{ $row['weight'] ?? '—' }}</td>
                                    <td class="py-2 text-gray-600 dark:text-gray-300">{{ $row['reason'] ?? ($row['zone'] ? 'Tarifa de la zona '.$row['zone'] : '') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
