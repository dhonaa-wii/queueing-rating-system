<?php

namespace App\Http\Controllers;

use App\Models\EvaluationLetterhead;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves a letterhead logo straight from storage/app/public. The page used to
 * link to /storage/... through the `public/storage` symlink, which shared
 * hosting (project in qrs/, web root in public_html/, no shell) usually
 * doesn't have — so logos showed locally and vanished once deployed. Logos
 * are printed on public schedule/tablet sheets anyway, so this is public.
 */
class LetterheadLogoController extends Controller
{
    public function show(EvaluationLetterhead $letterhead, string $slot): BinaryFileResponse
    {
        $path = $letterhead->{EvaluationLetterhead::LOGO_COLUMNS[$slot] ?? abort(404)};

        abort_unless($path && Storage::disk('public')->exists($path), 404);

        // The URL carries a version of the path, so a replaced logo gets a new
        // URL and the old one can be cached for good.
        return response()->file(Storage::disk('public')->path($path), [
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
