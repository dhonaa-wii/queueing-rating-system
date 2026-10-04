<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Builds the downloadable User Guide PDF (public/downloads/arpqrs-user-guide.pdf)
 * that the Help Center's Download PDF button serves.
 *
 * Pre-built rather than generated per request: the guide's layout needs a real
 * browser engine (Dompdf can't do its flex/grid design) and shared hosting has
 * no Chrome. The guide reads the same for everyone, so one file built here with
 * headless Chrome — vector text, the page's own A4 print stylesheet — is served
 * as-is. Re-run after editing anything under resources/views/help/, then deploy
 * the regenerated file with the rest of public/.
 */
class ExportHelpPdf extends Command
{
    protected $signature = 'help:export-pdf {--chrome= : Path to Chrome/Edge (defaults to CHROME_PATH or a standard install location)}';

    protected $description = 'Build the downloadable User Guide PDF from the Help Center page';

    public const OUTPUT = 'downloads/arpqrs-user-guide.pdf';

    public function handle(): int
    {
        $chrome = $this->findChrome();

        if (! $chrome) {
            $this->error('Chrome or Edge was not found. Pass --chrome="C:\path\to\chrome.exe" or set CHROME_PATH.');

            return self::FAILURE;
        }

        // Rendered as a guest, the same page an anonymous visitor gets.
        auth()->logout();
        $html = view('help.index')->render();

        $workDir = storage_path('app/help-export');
        File::ensureDirectoryExists($workDir);
        $htmlPath = $workDir.DIRECTORY_SEPARATOR.'guide.html';
        File::put($htmlPath, $html);

        $output = public_path(self::OUTPUT);
        File::ensureDirectoryExists(dirname($output));
        $tmpPdf = $workDir.DIRECTORY_SEPARATOR.'guide.pdf';
        File::delete($tmpPdf);

        $process = new Process([
            $chrome,
            '--headless=new',
            '--disable-gpu',
            '--no-first-run',
            '--no-default-browser-check',
            '--user-data-dir='.$workDir.DIRECTORY_SEPARATOR.'chrome-profile',
            '--no-pdf-header-footer',
            '--virtual-time-budget=8000',
            '--print-to-pdf='.$tmpPdf,
            'file:///'.str_replace('\\', '/', $htmlPath),
        ]);
        $process->setTimeout(180);
        $process->run();

        if (! File::exists($tmpPdf) || File::size($tmpPdf) < 10_000) {
            $this->error('Chrome did not produce the PDF.');
            $this->line(trim($process->getErrorOutput()));

            return self::FAILURE;
        }

        File::move($tmpPdf, $output);
        File::delete($htmlPath);

        $this->info(sprintf('User Guide PDF written to public/%s (%s KB).', self::OUTPUT, number_format(File::size($output) / 1024)));

        return self::SUCCESS;
    }

    private function findChrome(): ?string
    {
        $candidates = array_filter([
            $this->option('chrome'),
            getenv('CHROME_PATH') ?: null,
            'C:\Program Files\Google\Chrome\Application\chrome.exe',
            'C:\Program Files (x86)\Google\Chrome\Application\chrome.exe',
            'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe',
            'C:\Program Files\Microsoft\Edge\Application\msedge.exe',
            '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
            '/usr/bin/google-chrome',
            '/usr/bin/chromium',
            '/usr/bin/chromium-browser',
        ]);

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }
}
