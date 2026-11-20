<?php

namespace App\Domain\Shipping\Console;

use App\Domain\Shipping\Models\Commune;
use App\Domain\Shipping\Models\Region;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Carga las comunas desde el archivo oficial de Códigos Únicos Territoriales (CUT).
 * El archivo es un CSV con dos columnas: código CUT de 5 dígitos y nombre de la comuna.
 * Los dos primeros dígitos del código indican la región (01 Tarapacá … 16 Ñuble).
 */
class ImportCommunesCommand extends Command
{
    protected $signature = 'geo:import-communes {archivo : CSV con columnas codigo,nombre} {--dry-run : Solo revisa el archivo}';

    protected $description = 'Carga o actualiza las comunas desde el listado oficial de códigos CUT';

    /** Número de región en el código CUT → numeración romana de la tabla regions. */
    private const REGION_BY_CUT = [
        '01' => 'I', '02' => 'II', '03' => 'III', '04' => 'IV', '05' => 'V', '06' => 'VI', '07' => 'VII', '08' => 'VIII',
        '09' => 'IX', '10' => 'X', '11' => 'XI', '12' => 'XII', '13' => 'RM', '14' => 'XIV', '15' => 'XV', '16' => 'XVI',
    ];

    public function handle(): int
    {
        $path = $this->argument('archivo');
        if (! is_readable($path)) {
            $this->error("No se puede leer el archivo $path");

            return self::FAILURE;
        }
        $regions = Region::query()->pluck('id', 'roman');
        $rows = [];
        $errors = [];
        $handle = fopen($path, 'r');
        $first = fgets($handle);
        $delimiter = substr_count((string) $first, ';') > substr_count((string) $first, ',') ? ';' : ',';
        rewind($handle);
        $line = 0;
        while (($data = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            $line++;
            $code = preg_replace('/\D/', '', (string) ($data[0] ?? ''));
            $name = trim((string) ($data[1] ?? ''));
            if ($line === 1 && $code === '') {
                continue; // encabezado
            }
            $code = str_pad($code, 5, '0', STR_PAD_LEFT);
            $region = self::REGION_BY_CUT[substr($code, 0, 2)] ?? null;
            if (strlen($code) !== 5 || $name === '' || ! $region || ! isset($regions[$region])) {
                $errors[] = "Fila $line: código o nombre no válido ({$data[0]})";

                continue;
            }
            $rows[$code] = ['code' => $code, 'name' => $name, 'region_id' => $regions[$region]];
        }
        fclose($handle);

        foreach ($errors as $e) {
            $this->warn($e);
        }
        $this->info(count($rows).' comunas válidas, '.count($errors).' filas con error.');
        if ($errors !== [] || $this->option('dry-run')) {
            return $errors === [] ? self::SUCCESS : self::FAILURE;
        }

        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                Commune::query()->updateOrCreate(['code' => $row['code']], $row);
            }
        });
        $this->info('Comunas cargadas. La tienda ya muestra el selector de comuna.');

        return self::SUCCESS;
    }
}
