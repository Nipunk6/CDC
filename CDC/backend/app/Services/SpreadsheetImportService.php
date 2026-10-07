<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

/**
 * Reads an uploaded Excel/CSV file into plain rows.
 *
 * Shared by every Phase 2 bulk-upload flow (cycle enrolment, student import,
 * academic sync, shortlist upload) so the PhpSpreadsheet boilerplate lives once.
 */
class SpreadsheetImportService
{
    public const MAX_ROWS = 5000;

    /** Validation rule for any uploaded sheet in this app. */
    public const UPLOAD_RULES = ['file', 'mimes:csv,txt,xlsx,xls', 'max:5120'];

    /**
     * Read every non-empty row, keyed by its 1-based spreadsheet row number so
     * error reports can cite the row the admin sees in Excel.
     *
     * @return array<int, list<string>>
     *
     * @throws \RuntimeException when the file cannot be parsed
     */
    public function rows(UploadedFile $file, int $maxRows = self::MAX_ROWS): array
    {
        return $this->read($file, $maxRows)['rows'];
    }

    /**
     * Like rows(), but also returns the non-empty rows past `$maxRows` (keyed the same way) so the caller can report
     * them instead of dropping them silently (Superset parity S5.7).
     *
     * @return array{rows: array<int, list<string>>, overflow: array<int, list<string>>}
     *
     * @throws \RuntimeException when the file cannot be parsed
     */
    public function read(UploadedFile $file, int $maxRows = self::MAX_ROWS): array
    {
        $reader = IOFactory::createReader($this->readerType($file));
        $reader->setReadDataOnly(true);

        try {
            $spreadsheet = $reader->load($file->getRealPath());
        } catch (Throwable $exception) {
            throw new \RuntimeException('The file could not be read. Upload a valid .xlsx, .xls or .csv file.', 0, $exception);
        }

        $sheet = $spreadsheet->getActiveSheet()->toArray(null, true, false, false);
        $spreadsheet->disconnectWorksheets();

        $rows = [];
        $overflow = [];

        foreach ($sheet as $index => $cells) {
            $cells = array_map(
                static fn ($cell): string => trim((string) ($cell ?? '')),
                is_array($cells) ? $cells : []
            );

            // Skip rows that are entirely blank; they are an artefact of Excel ranges.
            if (implode('', $cells) === '') {
                continue;
            }

            if (count($rows) >= $maxRows) {
                $overflow[$index + 1] = array_values($cells);

                continue;
            }

            $rows[$index + 1] = array_values($cells);
        }

        return ['rows' => $rows, 'overflow' => $overflow];
    }

    /**
     * Read a single-column sheet (optionally skipping a header row) into values
     * keyed by spreadsheet row number.
     *
     * @return array<int, string>
     */
    public function firstColumn(UploadedFile $file, string ...$headerAliases): array
    {
        $rows = $this->rows($file);
        $values = [];

        foreach ($rows as $rowNumber => $cells) {
            $value = $cells[0] ?? '';

            if ($value === '') {
                continue;
            }

            // Tolerate a header row such as "Roll No" / "roll_no".
            if ($headerAliases !== [] && $this->matchesHeader($value, $headerAliases)) {
                continue;
            }

            $values[$rowNumber] = $value;
        }

        return $values;
    }

    private function matchesHeader(string $value, array $aliases): bool
    {
        $normalised = preg_replace('/[^a-z0-9]/', '', strtolower($value)) ?? '';

        foreach ($aliases as $alias) {
            if ($normalised === (preg_replace('/[^a-z0-9]/', '', strtolower($alias)) ?? '')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Uploaded temp files have no usable extension, so pick the reader from the
     * client filename instead of sniffing the path.
     */
    private function readerType(UploadedFile $file): string
    {
        return match (strtolower($file->getClientOriginalExtension())) {
            'csv', 'txt' => 'Csv',
            'xls' => 'Xls',
            default => 'Xlsx',
        };
    }
}
