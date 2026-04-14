<?php

use Illuminate\Support\Facades\Route;
use Platform\Organization\Http\Controllers\ProcessCertificatePublicController;

Route::get('/p/{token}', [ProcessCertificatePublicController::class, 'show'])->name('organization.certificate.public');
Route::get('/p/{token}/pdf', [ProcessCertificatePublicController::class, 'pdf'])->name('organization.certificate.public.pdf');
