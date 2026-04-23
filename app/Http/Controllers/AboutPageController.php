<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

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
}
