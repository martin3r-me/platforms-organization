<?php

namespace Platform\Organization\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Platform\Organization\Support\ParlanContext;

/**
 * Parlan fragt zurück (Kontext-Räume, TRAAN rooms/0.2): „Dürfen diese Personen das Objekt sehen?"
 * Das ERP bleibt Türsteher, auch wenn jemand in Parlan eingeladen wird. Authentifiziert mit dem
 * Integrations-Geheimnis (dasselbe wie Absprung/Abholen), kein Nutzer-Token.
 */
class ParlanController extends Controller
{
    public function canView(Request $request): JsonResponse
    {
        $secret = (string) config('organization.parlan.secret');
        abort_unless($secret !== '' && hash_equals($secret, (string) $request->bearerToken()), 401);
        $v = $request->validate([
            'ref' => ['required', 'string', 'max:300'],
            'emails' => ['required', 'array', 'max:50'],
            'emails.*' => ['string', 'email'],
        ]);
        $model = ParlanContext::modelForRef($v['ref']);
        abort_if($model === null, 404, 'Objekt nicht gefunden');
        $userClass = config('auth.providers.users.model');
        $users = $userClass::whereIn(\Illuminate\Support\Facades\DB::raw('LOWER(email)'), array_map('strtolower', $v['emails']))->get();
        $allowed = [];
        foreach ($users as $u) {
            if (ParlanContext::canView($u, $model)) {
                $allowed[] = strtolower($u->email);
            }
        }

        return response()->json(['ref' => $v['ref'], 'allowed' => $allowed]);
    }

    /**
     * Kontext-Karte (rooms/0.4): Parlan holt sie beim Absprung, damit jeder im Raum — Mensch wie
     * Agent — sieht, woher das Gespräch kommt und welche Knoten darüber liegen.
     */
    public function card(Request $request): JsonResponse
    {
        $secret = (string) config('organization.parlan.secret');
        abort_unless($secret !== '' && hash_equals($secret, (string) $request->bearerToken()), 401);
        $v = $request->validate(['ref' => ['required', 'string', 'max:300']]);
        $card = ParlanContext::cardForRef($v['ref']);
        abort_if($card === null, 404, 'Objekt nicht gefunden');

        return response()->json(['ref' => $v['ref'], 'card' => $card]);
    }

    /**
     * Profilkarte in Parlan: wer ist das? Name, Rolle (aktive Stellenbeschreibung, sonst gültige
     * Org-Rolle), Einheit (Eltern-Entity der Person/des Agenten) und Bild. Parlan zeigt das nur an —
     * gepflegt wird es hier. Unbekannte E-Mails fehlen in der Antwort.
     */
    public function people(Request $request): JsonResponse
    {
        $secret = (string) config('organization.parlan.secret');
        abort_unless($secret !== '' && hash_equals($secret, (string) $request->bearerToken()), 401);
        $v = $request->validate([
            'emails' => ['required', 'array', 'max:50'],
            'emails.*' => ['string', 'email'],
        ]);
        $userClass = config('auth.providers.users.model');
        $users = $userClass::whereIn(\Illuminate\Support\Facades\DB::raw('LOWER(email)'), array_map('strtolower', $v['emails']))->get();
        $out = [];
        foreach ($users as $u) {
            $entity = \Platform\Organization\Models\OrganizationEntity::with(['type', 'parent'])
                ->where('linked_user_id', $u->id)->orderByDesc('id')->first();
            $out[] = [
                'email' => strtolower($u->email),
                'name' => trim((string) ($u->fullname ?? '')) ?: (string) $u->name,
                'role' => $entity ? (string) ($this->roleOf($entity->id) ?? '') : '',
                'unit' => (string) ($entity?->parent?->name ?? ''),
                'avatar' => method_exists($u, 'avatarUrl') ? (string) ($u->avatarUrl() ?? '') : '',
                'agent' => ($entity?->type?->code === 'agent') || (method_exists($u, 'isAiUser') && $u->isAiUser()),
            ];
        }

        return response()->json(['people' => $out]);
    }

    /** Rollen-Titel: aktive Stellenbeschreibung (People, weich gekoppelt), sonst gültige Org-Rolle. */
    private function roleOf(int $entityId): ?string
    {
        $jp = \Platform\People\Models\JobProfile::class;
        if (class_exists($jp)) {
            try {
                $name = $jp::query()->where('owner_entity_id', $entityId)->where('status', 'active')->orderByDesc('id')->value('name');
                if ($name) {
                    return (string) $name;
                }
            } catch (\Throwable $e) {
                // People nicht migriert → weiter mit Org-Rollen
            }
        }
        $a = \Platform\Organization\Models\OrganizationRoleAssignment::with('role')->forPerson($entityId)
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', now()->toDateString()))
            ->orderByDesc('percentage')->first();

        return $a?->role?->name;
    }
}
