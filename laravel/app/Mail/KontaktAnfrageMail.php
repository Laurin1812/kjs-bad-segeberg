<?php

namespace App\Mail;

use App\Models\KontaktAnfrage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * KJS Bad Segeberg - Phase 6C (Kontaktformular vollstaendig auf Laravel).
 *
 * Ersetzt die manuelle PHPMailer-Mail aus api/contact.php
 * (kjs_contact_mail_versuchen()) 1:1 fachlich:
 * - Betreff "Neue Kontaktanfrage – <Anliegen>" (identisch zum Original).
 * - Reply-To ist die E-Mail/der Name des Besuchers (NICHT die technische
 *   Absenderadresse) - damit Mitarbeiter direkt antworten koennen, exakt
 *   wie im PHP-Original ("addReplyTo($email, $vollerName ?: $email)").
 * - Die From-Adresse wird bewusst NICHT hier gesetzt, sondern kommt aus der
 *   globalen Laravel-Mail-Konfiguration (config('mail.from'), env
 *   MAIL_FROM_ADDRESS/MAIL_FROM_NAME) - "From-Adresse über Laravel-Mail-
 *   Konfiguration" laut Auftrag, keine eigene/erfundene Absenderadresse.
 * - Reiner Text-Body (kein HTML) - im PHP-Original ebenfalls
 *   "$mail->isHTML(false)". KEINE Bestaetigungsmail an den Besucher: das
 *   PHP-Original verschickt nachweislich (siehe api/contact.php-Analyse)
 *   ausschliesslich diese EINE Mail an den festen Empfaenger, keine
 *   separate Mail an den Absender - das wird hier bewusst nicht neu
 *   erfunden.
 *
 * Synchron (kein ShouldQueue) - genau wie das PHP-Original, das
 * $mail->send() direkt innerhalb der Anfrage aufruft, kein Hintergrund-
 * Worker noetig/vorausgesetzt.
 */
class KontaktAnfrageMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly KontaktAnfrage $anfrage)
    {
    }

    public function envelope(): Envelope
    {
        $besucherName = trim((string) $this->anfrage->name);

        return new Envelope(
            subject: 'Neue Kontaktanfrage – '.$this->anfrage->anliegen,
            replyTo: [new Address($this->anfrage->email, $besucherName !== '' ? $besucherName : $this->anfrage->email)],
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'emails.kontakt-anfrage',
        );
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        // Keine Anhaenge: weder api/contact.php noch kontakt/index.html
        // kennen einen Datei-Upload im Kontaktformular (kein
        // <input type="file">, keine Anhang-Verarbeitung im PHP-Original) -
        // siehe Phase-6C-Alt-System-Analyse.
        return [];
    }
}
