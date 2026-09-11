// ────────────────────────────────────────────────────────────────────────
// LETZTER LOGIN – schreibt beim erfolgreichen Login den eigenen Zeitstempel
// ────────────────────────────────────────────────────────────────────────
//
// Hintergrund (11.09.2026, "Anzeige Letzter Login in der Benutzerverwaltung"):
// Die rohen Netlify-Identity-/GoTrue-Benutzerobjekte dieser Site liefern
// KEINEN eigenen Letzter-Login-Zeitstempel (weder last_sign_in_at noch eine
// vergleichbar benannte Eigenschaft - empirisch per Diagnose-Deploy geprüft,
// nicht angenommen). Diese Function ergänzt daher einen eigenen, bewusst
// minimalen Zeitstempel (nur Datum/Uhrzeit - keine IP, kein Gerät, keine
// Login-Historie) in user_metadata.last_login. admin-users.js liest diesen
// Wert anschließend unverändert für die Benutzerverwaltung aus (siehe
// mapUser() dort).
//
// Aufruf: ausschließlich clientseitig aus admin.js, bei jedem
// netlifyIdentity "login"-Event (siehe initAuth()). POST
// /.netlify/functions/record-last-login, kein Body-Inhalt nötig.
//
// Sicherheit / Selbstbeschränkung (WICHTIG):
// - Die zu aktualisierende Benutzer-ID kommt AUSSCHLIESSLICH aus dem von
//   Netlify serverseitig entschlüsselten und geprüften Zugriffstoken
//   (context.clientContext.user.sub). Ein vom Browser mitgeschickter Body
//   wird für die ID nirgends gelesen oder ausgewertet - ein Benutzer kann
//   dadurch technisch nicht den Letzter-Login-Zeitstempel eines anderen
//   Benutzers setzen, selbst wenn er versucht, eine fremde ID mitzusenden.
// - Es wird bewusst KEINE Admin-Rolle verlangt (jeder eingeloggte Benutzer
//   darf - und muss bei jedem Login - seinen EIGENEN Zeitstempel schreiben
//   dürfen), anders als admin-users.js, das ausschließlich Admins
//   vorbehalten ist. Sichtbar ist der gespeicherte Wert weiterhin nur
//   innerhalb der admin-Benutzerverwaltung (deren Rollenprüfung in
//   admin-users.js unverändert bleibt).
// - Es wird ausschließlich das Feld user_metadata.last_login verändert:
//   zunächst wird der aktuelle Benutzer-Datensatz gelesen und dessen
//   bestehendes user_metadata vollständig übernommen, dann wird darin nur
//   last_login ergänzt/überschrieben (z.B. full_name bleibt garantiert
//   erhalten). app_metadata (roles/permissions) wird von dieser Function
//   nie gesendet und bleibt dadurch unangetastet.

function json(statusCode, data) {
  return {
    statusCode: statusCode,
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data)
  };
}

exports.handler = async function (event, context) {
  if (event.httpMethod !== 'POST') {
    return json(405, { error: 'method_not_allowed', message: 'Methode nicht erlaubt.' });
  }

  var clientContext = context.clientContext || {};
  var identity = clientContext.identity;
  var caller = clientContext.user;

  // Kein Identity-Kontext vorhanden (z.B. Identity auf der Site nicht aktiv) -
  // eindeutig vom "nicht eingeloggt"-Fall unterscheiden, genau wie in
  // admin-users.js.
  if (!identity || !identity.url || !identity.token) {
    console.error('[record-last-login] Kein Identity-Kontext verfügbar - ist Netlify Identity auf dieser Site aktiviert?');
    return json(500, { error: 'server_misconfigured', message: 'Serverfehler: Identity ist nicht korrekt konfiguriert.' });
  }

  // Nicht eingeloggt (kein oder ungültiges/abgelaufenes Token im Authorization-Header)
  if (!caller || !caller.sub) {
    return json(401, { error: 'not_authenticated', message: 'Nicht angemeldet.' });
  }

  var adminBase = identity.url.replace(/\/+$/, '') + '/admin';
  var adminHeaders = { 'Authorization': 'Bearer ' + identity.token, 'Content-Type': 'application/json' };

  // NIE aus event.body übernehmen - die eigene, von Netlify verifizierte ID
  // ist die einzig zulässige Quelle (siehe Sicherheits-Hinweis oben).
  var selfId = caller.sub;

  try {
    // Aktuellen Datensatz lesen, um bestehendes user_metadata vollständig zu
    // erhalten - kein "blindes" Überschreiben, das sich nur auf eine
    // ungeprüfte Annahme zu GoTrues PUT-Merge-Verhalten verlassen würde.
    var rGet = await fetch(adminBase + '/users/' + encodeURIComponent(selfId), { headers: adminHeaders });
    var current = await rGet.json().catch(function () { return {}; });
    if (!rGet.ok) {
      console.error('[record-last-login] GET eigener Benutzer fehlgeschlagen', rGet.status, current);
      return json(502, { error: 'upstream_error', message: 'Letzter Login konnte nicht gespeichert werden.' });
    }

    var existingMeta = current.user_metadata || {};
    var mergedUserMetadata = {};
    for (var k in existingMeta) {
      if (Object.prototype.hasOwnProperty.call(existingMeta, k)) {
        mergedUserMetadata[k] = existingMeta[k];
      }
    }
    mergedUserMetadata.last_login = new Date().toISOString();

    var rPut = await fetch(adminBase + '/users/' + encodeURIComponent(selfId), {
      method: 'PUT',
      headers: adminHeaders,
      body: JSON.stringify({ user_metadata: mergedUserMetadata })
    });
    var putBody = await rPut.json().catch(function () { return {}; });
    if (!rPut.ok) {
      console.error('[record-last-login] PUT eigener Benutzer fehlgeschlagen', rPut.status, putBody);
      return json(502, { error: 'update_failed', message: 'Letzter Login konnte nicht gespeichert werden.' });
    }

    return json(200, { ok: true, last_login: mergedUserMetadata.last_login });
  } catch (e) {
    console.error('[record-last-login] Unerwarteter Fehler', e);
    return json(500, { error: 'internal_error', message: 'Unerwarteter Serverfehler.' });
  }
};
