{{--
    Phase 6C (Kontaktformular auf Laravel): reines Text-Mailtemplate,
    ersetzt den manuell zusammengebauten $zeilen-Array-Body aus
    api/contact.php (kjs_contact_mail_versuchen()) 1:1 fachlich - gleiche
    Reihenfolge/Feldauswahl (Name/E-Mail/Telefon-falls-vorhanden/Jäger-
    falls-vorhanden/Hegering-falls-vorhanden/Anliegen/Datum/Leerzeile/
    "Nachricht:"/Freitext).

    WICHTIG: dies ist ein reines Text-Mailable (Mailable::content() nutzt
    "text: ..." statt "view: ..."), daher bewusst {!! !!} statt {{ }} -
    Blades {{ }} wuerde per htmlspecialchars() z.B. "&" zu "&amp;" oder
    Anführungszeichen zu Entities verfälschen, obwohl der Mailbody gar
    keine HTML-Renderinstanz hat (Content-Type text/plain). Ein
    XSS-/Injection-Risiko besteht dadurch nicht, da Text-Mails nicht als
    HTML interpretiert werden - siehe Testabdeckung in
    tests/Feature/Phase6C für die Bestätigung, dass Nutzereingaben hier
    unverändert (nicht HTML-entity-kodiert) ankommen.
--}}
Neue Kontaktanfrage über die KJS-Website

Name: {!! $anfrage->name !!}
E-Mail: {!! $anfrage->email !!}
@if ($anfrage->telefon)
Telefon: {!! $anfrage->telefon !!}
@endif
@if ($anfrage->bereits_jaeger)
Bereits Jäger: {!! $anfrage->bereits_jaeger !!}
@endif
@if ($anfrage->hegering)
Hegering: {!! $anfrage->hegering !!}
@endif
Anliegen: {!! $anfrage->anliegen !!}
Datum: {{ $anfrage->erstellt_am?->format('d.m.Y, H:i \U\h\r') }}

Nachricht:
{!! $anfrage->nachricht !!}
