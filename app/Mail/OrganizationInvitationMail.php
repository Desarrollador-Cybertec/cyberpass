<?php

namespace App\Mail;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrganizationInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $invitee,
        public readonly Organization $organization,
        public readonly string $token,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Invitación a {$this->organization->name} en CyberPass",
        );
    }

    public function content(): Content
    {
        $frontendUrl = config('app.frontend_url', config('app.url'));
        $acceptUrl   = "{$frontendUrl}/invitations/accept?token={$this->token}&email=".urlencode($this->invitee->email);

        return new Content(
            view: 'emails.invitation',
            with: [
                'inviteeName'      => $this->invitee->name,
                'organizationName' => $this->organization->name,
                'acceptUrl'        => $acceptUrl,
                'expiresInDays'    => 7,
            ],
        );
    }
}
