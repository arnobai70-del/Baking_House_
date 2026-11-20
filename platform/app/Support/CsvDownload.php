<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * UTF-8 CSV downloads (with BOM so Excel shows Bangla correctly). Cells that a spreadsheet would
 * treat as a formula (=, +, -, @, tab, CR) are prefixed with an apostrophe (CSV injection guard).
 */
class CsvDownload
{
    /**
     * @param  list<list<string|int|null>>  $rows
     */
    public static function response(string $filename, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");
            foreach ($rows as $row) {
                fputcsv($out, array_map(self::cell(...), $row), ',', '"', '');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    public static function cell(string|int|null $value): string
    {
        $text = (string) $value;

        return $text !== '' && in_array($text[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$text : $text;
    }
}
