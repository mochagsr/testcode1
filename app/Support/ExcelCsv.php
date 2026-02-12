<?php

namespace App\Support;

final class ExcelCsv
{
    private const DELIMITER = ';';
    private const EOL = "\r\n";

    /**
     * @param resource $handle
     */
    public static function start($handle): void
    {
        fwrite($handle, "\xEF\xBB\xBF");
        fwrite($handle, 'sep='.self::DELIMITER.self::EOL);
    }

    /**
     * @param resource $handle
     * @param array<int, mixed> $row
     */
    public static function row($handle, array $row): void
    {
        fputcsv($handle, $row, self::DELIMITER, '"', '', self::EOL);
    }
}
