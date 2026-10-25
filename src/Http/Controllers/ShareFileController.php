<?php

namespace Platform\Organization\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Platform\Core\Http\Controllers\AvatarController;
use Platform\Core\Models\User;
use Platform\Organization\Services\EntityShareService;

/**
 * Dateien hinter einer Freigabe (Kunden-Tor): Belege und Profilbilder der Personen,
 * die auf der Seite erscheinen. Gleiche Prüfung wie die Seite selbst.
 */
class ShareFileController extends Controller
{
    public function document(Request $request, string $token, string $ref)
    {
        $service = app(EntityShareService::class);
        [$share, , $status] = $service->resolve($token);
        abort_unless($status === 'ok', 404);

        return $service->document($share, $ref) ?? abort(404);
    }

    public function avatar(Request $request, string $token, int $user)
    {
        [$share, , $status] = app(EntityShareService::class)->resolve($token);
        // Nur Personen, die die Seite gerade zeigt (gemerkt beim Rendern)
        abort_unless($status === 'ok' && in_array($user, (array) Cache::get('gp_people_' . $share->id, []), true), 404);

        return app(AvatarController::class)->show($request, User::findOrFail($user));
    }
}
