// ────────────────────────────────────────────────────────────────────────
// BENUTZERVERWALTUNG – serverseitiger Proxy zur Netlify-Identity-Admin-API
// ────────────────────────────────────────────────────────────────────────
//
// Hintergrund / Ursache des vorherigen Fehlers (05.09.2026):
// Das Admin-UI rief bislang direkt "/.netlify/identity/admin/users" aus dem
// Browser auf, mit dem Bearer-Token des eingeloggten Benutzers. Das schlägt
// IMMER mit HTTP 401 "User not allowed" fehl – unabhängig davon, ob dieser
// Benutzer die Rolle "admin" trägt oder nicht, und unabhängig von jedem
// Token-Refresh. Grund: Netlifys Identity-Admin-API (GoTrue) akzeptiert für
// Admin-Aktionen kein normales Benutzer-Token, sondern ausschließlich das
// site-eigene Admin-Service-Token, das Netlify automatisch (und AUSSCHLIESS-
// LICH) in den Ausführungskontext von Netlify Functions einspeist
// (context.clientContext.identity.token). Ein Browser kann an dieses Token
// grundsätzlich nicht gelangen – das ist so von Netlify vorgesehen, damit
// Admin-Rechte niemals im Client landen. Die Rolle "admin" im Token des
// Benutzers steuert dagegen nur den Zugriff auf git-gateway (das hier für
// alle Inhalte bereits korrekt funktioniert) und diese eigene Function,
// NICHT den direkten Zugriff auf die rohe Identity-Admin-API.
//
// Diese Function ist daher die einzige Stelle, die admin-Aktionen ausführt:
//   Browser → diese Function → Rolle "admin" prüfen → Identity-Admin-API
// Das Admin-Service-Token verlässt diese Function nie in Richtung Client.
//
// Aufruf: GET/POST/PATCH/DELETE /.netlify/functions/admin-users
//   GET                                    → Benutzerliste (inkl. roles + permissions)
//   POST    { email }                      → Benutzer einladen (Einladungs-Mail)
//   PATCH   { id, roles, permissions }     → Rolle(n) + Bereichsrechte eines Benutzers setzen
//   DELETE  ?id=<id>                       → Benutzer entfernen
//
// Granulare Bereichsrechte (05.09.2026, "Benutzerrechte granular pro
// Bereich"): zusätzlich zur groben Rolle ("admin"/"redakteur") kann ein
// Benutzer mit Rolle "redakteur" gezielt einzelne Bereiche freigeschaltet
// bekommen, gespeichert als app_metadata.permissions (Array von Keys, siehe
// PERMISSIONS_BEKANNT unten). "admin" hat unabhängig von permissions immer
// vollen Zugriff. roles und permissions werden bei PATCH immer gemeinsam
// und vollständig ersetzt (siehe Kommentar am PATCH-Handler).
//
// Autorisierung: Netlify entschlüsselt das im Authorization-Header
// mitgesendete Identity-JWT des aufrufenden Benutzers serverseitig selbst
// und stellt das Ergebnis (inkl. app_metadata.roles) als
// context.clientContext.user bereit – das Ergebnis ist vertrauenswürdig,
// weil es von Netlifys Infrastruktur geprüft wurde, nicht vom Client
// behauptet wird. Kein Browser-Parameter wird hier je als Autorisierung
// akzeptiert.

var ROLES_BEKANNT = ['admin', 'redakteur'];

// Granulare Bereichsrechte (05.09.2026, "Benutzerrechte granular pro Bereich").
// WICHTIG - MANUELLER SYNC: Diese Liste MUSS exakt den Keys aus
// PERMISSION_KEYS in admin/admin.js entsprechen (dort die einzige
// sichtbare/gruppierte Quelle für Label + Gruppierung im Admin-UI). Es gibt
// in diesem Projekt kein Build-System, das beide Dateien aus einer
// gemeinsamen Quelle generieren könnte - beim Hinzufügen/Umbenennen eines
// Rechts IMMER beide Stellen anpassen, sonst werden hier vergebene Rechte
// vom Admin-UI ignoriert bzw. dort definierte Rechte hier abgelehnt.
var PERMISSIONS_BEKANNT = [
  'aktuelles', 'termine', 'kontakt', 'inhaltsseiten',
  'vorstand', 'obleute', 'hegeringe', 'kjm', 'jagdhundeschule',
  'hundeboerse', 'waffenboerse', 'partner', 'infomobil',
  'medien',
  'navigation', 'design'
];

function json(statusCode, data) {
  return {
    statusCode: statusCode,
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data)
  };
}

function isValidEmail(email) {
  return typeof email === 'string' && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim());
}

function mapUser(u) {
  return {
    id: u.id,
    email: u.email,
    full_name: (u.user_metadata && u.user_metadata.full_name) || '',
    roles: (u.app_metadata && u.app_metadata.roles) || [],
    permissions: (u.app_metadata && u.app_metadata.permissions) || [],
    status: u.confirmed_at ? 'bestaetigt' : 'eingeladen',
    created_at: u.created_at || null
  };
}

exports.handler = async function (event, context) {
  var clientContext = context.clientContext || {};
  var identity = clientContext.identity;
  var caller = clientContext.user;

  // Kein Identity-Kontext vorhanden (z.B. Identity auf der Site nicht aktiv) -
  // eindeutig vom "nicht eingeloggt"-Fall unterscheiden, damit sich ein
  // echter Konfigurationsfehler nicht als gewöhnliches 401 tarnt.
  if (!identity || !identity.url || !identity.token) {
    console.error('[admin-users] Kein Identity-Kontext verfügbar - ist Netlify Identity auf dieser Site aktiviert?');
    return json(500, { error: 'server_misconfigured', message: 'Serverfehler: Identity ist nicht korrekt konfiguriert.' });
  }

  // Nicht eingeloggt (kein oder ungültiges/abgelaufenes Token im Authorization-Header)
  if (!caller) {
    return json(401, { error: 'not_authenticated', message: 'Nicht angemeldet.' });
  }

  var callerRoles = (caller.app_metadata && caller.app_metadata.roles) || [];
  if (callerRoles.indexOf('admin') === -1) {
    return json(403, { error: 'forbidden', message: 'Keine Adminrechte.' });
  }

  var adminBase = identity.url.replace(/\/+$/, '') + '/admin';
  var adminHeaders = { 'Authorization': 'Bearer ' + identity.token, 'Content-Type': 'application/json' };

  try {
    if (event.httpMethod === 'GET') {
      var r = await fetch(adminBase + '/users?per_page=100', { headers: adminHeaders });
      var body = await r.json().catch(function () { return {}; });
      if (!r.ok) {
        console.error('[admin-users] GET Identity-Admin-API fehlgeschlagen', r.status, body);
        return json(502, { error: 'upstream_error', message: 'Benutzerliste konnte nicht geladen werden.' });
      }
      var users = (body.users || []).map(mapUser);
      return json(200, { users: users });
    }

    if (event.httpMethod === 'POST') {
      var payload = {};
      try { payload = JSON.parse(event.body || '{}'); } catch (e) { /* siehe Validierung unten */ }
      var email = (payload.email || '').trim().toLowerCase();
      if (!isValidEmail(email)) {
        return json(400, { error: 'invalid_email', message: 'Bitte eine gültige E-Mail-Adresse angeben.' });
      }
      // Bewusst OHNE app_metadata/Rolle einladen: der neue Benutzer bekommt
      // zunächst keine besonderen Rechte. Admin-Rechte werden anschließend,
      // falls gewünscht, gezielt über PATCH (Rollen setzen) vergeben - nie
      // automatisch bei der reinen Einladung (Prinzip geringster Rechte).
      var r2 = await fetch(adminBase + '/users', {
        method: 'POST',
        headers: adminHeaders,
        body: JSON.stringify({ email: email })
      });
      var body2 = await r2.json().catch(function () { return {}; });
      if (!r2.ok) {
        console.error('[admin-users] POST Identity-Admin-API fehlgeschlagen', r2.status, body2);
        var msg = body2 && body2.msg;
        return json(r2.status === 422 ? 409 : 502, {
          error: 'invite_failed',
          message: msg ? ('Einladung fehlgeschlagen: ' + msg) : 'Einladung konnte nicht gesendet werden.'
        });
      }
      return json(200, { ok: true, user: mapUser(body2) });
    }

    if (event.httpMethod === 'PATCH') {
      var payload2 = {};
      try { payload2 = JSON.parse(event.body || '{}'); } catch (e) { /* siehe Validierung unten */ }
      var id = payload2.id;
      var roles = Array.isArray(payload2.roles) ? payload2.roles : [];
      // WICHTIG: roles UND permissions werden hier immer gemeinsam und
      // vollständig ersetzt (kein Merge) - genau wie roles es schon vorher
      // getan hat. Das Admin-UI (admin/admin.js, Benutzerverwaltung) schickt
      // daher bei jedem Speichern beide Felder zusammen (permissions ggf. als
      // leeres Array), damit hier nie versehentlich Rechte verloren gehen.
      var permissions = Array.isArray(payload2.permissions) ? payload2.permissions : [];
      if (!id) return json(400, { error: 'missing_id', message: 'Keine Benutzer-ID angegeben.' });
      var unbekannt = roles.filter(function (rl) { return ROLES_BEKANNT.indexOf(rl) === -1; });
      if (unbekannt.length) {
        return json(400, { error: 'unknown_role', message: 'Unbekannte Rolle: ' + unbekannt.join(', ') });
      }
      var unbekanntePerm = permissions.filter(function (p) { return PERMISSIONS_BEKANNT.indexOf(p) === -1; });
      if (unbekanntePerm.length) {
        return json(400, { error: 'unknown_permission', message: 'Unbekanntes Recht: ' + unbekanntePerm.join(', ') });
      }
      // Schutz vor versehentlicher Selbst-Aussperrung: der aufrufende Admin
      // kann sich über diese UI nicht selbst die admin-Rolle entziehen.
      if (id === caller.sub && callerRoles.indexOf('admin') !== -1 && roles.indexOf('admin') === -1) {
        return json(400, { error: 'self_lockout', message: 'Du kannst dir nicht selbst die Admin-Rechte entziehen.' });
      }
      var r3 = await fetch(adminBase + '/users/' + encodeURIComponent(id), {
        method: 'PUT',
        headers: adminHeaders,
        body: JSON.stringify({ app_metadata: { roles: roles, permissions: permissions } })
      });
      var body3 = await r3.json().catch(function () { return {}; });
      if (!r3.ok) {
        console.error('[admin-users] PATCH Identity-Admin-API fehlgeschlagen', r3.status, body3);
        return json(502, { error: 'update_failed', message: 'Rolle konnte nicht geändert werden.' });
      }
      return json(200, { ok: true, user: mapUser(body3) });
    }

    if (event.httpMethod === 'DELETE') {
      var qid = event.queryStringParameters && event.queryStringParameters.id;
      if (!qid) return json(400, { error: 'missing_id', message: 'Keine Benutzer-ID angegeben.' });
      if (qid === caller.sub) {
        return json(400, { error: 'self_delete', message: 'Du kannst dich nicht selbst entfernen.' });
      }
      var r4 = await fetch(adminBase + '/users/' + encodeURIComponent(qid), {
        method: 'DELETE',
        headers: adminHeaders
      });
      if (!r4.ok) {
        var body4 = await r4.json().catch(function () { return {}; });
        console.error('[admin-users] DELETE Identity-Admin-API fehlgeschlagen', r4.status, body4);
        return json(502, { error: 'delete_failed', message: 'Benutzer konnte nicht entfernt werden.' });
      }
      return json(200, { ok: true });
    }

    return json(405, { error: 'method_not_allowed', message: 'Methode nicht erlaubt.' });
  } catch (e) {
    console.error('[admin-users] Unerwarteter Fehler', e);
    return json(500, { error: 'internal_error', message: 'Unerwarteter Serverfehler.' });
  }
};
