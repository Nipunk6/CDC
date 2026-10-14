<?php

namespace App\Support;

/**
 * SEC-006: neutralise spreadsheet formulas in CSV cells. A value starting with =, +, -, @, a tab or a carriage return
 * would be evaluated by Excel / Sheets when the file is opened, so it gets a leading apostrophe, which spreadsheets
 * show as plain text. Use for every value of every CSV the portal writes. (Excel downloads built with PhpSpreadsheet
 * write explicit string cells instead — see ExportService.)
 */
final class CsvSafe
{
    private const FORMULA_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    public static function cell(string $value): string
    {
        return $value !== '' && in_array($value[0], self::FORMULA_PREFIXES, true) ? "'".$value : $value;
    }
}
