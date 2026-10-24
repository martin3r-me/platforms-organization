<?php

namespace Platform\Organization\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Platform\Organization\Models\OrganizationEntityShare;
use Platform\Organization\Models\OrganizationEntityShareRecipient;

/** Persönlicher Link zu einer geteilten Knoten-Seite (Kunden-Tor). */
class ShareInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public OrganizationEntityShare $share,
        public OrganizationEntityShareRecipient $recipient,
        public ?string $senderName = null,
    ) {
    }

    public function build(): self
    {
        $title = $this->share->displayTitle();
        $teamName = $this->share->team?->name ?? config('app.name', 'Platform');

        return $this
            ->subject("{$title} – Ihr Zugang")
            ->view('organization::emails.share-invitation', [
                'title' => $title,
                'teamName' => $teamName,
                'senderName' => $this->senderName,
                'note' => $this->share->message,
                'url' => $this->recipient->url(),
            ]);
    }
}
