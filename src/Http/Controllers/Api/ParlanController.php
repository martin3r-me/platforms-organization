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
}
