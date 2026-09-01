<?php

namespace Platform\Organization\Http\Controllers\Api;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Platform\Organization\Models\OrganizationEntity;
use Platform\UserConnectors\DTOs\Pagination;
use Platform\UserConnectors\Models\UserConnector;
use Platform\UserConnectors\Models\UserConnectorConnection;
use Platform\UserConnectors\Services\Microsoft365\Microsoft365ApiService;
use Platform\UserConnectors\Services\Microsoft365\Microsoft365MailConnector;
use Platform\UserConnectors\Services\Microsoft365\Microsoft365TeamsConnector;

/**
 * Agent-Comms (AUSGANG) — der Ausgangs-Motor der Firmware. Der Daemon liefert INHALT
 * ("sende das an X"), die Org-Seite besorgt den TRANSPORT: sie rendert Hausstil-HTML und
 * schickt es über M365 — Teams für intern/schnell, Mail für formell/extern. So ist der
 * Transport austauschbar, ohne den Daemon anzufassen, und der Hausstil lebt an EINER Stelle.
 *
 * IDENTITÄT: gesendet wird IMMER über die EIGENE, aktive M365-Connection des Agenten
 * (owner_user_id = Bot-User des Agenten). Kein Shared-Fallback — sonst postete ein Agent
 * versehentlich als ein anderer Nutzer. Fehlt die eigene Connection → 422 (fail-safe).
 */
class AgentMessagingController extends Controller
{
    /**
     * POST /api/org/agent/message
     *
     * body:
     *   to?         E-Mail einer Person (Teams: → 1:1-Chat, Mail: Empfänger)
     *   channel_id? Teams-Channel-ID (Ops/Rückfrage-Channel — agent↔agent)
     *   transport?  teams|mail|auto (Default auto: interne Person → Teams, sonst Mail)
     *   kind?       report|question|proposal|reply|note (steuert Überschrift/Betreff)
     *   subject?    Betreff (Mail) / Karten-Titel
     *   body        Der Inhalt (Klartext/leichtes Markdown vom Kortex)
     *   thread?     Reply-Korrelation: {chat_id} | {channel_id,message_id} | {mail_message_id}
     */
    public function send(Request $request): JsonResponse
    {
        $agent = $request->user();
        if (! $agent) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $data = $request->validate([
            'to' => ['nullable', 'string', 'max:255'],
            'channel_id' => ['nullable', 'string', 'max:255'],
            'transport' => ['nullable', Rule::in(['teams', 'mail', 'auto'])],
            'kind' => ['nullable', 'string', 'max:32'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'thread' => ['nullable', 'array'],
        ]);

        if (empty($data['to']) && empty($data['channel_id'])) {
            return response()->json(['message' => 'Either "to" (person) or "channel_id" (Teams channel) is required.'], 422);
        }

        // IDENTITÄT: eigene, aktive M365-Connection des Agenten — kein Shared-Fallback.
        $connection = $this->ownMicrosoft365Connection((int) $agent->id);
        if (! $connection) {
            return response()->json([
                'message' => 'Agent has no own active Microsoft 365 connection — cannot send as itself.',
            ], 422);
        }

        $api = app(Microsoft365ApiService::class)->forConnection((int) $connection->id);
        $teams = new Microsoft365TeamsConnector($api);
        $mail = new Microsoft365MailConnector($api);

        $kind = $data['kind'] ?? 'note';
        $subject = $data['subject'] ?? $this->defaultSubject($kind);
        [$agentName, $roleLabel] = $this->identity((int) $agent->id, (string) ($agent->name ?? 'Agent'));
        $html = $this->renderHtml($kind, $subject, $data['body'], $agentName, $roleLabel);
        $thread = $data['thread'] ?? [];

        try {
            // (1) Teams-CHANNEL (Ops / Rückfrage-Thread, agent↔agent oder öffentliche Rückfrage).
            if (! empty($data['channel_id'])) {
                if (! empty($thread['message_id'])) {
                    $r = $teams->replyToChannelMessage($agent, $data['channel_id'], (string) $thread['message_id'], $html);
                } else {
                    $r = $teams->sendChannelMessage($agent, $data['channel_id'], $html);
                }

                return $this->ok('teams', [
                    'channel_id' => $data['channel_id'],
                    'message_id' => $r['id'] ?? null,
                ]);
            }

            // (2) An eine PERSON. Transport bestimmen.
            $transport = $data['transport'] ?? 'auto';
            $graphId = null;
            if ($transport !== 'mail') {
                // Bevorzugt: Graph-ID des Empfängers aus SEINER eigenen Plattform-M365-Connection
                // (kennt die Plattform bereits) — braucht kein User.Read.All auf der Agent-App.
                $graphId = $this->recipientGraphId((string) $data['to']);
                if (! $graphId) {
                    // Fallback: Directory-Lookup (nur falls die Agent-App den Scope hat).
                    $res = $teams->resolveUserIds($agent, [$data['to']]);
                    $graphId = $res['resolved'][$data['to']] ?? null;
                }
                if ($transport === 'auto') {
                    $transport = $graphId ? 'teams' : 'mail';
                }
            }

            // (2a) TEAMS an eine Person (1:1-Chat; für 1:1 ist createChat idempotent).
            if ($transport === 'teams') {
                $chatId = $thread['chat_id'] ?? null;
                if (! $chatId) {
                    if (! $graphId) {
                        return response()->json([
                            'message' => "Could not resolve \"{$data['to']}\" to a Teams user (external or missing directory scope) — set transport=mail.",
                        ], 422);
                    }
                    $chat = $teams->createChat($agent, [$graphId]);
                    $chatId = $chat['id'] ?? null;
                }
                if (! $chatId) {
                    return response()->json(['message' => 'Could not open a Teams chat.'], 422);
                }
                $r = $teams->sendChatMessage($agent, $chatId, $html);

                return $this->ok('teams', [
                    'chat_id' => $chatId,
                    'message_id' => $r['id'] ?? null,
                ]);
            }

            // (2b) MAIL an eine Person.
            if (! empty($thread['mail_message_id'])) {
                $msg = $mail->replyToMessage($agent, (string) $thread['mail_message_id'], $html);
            } else {
                $msg = $mail->sendMessage($agent, (string) $data['to'], $subject, $html);
            }

            return $this->ok('mail', [
                'message_id' => is_object($msg) ? ($msg->id ?? null) : ($msg['id'] ?? null),
                'conversation_id' => is_object($msg) ? ($msg->threadId ?? null) : ($msg['threadId'] ?? null),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Send failed: ' . $e->getMessage(),
            ], 502);
        }
    }

    private function ok(string $transport, array $ids): JsonResponse
    {
        return response()->json(['data' => [
            'transport' => $transport,
            'ids' => $ids,
        ]]);
    }

    /**
     * GET /api/org/agent/messages?since=<iso8601>&limit=<n>
     *
     * Der Eingangs-Sinn: EIN Posteingang aus Teams + Mail seit last-seen (pull-basiert, keine
     * Webhook-Abhängigkeit). Der Daemon persistiert den zurückgegebenen `since`-Cursor und reicht
     * ihn nächste Runde wieder rein. Fehlt die eigene Connection → leerer Posteingang (kein Fehler).
     */
    public function inbox(Request $request): JsonResponse
    {
        $agent = $request->user();
        if (! $agent) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $sinceParam = (string) $request->query('since', '');
        try {
            $since = $sinceParam !== '' ? Carbon::parse($sinceParam) : now()->subHour();
        } catch (\Throwable $e) {
            $since = now()->subHour();
        }
        $limit = max(1, min((int) $request->query('limit', 25), 50));

        $emptyCursor = now()->toIso8601String();
        $connection = $this->ownMicrosoft365Connection((int) $agent->id);
        if (! $connection) {
            return response()->json(['data' => ['since' => $emptyCursor, 'count' => 0, 'messages' => []]]);
        }

        $api = app(Microsoft365ApiService::class)->forConnection((int) $connection->id);
        $teams = new Microsoft365TeamsConnector($api);
        $mail = new Microsoft365MailConnector($api);
        [$agentName] = $this->identity((int) $agent->id, (string) ($agent->name ?? 'Agent'));
        $ownMs365 = is_array($connection->credentials) ? ($connection->credentials['ms365_user_id'] ?? null) : null;

        $items = [];
        $maxTs = $since->copy();

        // MAIL — Posteingang, neueste zuerst, nur was seit `since` ankam.
        try {
            $res = $mail->listMessages($agent, ['folder' => 'inbox'], new Pagination(1, $limit));
            foreach ($res['messages'] ?? [] as $m) {
                if ($m->date->lessThanOrEqualTo($since)) {
                    continue;
                }
                $items[] = [
                    'transport' => 'mail',
                    'from' => $m->from,
                    'subject' => $m->subject,
                    'preview' => $this->preview($m->body),
                    'thread' => ['mail_message_id' => $m->id, 'conversation_id' => $m->threadId],
                    'received_at' => $m->date->toIso8601String(),
                    'is_read' => $m->isRead,
                ];
                if ($m->date->greaterThan($maxTs)) {
                    $maxTs = $m->date->copy();
                }
            }
        } catch (\Throwable $e) {
            // Mail nicht verfügbar → überspringen, nicht den ganzen Posteingang kippen.
        }

        // TEAMS — nur Chats, die seit `since` bewegt wurden (Kosten begrenzen), fremde Nachrichten.
        try {
            $chats = $teams->listChats($agent)['chats'] ?? [];
            $drilled = 0;
            foreach ($chats as $chat) {
                $lu = $chat['last_updated'] ?? null;
                if (! $lu || Carbon::parse($lu)->lessThanOrEqualTo($since)) {
                    continue;
                }
                if ($drilled >= 15) {
                    break;
                }
                $drilled++;
                $msgs = $teams->getChatMessages($agent, (string) $chat['id'], new Pagination(1, 15))['messages'] ?? [];
                foreach ($msgs as $tm) {
                    $created = $tm['created_at'] ?? null;
                    if (! $created || Carbon::parse($created)->lessThanOrEqualTo($since)) {
                        continue;
                    }
                    $fromId = $tm['from_id'] ?? null;
                    // Eigene Nachrichten NICHT zurückspiegeln (Loop-Schutz): primär per Sender-ID
                    // (robust), Anzeigename als Fallback.
                    if (($fromId !== null && $ownMs365 !== null && $fromId === $ownMs365)
                        || ($tm['from'] ?? '') === $agentName) {
                        continue;
                    }
                    $preview = $this->preview($tm['body'] ?? '');
                    // System-/Event-Nachrichten (kein Sender + kein Inhalt) sind kein Posteingang.
                    if (($fromId === null || $fromId === '') && $preview === '') {
                        continue;
                    }
                    $items[] = [
                        'transport' => 'teams',
                        'from' => $tm['from'] ?? null,
                        'subject' => $chat['topic'] ?? null,
                        'preview' => $preview,
                        'thread' => ['chat_id' => $chat['id'], 'message_id' => $tm['id'] ?? null],
                        'received_at' => $created,
                        'is_read' => null,
                    ];
                    $c = Carbon::parse($created);
                    if ($c->greaterThan($maxTs)) {
                        $maxTs = $c;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Teams nicht verfügbar → überspringen.
        }

        usort($items, fn ($a, $b) => strcmp((string) $b['received_at'], (string) $a['received_at']));
        $items = array_slice($items, 0, $limit);

        return response()->json(['data' => [
            'since' => $maxTs->toIso8601String(),
            'count' => count($items),
            'messages' => $items,
        ]]);
    }

    private function preview(string $body): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($body)) ?? '');

        return mb_substr($text, 0, 200);
    }

    /**
     * Graph-user-id des Empfängers aus SEINER eigenen Plattform-M365-Connection auflösen.
     * Kein Directory-Scope nötig; funktioniert nur für Leute, die selbst M365 verbunden haben.
     */
    private function recipientGraphId(string $email): ?string
    {
        $email = trim($email);
        if ($email === '') {
            return null;
        }
        $user = \Platform\Core\Models\User::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])
            ->first();
        if (! $user) {
            return null;
        }
        $conn = $this->ownMicrosoft365Connection((int) $user->id);
        $creds = $conn?->credentials;

        return is_array($creds) ? ($creds['ms365_user_id'] ?? null) : null;
    }

    /**
     * Eigene, aktive M365-Connection des Agenten (owner-only, kein Shared-Fallback).
     */
    private function ownMicrosoft365Connection(int $userId): ?UserConnectorConnection
    {
        $connector = UserConnector::where('key', 'microsoft365')->first();
        if (! $connector) {
            return null;
        }

        return UserConnectorConnection::query()
            ->where('connector_id', $connector->id)
            ->where('owner_user_id', $userId)
            ->where('status', 'active')
            ->orderByDesc('is_default')
            ->first();
    }

    /**
     * Anzeigename + Rollen-Label für die Signatur (People weich referenziert).
     */
    private function identity(int $userId, string $fallbackName): array
    {
        $entity = OrganizationEntity::query()->agents()->where('linked_user_id', $userId)->first();
        $name = $entity?->name ?: $fallbackName;
        $role = null;
        $jpClass = \Platform\People\Models\JobProfile::class;
        if ($entity && class_exists($jpClass)) {
            try {
                $role = $jpClass::query()->where('owner_entity_id', $entity->id)->value('name');
            } catch (\Throwable $e) {
                $role = null;
            }
        }

        return [$name, $role];
    }

    private function defaultSubject(string $kind): string
    {
        return match ($kind) {
            'report' => 'Statusbericht',
            'question' => 'Rückfrage',
            'proposal' => 'Vorschlag',
            default => 'Nachricht',
        };
    }

    /**
     * Hausstil-HTML — EINE Stelle für alle Agenten. Überschrift nach Art, leichtes Markdown
     * (Bullets/fett/Code) → Rich Text, dezente Signatur. Teams/Outlook rendern das als Karte.
     */
    private function renderHtml(string $kind, string $subject, string $body, string $agentName, ?string $roleLabel): string
    {
        $icon = match ($kind) {
            'report' => '🔷',
            'question' => '❓',
            'proposal' => '💡',
            'reply' => '↩️',
            default => '•',
        };
        $head = $kind === 'reply' ? '' : "<h3>{$icon} " . e($subject) . "</h3>";
        $sig = e($agentName) . ($roleLabel ? ' · ' . e($roleLabel) : '');

        return $head
            . $this->bodyToHtml($body)
            . "<br><span style=\"color:#8a8f98;font-size:12px\">— {$sig}</span>";
    }

    /**
     * Minimaler, sicherer Markdown→HTML-Wandler: erst escapen, dann "- "-Zeilen zu <ul><li>,
     * Leerzeilen zu Absätzen, **fett** und `code`. Bewusst klein — kein Parser-Kaninchenbau.
     */
    private function bodyToHtml(string $body): string
    {
        $lines = preg_split('/\r?\n/', trim($body));
        $out = [];
        $inList = false;
        foreach ($lines as $line) {
            $trim = trim($line);
            if ($trim === '') {
                if ($inList) {
                    $out[] = '</ul>';
                    $inList = false;
                }
                $out[] = '<br>';
                continue;
            }
            if (preg_match('/^[-*]\s+(.*)$/', $trim, $m)) {
                if (! $inList) {
                    $out[] = '<ul>';
                    $inList = true;
                }
                $out[] = '<li>' . $this->inline($m[1]) . '</li>';
                continue;
            }
            if ($inList) {
                $out[] = '</ul>';
                $inList = false;
            }
            $out[] = $this->inline($trim) . '<br>';
        }
        if ($inList) {
            $out[] = '</ul>';
        }

        return implode('', $out);
    }

    private function inline(string $text): string
    {
        $text = e($text);
        $text = preg_replace('/\*\*(.+?)\*\*/', '<b>$1</b>', $text);
        $text = preg_replace('/`(.+?)`/', '<code>$1</code>', $text);

        return $text;
    }
}
