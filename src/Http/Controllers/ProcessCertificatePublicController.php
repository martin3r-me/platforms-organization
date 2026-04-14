<?php

namespace Platform\Organization\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Routing\Controller;
use Platform\Organization\Models\OrganizationProcess;
use Platform\Organization\Services\ProcessCertificateService;

class ProcessCertificatePublicController extends Controller
{
    public function show(string $token)
    {
        $process = $this->resolveProcess($token);
        $data = ProcessCertificateService::compute($process);

        $html = view('organization::pdf.process-certificate', [
            'data' => $data,
            'process' => $process,
        ])->render();

        return response($html);
    }

    public function pdf(string $token)
    {
        $process = $this->resolveProcess($token);
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

    private function resolveProcess(string $token): OrganizationProcess
    {
        return OrganizationProcess::where('public_token', $token)
            ->where('public_token_expires_at', '>', now())
            ->firstOrFail();
    }
}
