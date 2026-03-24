<?php

namespace App\Modules\League\Services;

use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use RuntimeException;

class NationaldexCostCsvReader
{
    /**
     * @return list<array{0: float|int, 1: int}>
     */
    public function readFromUploadedFile(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        if ($path === false) {
            throw new RuntimeException('Could not read uploaded CSV.');
        }

        return $this->readFromPath($path);
    }

    /**
     * @return list<array{0: float|int, 1: int}>
     */
    public function readFromPath(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException("Could not open CSV: {$path}");
        }

        try {
            $header = fgetcsv($handle);
            if ($header === false) {
                throw new InvalidArgumentException('CSV is empty.');
            }

            if (! is_numeric($header[0] ?? null)) {
                $dataRows = [];
            } else {
                $dataRows = [$header];
            }

            while (($row = fgetcsv($handle)) !== false) {
                $dataRows[] = $row;
            }

            $rows = [];
            foreach ($dataRows as $row) {
                if (! isset($row[0], $row[1]) || trim((string) $row[0]) === '') {
                    continue;
                }
                $nationaldexId = $row[0];
                if (! is_numeric($nationaldexId)) {
                    throw new InvalidArgumentException('Each row must start with a numeric nationaldex_id.');
                }
                $costRaw = trim((string) $row[1]);
                if (! is_numeric($costRaw)) {
                    throw new InvalidArgumentException("Invalid cost for nationaldex_id {$nationaldexId}: {$costRaw}");
                }
                $rows[] = [(float) $nationaldexId, (int) $costRaw];
            }

            if ($rows === []) {
                throw new InvalidArgumentException('No data rows found in CSV (expected nationaldex_id,cost).');
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }
}
