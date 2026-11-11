<?php

namespace Platform\Organization\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Services\ObjectTypeRegistry;
use Platform\Organization\Support\ParlanContext;

/**
 * KONTEXT-RAUM für einen Agenten: der Agent nennt nur Objektart (Alias) und ID — die Organisation baut den
 * Ref, öffnet bzw. holt den einen Raum dieses Objekts in Parlan und nimmt den Agenten (und auf Wunsch weitere
 * Personen) auf. So landet alles, was der Agent zu einem Objekt bespricht oder terminiert, im selben Raum,
 * den auch die Menschen über „In Parlan besprechen“ erreichen — und fließt per Ref an die Einheit zurück.
 */
class AgentContextRoomController extends Controller
{
    /** POST /api/org/agent/context-room {object, id, members?} */
    public function open(Request $request): JsonResponse
    {
        $user = $request->user();
        $agent = $user ? OrganizationEntity::query()->agents()->where('linked_user_id', $user->id)->first() : null;
        if (! $agent) {
            return response()->json(['message' => 'No agent for this token'], 404);
        }
        if (! ParlanContext::enabled()) {
            return response()->json(['message' => 'Parlan ist für diese Organisation nicht angebunden.'], 422);
        }
        $v = $request->validate([
            'object' => ['required', 'string', 'max:80'],
            'id' => ['required', 'integer', 'min:1'],
            'members' => ['nullable', 'array', 'max:50'],
            'members.*' => ['string', 'email'],
        ]);

        // „entity“ als sprechende Kurzform für Org-Einheiten; sonst der Objekt-Alias der Plattform.
        $alias = $v['object'] === 'entity' ? ParlanContext::alias(OrganizationEntity::class) : $v['object'];
        $class = $alias ? ObjectTypeRegistry::modelClass($alias) : null;
        if (! $class) {
            return response()->json(['message' => "Unbekannte Objektart „{$v['object']}“."], 422);
        }
        $model = $class::find((int) $v['id']);
        if (! $model) {
            return response()->json(['message' => 'Objekt nicht gefunden.'], 404);
        }
        // Wer das Objekt nicht sehen darf, kommt auch nicht in sein Gespräch — Agenten wie Menschen.
        if (! ParlanContext::canView($user, $model)) {
            return response()->json(['message' => 'Kein Zugriff auf dieses Objekt.'], 403);
        }

        $res = ParlanContext::openRoom($alias, $model, [$user->email, ...($v['members'] ?? [])]);
        if (! $res || empty($res['room'])) {
            return response()->json(['message' => 'Parlan hat den Raum nicht geöffnet.'], 502);
        }

        return response()->json(['data' => [
            'room' => (string) $res['room'],
            'ref' => (string) $res['ref'],
            'url' => $res['url'] ?? null,
            'title' => $model instanceof OrganizationEntity ? $model->name : null,
            'members' => $res['members'] ?? [],
            'unknown' => $res['unknown'] ?? [],
        ]]);
    }
}
