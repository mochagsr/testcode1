<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AppSetting;

final class PrintPresetResolver
{
    /**
     * @return array{mode:string,preset:string,label:string,threshold:int,is_small:bool,row_count:int}
     */
    public static function resolve(int $rowCount): array
    {
        $mode = strtolower(trim((string) AppSetting::getValue('print_workflow_mode', 'browser')));
        if (! in_array($mode, ['browser', 'qz'], true)) {
            $mode = 'browser';
        }

        $presetSetting = strtolower(trim((string) AppSetting::getValue('print_paper_preset', 'auto')));
        if (! in_array($presetSetting, ['auto', '9.5x5.5', '9.5x11'], true)) {
            $presetSetting = 'auto';
        }

        $threshold = max(5, (int) AppSetting::getValue('print_small_rows_threshold', '35'));
        $normalizedRows = max(0, $rowCount);
        $resolvedPreset = $presetSetting;
        if ($presetSetting === 'auto') {
            $resolvedPreset = $normalizedRows <= $threshold ? '9.5x5.5' : '9.5x11';
        }

        return [
            'mode' => $mode,
            'preset' => $resolvedPreset,
            'label' => $resolvedPreset,
            'threshold' => $threshold,
            'is_small' => $resolvedPreset === '9.5x5.5',
            'row_count' => $normalizedRows,
        ];
    }
}

