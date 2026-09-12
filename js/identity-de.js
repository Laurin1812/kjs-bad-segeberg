/* KJS Segeberg – identity-de.js
   (12.09.2026, Frank-Feedback "Login-Fehlermeldungen teilweise Englisch")

   HINTERGRUND (bitte vor Änderungen lesen):
   Das Netlify-Identity-Widget (netlify-identity-widget.js) rendert sein
   komplettes Login-/Recovery-/Invite-Formular NICHT direkt in die Seite,
   sondern erzeugt dynamisch ein eigenes <iframe id="netlify-identity-widget">
   (src="about:blank", dadurch weiterhin same-origin/scriptbar) und rendert
   sein UI hinein. Das Widget hat zwar einen echten, offiziellen
   Übersetzungsmechanismus (netlifyIdentity.init({ locale: 'xx' })) - das
   wurde direkt im Quellcode geprüft UND live gegengetestet (Modal auf
   Spanisch mit locale:'es' erfolgreich dargestellt). ABER: Deutsch ("de")
   ist in der mitgelieferten Sprachdatei nicht enthalten, und es gibt KEINE
   öffentliche Schnittstelle, um dem Widget ein eigenes Wörterbuch
   unterzuschieben (kein "translations"/"i18n"-Parameter vorhanden - im
   ~240KB-Bundle nicht auffindbar). Deshalb bleibt nur der hier gewählte,
   robuste Weg: das Skript wartet auf das Erscheinen des Identity-Iframes,
   ersetzt darin die bekannten englischen Texte 1:1 durch die deutschen
   Entsprechungen und beobachtet per MutationObserver jede Neu-Renderung
   (Formularwechsel Login -> Passwort-vergessen -> Bestätigung usw.), damit
   auch dabei sofort wieder Deutsch angezeigt wird.

   Gleichzeitig behebt dieses Skript das zweite gemeldete Thema (Dashlane/
   Passwortmanager erkennt die Login-Felder nicht zuverlässig): im
   Widget-Quellcode fehlt dem E-Mail-Feld (type="email" name="email") jedes
   autocomplete-Attribut komplett. Das Passwort-Feld bekommt bereits korrekt
   "current-password"/"new-password" vom Widget selbst - dort ist keine
   Anpassung nötig. Hier wird deshalb ausschließlich beim E-Mail-Feld
   autocomplete="username" ergänzt, sobald es im Iframe auftaucht - keine
   sonstigen Attribute, kein autocomplete="off", keine Passwortspeicherung.

   Bewusst NICHT verändert: die Netlify-Identity-Architektur selbst (kein
   Ersatz-Login-Formular), keine Rollen/Benutzerdaten/Passwort-Logik.
   ========================================================= */
(function () {
  'use strict';

  /* Bekannte englische UI-Texte des Widgets -> deutsche Entsprechung.
     Quelle: direkt aus dem minifizierten Widget-Bundle extrahierte
     Wörterbuch-Werte (Schlüssel wie log_in, forgot_password, ...) sowie
     ein live durchgeführter, echter Fehlversuch gegen den GoTrue-
     Token-Endpunkt (siehe Abschlussbericht) für die kombinierte
     Fehlermeldung bei falschem Passwort/unbekannter E-Mail. */
  var UEBERSETZUNGEN = {
    'Log in': 'Anmelden',
    'Log out': 'Abmelden',
    'Logged in as': 'Angemeldet als',
    'Logged in': 'Angemeldet',
    'Logging in': 'Anmeldung läuft',
    'Sign up': 'Registrieren',
    'Signing up': 'Registrierung läuft',
    'Forgot password?': 'Passwort vergessen?',
    'Recover password': 'Passwort zurücksetzen',
    'Send recovery email': 'E-Mail zum Zurücksetzen senden',
    'Sending recovery email': 'E-Mail wird gesendet',
    'Never mind': 'Abbrechen',
    'Update password': 'Passwort aktualisieren',
    'Updating password': 'Passwort wird aktualisiert',
    'Complete your signup': 'Registrierung abschließen',
    'Email': 'E-Mail-Adresse',
    'Password': 'Passwort',
    'Enter your name': 'Name eingeben',
    'Enter your password': 'Passwort eingeben',
    'Coded by Netlify': 'Bereitgestellt von Netlify',
    'Continue with': 'Fortfahren mit',
    'A confirmation message was sent to your email, click the link there to continue.':
      'Eine Bestätigungs-E-Mail wurde verschickt. Bitte klicke auf den Link darin, um fortzufahren.',
    "We've sent a recovery email to your account, follow the link there to reset your password.":
      'Wir haben dir eine E-Mail zum Zurücksetzen deines Passworts geschickt. Bitte folge dem Link darin.',
    'Your password has been updated!': 'Dein Passwort wurde aktualisiert!',
    'There was an error verifying your account. Please try again or contact an administrator.':
      'Beim Bestätigen deines Kontos ist ein Fehler aufgetreten. Bitte versuche es erneut oder wende dich an eine Administratorin bzw. einen Administrator.',
    'Public signups are disabled. Contact an administrator and ask for an invite.':
      'Die öffentliche Registrierung ist deaktiviert. Bitte wende dich an eine Administratorin bzw. einen Administrator und bitte um eine Einladung.',
    /* Widget-interne, wortwörtliche Fehlerschlüssel (Rohtexte aus GoTrue) */
    'No user found with this email': 'Für diese E-Mail-Adresse wurde kein Benutzerkonto gefunden.',
    'Invalid Password': 'Das Passwort ist nicht korrekt.',
    'Email not confirmed': 'Die E-Mail-Adresse wurde noch nicht bestätigt.',
    'User not found': 'Benutzerkonto wurde nicht gefunden.',
    /* Live-verifizierte, tatsächliche GoTrue-Fehlermeldung bei falschem
       Passwort bzw. unbekannter E-Mail (kombinierter Text, kommt NICHT
       aus dem obigen Wörterbuch, sondern direkt vom Server) - genau der
       Fall, den Frank beim Login meldete. */
    'No user found with that email, or password invalid.':
      'E-Mail-Adresse oder Passwort ist nicht korrekt.',
    'Please try again': 'Bitte erneut versuchen.'
  };

  /* Ersetzt den Text eines einzelnen Text-Knotens, falls er (getrimmt)
     exakt einem bekannten englischen String entspricht. */
  function textErsetzen(knoten) {
    var text = knoten.nodeValue;
    if (!text) return;
    var getrimmt = text.trim();
    if (!getrimmt) return;
    var uebersetzung = UEBERSETZUNGEN[getrimmt];
    if (uebersetzung) {
      knoten.nodeValue = text.replace(getrimmt, uebersetzung);
    }
  }

  /* Läuft über alle Text-Knoten unterhalb von root und übersetzt sie;
     ergänzend werden value-/placeholder-Attribute von Buttons/Inputs
     geprüft, da manche Widget-Texte (z.B. Button-Beschriftungen) als
     Attribut statt als Text-Knoten gerendert werden. */
  function uebersetzeTextknoten(root) {
    var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, null);
    var knoten;
    while ((knoten = walker.nextNode())) {
      textErsetzen(knoten);
    }
    var attributElemente = root.querySelectorAll('[value], [placeholder]');
    for (var i = 0; i < attributElemente.length; i++) {
      var el = attributElemente[i];
      ['value', 'placeholder'].forEach(function (attr) {
        var wert = el.getAttribute(attr);
        if (wert && UEBERSETZUNGEN[wert.trim()]) {
          el.setAttribute(attr, UEBERSETZUNGEN[wert.trim()]);
        }
      });
    }
  }

  /* Ergänzt beim E-Mail-Feld autocomplete="username", falls es (wie im
     Widget-Quellcode bestätigt) noch fehlt. Das Passwort-Feld bekommt
     sein autocomplete bereits korrekt vom Widget selbst und wird hier
     bewusst nicht angefasst. Kein autocomplete="off", keine sonstigen
     Änderungen am Markup. */
  function autofillAttributeSetzen(root) {
    var emailFelder = root.querySelectorAll('input[type="email"][name="email"]');
    for (var i = 0; i < emailFelder.length; i++) {
      var feld = emailFelder[i];
      if (!feld.getAttribute('autocomplete')) {
        feld.setAttribute('autocomplete', 'username');
      }
    }
  }

  /* Führt beide Anpassungen auf einem Widget-Dokument aus und beobachtet
     es danach dauerhaft, damit auch jeder Formularwechsel (z.B. von
     "Anmelden" zu "Passwort vergessen") erneut übersetzt wird. */
  function widgetDokumentBehandeln(doc) {
    if (!doc || !doc.body) return;
    uebersetzeTextknoten(doc.body);
    autofillAttributeSetzen(doc.body);
    var observer = new MutationObserver(function () {
      uebersetzeTextknoten(doc.body);
      autofillAttributeSetzen(doc.body);
    });
    observer.observe(doc.body, {
      childList: true,
      subtree: true,
      characterData: true
    });
  }

  /* Das Widget erzeugt sein Iframe erst beim ersten Öffnen/Init, nicht
     beim reinen Laden des Scripts. Deshalb: falls es schon existiert,
     sofort behandeln; ansonsten auf sein Erscheinen warten. */
  function aufIframeWarten() {
    var bestehenderIframe = document.getElementById('netlify-identity-widget');
    if (bestehenderIframe && bestehenderIframe.contentDocument) {
      widgetDokumentBehandeln(bestehenderIframe.contentDocument);
      return;
    }
    var beobachter = new MutationObserver(function () {
      var iframe = document.getElementById('netlify-identity-widget');
      if (iframe && iframe.contentDocument) {
        beobachter.disconnect();
        widgetDokumentBehandeln(iframe.contentDocument);
      }
    });
    beobachter.observe(document.body, { childList: true, subtree: true });
  }

  if (document.body) {
    aufIframeWarten();
  } else {
    document.addEventListener('DOMContentLoaded', aufIframeWarten);
  }
})();
