<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Throwable;

class AboutPageController extends Controller
{
    public function index(): View
    {
        return view('about.index', [
            'updates' => $this->readRecentCommits(),
            'appLabel' => 'ERPOS by Moch Agus Rahmanto',
        ]);
    }

    /**
     * @return list<array{hash:string,short_hash:string,message:string,committed_at:string}>
     */
    private function readRecentCommits(): array
    {
        if (! File::exists(base_path('.git'))) {
            return [];
        }

        if (! $this->canStartProcess()) {
            return [];
        }

        try {
            $process = new Process([
                'git',
                'log',
                '--pretty=format:%H%x1f%h%x1f%s%x1f%cI',
                '-n',
                '120',
            ], base_path());

            $process->setTimeout(10);
            $process->run();

            if (! $process->isSuccessful()) {
                return [];
            }

            $lines = preg_split('/\r\n|\r|\n/', trim($process->getOutput())) ?: [];
        } catch (Throwable) {
            return [];
        }

        return collect($lines)
            ->map(function (string $line): ?array {
                $parts = explode("\x1f", $line);
                if (count($parts) !== 4) {
                    return null;
                }

                return [
                    'hash' => trim($parts[0]),
                    'short_hash' => trim($parts[1]),
                    'message' => trim($parts[2]),
                    'committed_at' => trim($parts[3]),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function canStartProcess(): bool
    {
        if (! function_exists('proc_open')) {
            return false;
        }

        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

        return ! in_array('proc_open', $disabled, true);
    }
}
