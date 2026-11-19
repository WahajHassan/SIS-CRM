<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DynamicProposalMail extends Mailable
{
    use Queueable, SerializesModels;

    public $subject;
    public $bodyHtml;
    public $emailAttachments;

    /**
     * Create a new message instance.
     */
    public function __construct($subject, $bodyHtml, $emailAttachments = [])
    {
        $this->subject = $subject;
        $this->bodyHtml = $bodyHtml;
        $this->emailAttachments = $emailAttachments;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.dynamic_proposal',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $mapped = [];
        foreach ($this->emailAttachments as $path) {
            if (file_exists(storage_path('app/public/' . $path))) {
                $mapped[] = Attachment::fromPath(storage_path('app/public/' . $path));
            } elseif (file_exists($path)) {
                $mapped[] = Attachment::fromPath($path);
            }
        }
        return $mapped;
    }
}
