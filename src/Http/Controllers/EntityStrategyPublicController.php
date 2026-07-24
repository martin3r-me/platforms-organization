<?php

namespace Platform\Organization\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Routing\Controller;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Services\EntityStrategyPresenter;

class EntityStrategyPublicController extends Controller
{
    public function show(string $token)
    {
        $entity = $this->resolveEntity($token);

        return response($this->render($entity));
    }

    public function pdf(string $token)
    {
        $entity = $this->resolveEntity($token);

        $filename = str($entity->name ?: 'strategie')
            ->slug('-')
            ->append('-strategie.pdf')
            ->toString();

        return Pdf::loadHTML($this->render($entity))
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('isHtml5ParserEnabled', true)
            ->setPaper('a4', 'portrait')
            ->download($filename);
    }

    private function render(OrganizationEntity $entity): string
    {
        return view('organization::public.entity-strategy', [
            'entity'   => $entity,
            'strategy' => EntityStrategyPresenter::forEntity($entity),
        ])->render();
    }

    private function resolveEntity(string $token): OrganizationEntity
    {
        return OrganizationEntity::query()
            ->where('public_token', $token)
            ->where(function ($q) {
                $q->whereNull('public_token_expires_at')
                    ->orWhere('public_token_expires_at', '>', now());
            })
            ->firstOrFail();
    }
}
