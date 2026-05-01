<?php

declare(strict_types=1);

namespace App\Support;

final class PrintPaperSize
{
    public const CONTINUOUS_FORM_95X11_WIDTH_POINTS = 684;

    public const CONTINUOUS_FORM_95X11_HEIGHT_POINTS = 792;

    /**
     * DomPDF custom paper size for continuous form 9.5" x 11".
     *
     * @return array{0:int,1:int,2:int,3:int}
     */
    public static function continuousForm95x11(): array
    {
        return [
            0,
            0,
            self::CONTINUOUS_FORM_95X11_WIDTH_POINTS,
            self::CONTINUOUS_FORM_95X11_HEIGHT_POINTS,
        ];
    }
}
