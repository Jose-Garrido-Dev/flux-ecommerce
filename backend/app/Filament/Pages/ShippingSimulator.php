<?php

namespace App\Filament\Pages;

use App\Domain\Shipping\Models\Region;
use App\Domain\Shipping\Models\ShippingZone;
use App\Domain\Shipping\Services\RateEngine;
use App\Domain\Shipping\Services\ShippingPackage;
use App\Filament\Concerns\HasHelpManual;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Logística → Simulador de tarifas: qué métodos ve el cliente para un destino y un paquete, y por qué no ve los demás. */
class ShippingSimulator extends Page implements HasForms
{
    use HasHelpManual, InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static string|UnitEnum|null $navigationGroup = 'Logística';

    protected static ?int $navigationSort = 13;

    protected static ?string $navigationLabel = 'Simulador de tarifas';

    protected static ?string $title = 'Simulador de tarifas';

    protected static ?string $slug = 'shipping-simulator';

    protected string $view = 'filament.pages.shipping-simulator';

    /** @var array<string, mixed> */
    public ?array $data = [];

    /** @var list<array<string, mixed>>|null */
    public ?array $results = null;

    public ?string $zoneName = null;

    public ?string $weightNote = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('shipping.view') ?? false;
    }

    public function mount(): void
    {
        $this->form->fill(['subtotal' => 20000, 'weight_kg' => 1, 'quantity' => 1, 'allows_pickup' => true, 'cold_chain' => false]);
    }

    protected function helpManual(): string
    {
        return 'simulador-de-tarifas';
    }

    protected function getHeaderActions(): array
    {
        return [$this->helpManualAction()];
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Destino y paquete')->columns(2)->schema([
                Select::make('region_id')->label('Región')->required()->searchable()
                    ->options(fn () => Region::query()->orderBy('position')->pluck('name', 'id')),
                TextInput::make('commune')->label('Comuna')->required()->maxLength(80),
                TextInput::make('subtotal')->label('Total de productos')->prefix('$')->integer()->minValue(0)->required()
                    ->helperText('Sirve para probar el envío gratis desde cierto monto.'),
                TextInput::make('quantity')->label('Unidades')->integer()->minValue(1)->maxValue(999)->required(),
                TextInput::make('weight_kg')->label('Peso de cada unidad')->suffix('kg')->numeric()->minValue(0)->required(),
                Grid::make(3)->schema([
                    TextInput::make('length_cm')->label('Largo')->suffix('cm')->numeric()->minValue(0),
                    TextInput::make('width_cm')->label('Ancho')->suffix('cm')->numeric()->minValue(0),
                    TextInput::make('height_cm')->label('Alto')->suffix('cm')->numeric()->minValue(0),
                ]),
                Toggle::make('allows_pickup')->label('Se puede retirar en tienda'),
                Toggle::make('cold_chain')->label('Requiere cadena de frío'),
            ]),
        ]);
    }

    public function simulate(RateEngine $engine): void
    {
        $d = $this->form->getState();
        $mm = fn ($cm) => filled($cm) && (float) $cm > 0 ? (int) round((float) $cm * 10) : null;
        $package = ShippingPackage::manual(
            (int) round((float) $d['weight_kg'] * 1000), $mm($d['length_cm'] ?? null), $mm($d['width_cm'] ?? null), $mm($d['height_cm'] ?? null),
            (int) $d['quantity'], (bool) $d['allows_pickup'], (bool) $d['cold_chain'],
        );
        $regionId = (int) $d['region_id'];
        $this->zoneName = ShippingZone::match($regionId, $d['commune'])?->name;
        $this->weightNote = 'Peso real del paquete: '.RateEngine::kg($package->realWeight()).'.';

        $this->results = $engine->evaluateAll($regionId, $d['commune'], (int) $d['subtotal'], $package)->map(fn (array $r) => [
            'name' => $r['method']->name,
            'type' => $r['method']::TYPES[$r['method']->type] ?? $r['method']->type,
            'delivery' => $r['method']->deliveryLabel(),
            'available' => $r['available'],
            'reason' => $r['reason'],
            'amount' => $r['option']['amount'] ?? null,
            'free' => $r['available'] && ($r['option']['amount'] ?? 1) === 0 && $r['method']->isFreeFor((int) $d['subtotal']),
            'days' => $r['option']['estimated_days'] ?? null,
            'weight' => isset($r['option']['chargeable_weight_g']) ? RateEngine::kg($r['option']['chargeable_weight_g']) : null,
            'zone' => $r['option']['zone_name'] ?? null,
        ])->values()->all();
    }
}
