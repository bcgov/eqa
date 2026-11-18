<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Public "Registry of BC EQA-Designated Institutions" PDF. Mirrors the legacy
 * report at admin.bceqa.gov.bc.ca/report/ and adds a section listing
 * institutions that are no longer designated.
 */
class EqaRegistryReportController extends Controller
{
    private const NO_LONGER_DESIGNATED_STATUSES = ['De-Designated', 'Not Approved'];

    /** Text width available in the Website column, in points (124pt cell less padding). */
    private const WEBSITE_WIDTH = 113.0;

    private Dompdf $dompdf;

    public function __invoke(): Response
    {
        $options = new Options;
        $options->set('defaultFont', 'Helvetica');
        $options->set('isRemoteEnabled', false);
        $this->dompdf = new Dompdf($options);

        $designated = DB::table('institutions')
            ->where('eqa_status', 'Designated')
            ->orderBy('name')
            ->get();

        $noLongerDesignated = DB::table('institutions')
            ->whereIn('eqa_status', self::NO_LONGER_DESIGNATED_STATUSES)
            ->orderBy('name')
            ->get();

        $html = view('reports.eqa-registry', [
            'designated' => $this->rows($designated, fn (object $i) => $i->designation_start),
            'noLongerDesignated' => $this->rows(
                $noLongerDesignated,
                fn (object $i) => ($i->de_designated_date ?? null) ?: $i->designation_expiry,
            ),
            'seal' => 'data:image/png;base64,'.base64_encode((string) file_get_contents(resource_path('images/eqa-seal.png'))),
        ])->render();

        $dompdf = $this->dompdf;
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->loadHtml($html);
        $dompdf->render();

        $reportDate = Carbon::now()->format('m/d/Y');
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('Helvetica');
        $canvas->page_text(42, 768, "Report Date: {$reportDate}", $font, 8, [0, 0, 0]);
        $canvas->page_script(function (int $pageNumber, int $pageCount, $canvas, $fontMetrics) use ($font): void {
            $text = "Page {$pageNumber} of {$pageCount}";
            $width = $fontMetrics->getTextWidth($text, $font, 8);
            $canvas->text(571 - $width, 768, $text, $font, 8, [0, 0, 0]);
        });

        return response((string) $dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="eqa-registry.pdf"',
        ]);
    }

    /**
     * @param  Collection<int, object>  $institutions
     * @return array<int, array<string, mixed>>
     */
    private function rows(Collection $institutions, callable $date): array
    {
        return $institutions->values()->map(fn (object $i, int $index): array => [
            'number' => $index + 1,
            'name' => $i->name,
            'address' => $this->addressLines($i),
            'website' => trim((string) $i->website),
            'website_url' => $this->websiteUrl((string) $i->website),
            'website_lines' => $this->wrapWebsite(trim((string) $i->website)),
            'date' => ($value = $date($i)) ? Carbon::parse($value)->format('m/d/Y') : '',
        ])->all();
    }

    /** @return array<int, string> */
    private function addressLines(object $i): array
    {
        $streets = collect([$i->street1, $i->street2])
            ->flatMap(fn ($s) => preg_split('/\R/', trim((string) $s)) ?: []);
        $locality = implode(' ', array_filter([trim((string) $i->city), trim((string) $i->province), trim((string) $i->postal_code)]));

        return $streets->push($locality, trim((string) $i->country))
            ->map(fn ($line) => trim((string) $line))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * dompdf cannot break a long unspaced URL, so split it to fit the Website
     * column: fill each line by character, breaking after a hyphen when one is
     * on the line (as the legacy iText report does).
     *
     * @return array<int, string>
     */
    private function wrapWebsite(string $website): array
    {
        $metrics = $this->dompdf->getFontMetrics();
        $font = $metrics->getFont('Helvetica');
        $lines = [];
        $line = '';

        foreach (mb_str_split($website) as $char) {
            if ($line !== '' && $metrics->getTextWidth($line.$char, $font, 8) > self::WEBSITE_WIDTH) {
                $hyphen = mb_strrpos($line, '-');
                if ($hyphen !== false && $hyphen > 0 && $hyphen < mb_strlen($line) - 1) {
                    $lines[] = mb_substr($line, 0, $hyphen + 1);
                    $line = mb_substr($line, $hyphen + 1);
                } else {
                    $lines[] = $line;
                    $line = '';
                }
            }
            $line .= $char;
        }

        if ($line !== '') {
            $lines[] = $line;
        }

        return $lines;
    }

    private function websiteUrl(string $website): ?string
    {
        $website = trim($website);
        if ($website === '') {
            return null;
        }

        return preg_match('#^https?://#i', $website) ? $website : 'http://'.$website;
    }
}
