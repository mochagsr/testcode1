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
        $storedUpdates = $this->readStoredUpdates();

        if ($storedUpdates !== []) {
            return $storedUpdates;
        }

        if (! File::exists(base_path('.git'))) {
            return [];
        }

        $updates = $this->readCommitsFromGitProcess();

        if ($updates !== []) {
            return $updates;
        }

        return [];
    }

    /**
     * @return list<array{hash:string,short_hash:string,message:string,committed_at:string}>
     */
    private function readStoredUpdates(): array
    {
        $path = storage_path('app/about-updates.json');

        if (! is_readable($path)) {
            return [];
        }

        try {
            $payload = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return [];
        }

        if (! is_array($payload)) {
            return [];
        }

        return collect($payload)
            ->map(function (mixed $item): ?array {
                if (! is_array($item)) {
                    return null;
                }

                $hash = trim((string) ($item['hash'] ?? ''));
                $shortHash = trim((string) ($item['short_hash'] ?? ''));
                $message = trim((string) ($item['message'] ?? ''));
                $committedAt = trim((string) ($item['committed_at'] ?? ''));

                if ($hash === '' || $message === '') {
                    return null;
                }

                return [
                    'hash' => $hash,
                    'short_hash' => $shortHash !== '' ? $shortHash : substr($hash, 0, 7),
                    'message' => $message,
                    'committed_at' => $committedAt,
                ];
            })
            ->filter()
            ->take(120)
            ->values()
            ->all();
    }

    /**
     * @return list<array{hash:string,short_hash:string,message:string,committed_at:string}>
     */
    private function readCommitsFromGitProcess(): array
    {
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
