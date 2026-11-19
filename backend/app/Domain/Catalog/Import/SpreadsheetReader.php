<?php

namespace App\Domain\Catalog\Import;

use Generator;
use OpenSpout\Common\Entity\Cell\DateTimeCell;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use RuntimeException;

/**
 * Lee CSV o Excel (.xlsx) fila por fila, sin cargar el archivo completo en memoria.
 * CSV: detecta separador (coma o punto y coma, como exporta Excel en Chile)
 * y archivos guardados en Windows-1252 en lugar de UTF-8.
 */
final class SpreadsheetReader
{
    /** @return Generator<int, list<string>> número de fila (1 = encabezados) => celdas como texto */
    public static function rows(string $path, string $extension): Generator
    {
        $extension = strtolower($extension);
        if ($extension === 'xlsx') {
            $reader = new XlsxReader;
        } elseif (in_array($extension, ['csv', 'txt'], true)) {
            $options = new CsvOptions;
            $options->FIELD_DELIMITER = self::detectDelimiter($path);
            if (! self::isUtf8($path)) {
                $options->ENCODING = 'Windows-1252';
            }
            $reader = new CsvReader($options);
        } else {
            throw new RuntimeException('Formato no admitido. Usa un archivo .csv o .xlsx.');
        }

        $reader->open($path);
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $n = 0;
                foreach ($sheet->getRowIterator() as $row) {
                    $n++;
                    $cells = array_map(function ($cell) {
                        if ($cell instanceof DateTimeCell) {
                            return $cell->getValue()->format('Y-m-d H:i');
                        }
                        $v = $cell->getValue();
                        if (is_float($v) && floor($v) === $v) {
                            return (string) (int) $v;
                        }

                        return trim((string) $v);
                    }, $row->getCells());

                    // Quita la marca BOM que agrega Excel al inicio del archivo
                    if ($n === 1 && isset($cells[0])) {
                        $cells[0] = preg_replace('/^\x{FEFF}/u', '', $cells[0]);
                    }
                    yield $n => $cells;
                }
                break; // solo la primera hoja
            }
        } finally {
            $reader->close();
        }
    }

    private static function detectDelimiter(string $path): string
    {
        $handle = fopen($path, 'r');
        $line = (string) fgets($handle, 8192);
        fclose($handle);

        return substr_count($line, ';') > substr_count($line, ',') ? ';' : ',';
    }

    private static function isUtf8(string $path): bool
    {
        $handle = fopen($path, 'r');
        $chunk = (string) fread($handle, 65536);
        fclose($handle);

        return mb_check_encoding($chunk, 'UTF-8') || mb_check_encoding(substr($chunk, 0, -3), 'UTF-8');
    }
}
