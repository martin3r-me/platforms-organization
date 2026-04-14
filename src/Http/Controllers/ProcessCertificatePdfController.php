<?php

namespace Platform\Organization\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Platform\Organization\Models\OrganizationProcess;
use Platform\Organization\Services\ProcessCertificateService;

class ProcessCertificatePdfController extends Controller
{
    public function __invoke(OrganizationProcess $process)
    {
        abort_unless(
            Auth::check() && $process->team_id === Auth::user()->currentTeam?->id,
            403,
            'Zugriff verweigert'
        );

        $data = ProcessCertificateService::compute($process);

        $html = view('organization::pdf.process-certificate', [
            'data' => $data,
            'process' => $process,
        ])->render();

        $filename = str($process->name ?: 'prozessausweis')
            ->slug('-')
            ->append('-ausweis.pdf')
            ->toString();

        return Pdf::loadHTML($html)
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('isHtml5ParserEnabled', true)
            ->setPaper('a4', 'portrait')
            ->download($filename);
    }
}
