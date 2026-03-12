# CrowdBook WordPress Plugin
## Die Speck (Final) – v3.0 – Gar und fertig
## Mit Speckmantel (vollständig dokumentiert für Codex)

**Projekt:** CrowdBook – Kollaborative Buch-Plattform
**Autor:** Olaf Japp / CrowdWare
**Website:** crowdware.info
**Für:** Codex (AI Code Generation)
**Datum:** 2026-03-12

---

## Glossar (DKI-Übersetzungsschicht)

*Die DKI (Diktier-KI / ForgeSTA) erfindet manchmal neue Begriffe:*

| DKI-Begriff | Echte Bedeutung |
|-------------|-----------------|
| Speck | Spec / Specification |
| Speck ist gar | Spec ist final |
| Newscaster | New Use Case |
| Coder-Welsch | Unverständlicher KI-Output |
| Gerda | ForgeSTA 0.0.1 |
| Gradle-Weihnachten | Unkontrollierter Dependency-Download |
| DKI | Diktier-KI (ForgeSTA) |

---

## Übersicht

CrowdBook ist eine WordPress Plugin-Erweiterung für eine kollaborative Buch-Plattform. Anonyme Autoren schreiben Kapitel in Markdown. Die Kapitel bilden eine verzweigte Erzählung – inspiriert von "The Egg" von Andy Weir.

**Kern-Philosophie:**
- Kein Datenschutz-Problem – alles läuft lokal auf dem Server
- Kein Drittanbieter – kein Akismet, kein Cross-Domain
- Anonym-freundlich – Pseudonyme willkommen
- Passwordless – Magic Link statt Passwort
- Einfach – Autoren schreiben nur Markdown

---

## Bestehendes Fundament (NICHT neu schreiben!)

Das Plugin existiert bereits mit:
- ✅ SML → Static HTML Rendering Pipeline
- ✅ Twig als Template Engine
- ✅ Markdown Rendering in SML
- ✅ Monaco Editor mit Syntax Highlighting
- ✅ Static HTML Generierung beim Speichern

**Regel: Bestehendes NICHT anfassen. Nur erweitern.**

---

## Architektur Übersicht

```
Autor (Browser / Android App / ForgeSTA)
            ↓
    Email eingeben → Magic Link
            ↓
    Monaco Markdown Editor
            ↓
    WordPress CrowdBook Plugin
            ↓
    Ollama (lokal) → Spam-Filter
            ↓
    Static HTML generieren
            ↓
    "Choose Your Incarnation" Buch
            ↓
    Leser → Like → Share → neue Autoren
```

---

## 1. Datenbank

### Tabelle: `crowdbook_users`

```sql
CREATE TABLE crowdbook_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(200) NOT NULL UNIQUE,  -- Die Identität. Alles.
    display_name VARCHAR(200) NOT NULL,  -- Kann Pseudonym sein
    bio TEXT,
    status ENUM('active', 'banned') DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    -- Kein Passwort. Kein password_hash. Nicht nötig.
);
```

### Tabelle: `crowdbook_magic_tokens`

```sql
CREATE TABLE crowdbook_magic_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(200) NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,  -- 15 Minuten ab Erstellung
    used TINYINT DEFAULT 0         -- Einmalig verwendbar
);
```

### Tabelle: `crowdbook_chapters`

```sql
CREATE TABLE crowdbook_chapters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    book_id VARCHAR(100) NOT NULL DEFAULT 'choose-your-incarnation',
    author_id INT NOT NULL,
    title VARCHAR(300) NOT NULL,
    slug VARCHAR(300) NOT NULL UNIQUE,
    path_label VARCHAR(100),
    markdown_content LONGTEXT,
    status ENUM('draft', 'published', 'rejected') DEFAULT 'draft',
    spam_score FLOAT DEFAULT 0.0,
    like_count INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    published_at DATETIME NULL,
    FOREIGN KEY (author_id) REFERENCES crowdbook_users(id)
);
```

### Tabelle: `crowdbook_likes`

```sql
CREATE TABLE crowdbook_likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chapter_id INT NOT NULL,
    fingerprint VARCHAR(64) NOT NULL,  -- Hash aus IP + User Agent
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_like (chapter_id, fingerprint),
    FOREIGN KEY (chapter_id) REFERENCES crowdbook_chapters(id)
);
```

---

## 2. Magic Link Authentication

**Kein Passwort. Die Email ist die Identität.**

### Warum Magic Link sicherer ist als Passwörter

Viele User benutzen überall dieselbe Email + dasselbe Passwort. Ein einziger Hack irgendwo – alle Accounts kompromittiert. Magic Link hat kein Passwort das gestohlen werden kann.

Einziger Angriffspunkt: das Email-Postfach. Aber das gilt für "Passwort vergessen" genauso.

### Flow: Erster Besuch (= automatische Registrierung)

```
User gibt Email + Display Name ein
            ↓
Email noch nicht bekannt?
            ↓
Account still erstellt (status: active)
            ↓
Magic Link Email gesendet
            ↓
User klickt Link
            ↓
Token validiert → Session erstellt → Cookie gesetzt
            ↓
Eingeloggt ✅
```

### Flow: Wiederkehrender User

```
User gibt Email ein
            ↓
"Magic Link senden" Button
            ↓
Email mit Link
            ↓
Klicken → eingeloggt ✅
```

### Token Generierung

```php
// Kryptografisch sicher, 64 Zeichen
$token = bin2hex(random_bytes(32));
$expires = date('Y-m-d H:i:s', time() + 900); // 15 Minuten

// In DB speichern
$wpdb->insert('crowdbook_magic_tokens', [
    'email'      => $email,
    'token'      => $token,
    'expires_at' => $expires,
    'used'       => 0
]);

// Email senden
$link = home_url('/crowdbook/auth?token=' . $token);
wp_mail($email, 'Dein Login Link', 'Klick hier: ' . $link);
```

### Token Validierung

```php
function crowdbook_validate_token($token) {
    global $wpdb;
    
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM crowdbook_magic_tokens 
         WHERE token = %s 
         AND used = 0 
         AND expires_at > NOW()",
        $token
    ));
    
    if (!$row) return false;
    
    // Token einmalig markieren
    $wpdb->update('crowdbook_magic_tokens', 
        ['used' => 1], 
        ['token' => $token]
    );
    
    // Session erstellen
    session_start();
    $_SESSION['crowdbook_email'] = $row->email;
    
    return true;
}
```

### Session & Cookie

- PHP Session hält Login aufrecht
- Cookie läuft nach 30 Tagen ab
- Handy-Browser: bleibt meist dauerhaft eingeloggt
- Cookie weg → einfach neue Magic Link Email anfordern

---

## 3. Spam Filter (Ollama – lokal)

**Kein Akismet. Kein Cross-Domain. Alles lokal.**

### Voraussetzung

```bash
# Ollama auf Server installieren
curl -fsSL https://ollama.com/install.sh | sh
ollama pull phi3:mini  # 2.3GB, läuft auf 8GB RAM
```

### PHP Integration

```php
function crowdbook_check_spam($text) {
    $prompt = "You are a spam filter. Analyze this text and respond with ONLY 'spam' or 'ok'.
    Spam: advertising, viagra, casino, penis enlargement, SEO links, gibberish, unrelated commercial content.
    Ok: personal stories, spiritual content, nature, community, creative writing, life experiences.
    
    Text: " . substr($text, 0, 500); // Erste 500 Zeichen reichen
    
    $response = wp_remote_post('http://localhost:11434/api/generate', [
        'body' => json_encode([
            'model'  => 'phi3:mini',
            'prompt' => $prompt,
            'stream' => false
        ]),
        'headers' => ['Content-Type' => 'application/json'],
        'timeout' => 30
    ]);
    
    if (is_wp_error($response)) return false; // Im Zweifel: ok
    
    $body = json_decode(wp_remote_retrieve_body($response));
    return strtolower(trim($body->response)) === 'spam';
}
```

### Spam Flow

```
Kapitel eingereicht
        ↓
Ollama: spam oder ok?
        ↓
spam → still verwerfen + Admin-Notiz in Dashboard
ok   → automatisch publizieren
        ↓
Admin kann jederzeit manuell löschen
```

---

## 4. Kapitel Editor

### Shortcode: `[crowdbook_editor]`

**User sieht nur:**
1. Title (Text Input)
2. Path Label (kurz, z.B. "Ubuntu Weg", "Rainbow Pfad")
3. Markdown Content (Monaco Editor)
4. "Entwurf speichern" Button
5. "Einreichen" Button

**User sieht NICHT:**
- SML Wrapper
- Template Konfiguration
- Technische Details

### SML Wrapper (automatisch generiert beim Publizieren)

```sml
Page {
    template: "tweak"
    
    Chapter {
        book: "choose-your-incarnation"
        title: "[user_title]"
        author: "[display_name]"
        path: "[path_label]"
        
        Markdown {
            content: "[user_markdown_content]"
        }
        
        Navigation {
            back: "Wähle deinen Weg"
            back_url: "/choose-your-incarnation/"
        }
    }
}
```

---

## 5. Buch Index

### Shortcode: `[crowdbook_index book="choose-your-incarnation"]`

**Zeigt:**
- Prolog (fest, von Olaf Japp)
- Alle publizierten Kapitel als Karten
- Pro Karte: Titel, Autor (Display Name), Path Label, Excerpt (150 Zeichen), Like-Anzahl
- "Lesen" Button
- Am Ende: "Schreib deinen eigenen Weg" → Login/Registrierung

---

## 6. Likes (Newscaster: Likes ohne Login)

- Nur 👍 – kein 👎
- Kein Login nötig zum Liken
- Fingerprint = Hash(IP + User Agent) – anonym, nicht rückverfolgbar
- Verhindert Doppel-Likes ohne Tracking

```javascript
fetch('/wp-admin/admin-ajax.php', {
    method: 'POST',
    body: new URLSearchParams({
        action: 'crowdbook_like',
        chapter_id: chapterId,
        nonce: crowdbook.nonce
    })
})
.then(r => r.json())
.then(data => {
    document.querySelector('.like-count').textContent = data.count;
    likeButton.disabled = true;
    likeButton.classList.add('liked');
});
```

**Email Benachrichtigung bei:** 1, 5, 10, 25, 50, 100 Likes

---

## 7. Social Sharing (Newscaster #2)

**Ohne externe Scripts. Ohne Plugins. Nur HTML.**

### Open Graph Tags (automatisch pro Kapitel)

```html
<meta property="og:title" content="[Titel] – Choose Your Incarnation">
<meta property="og:description" content="[Erste 150 Zeichen]">
<meta property="og:image" content="[crowdware.info/images/crowdbook-share.jpg]">
<meta property="og:url" content="[Vollständige URL]">
<meta property="og:type" content="article">
<meta property="og:site_name" content="Choose Your Incarnation – crowdware.info">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="[Titel]">
<meta name="twitter:description" content="[Erste 150 Zeichen]">
<meta name="twitter:image" content="[crowdware.info/images/crowdbook-share.jpg]">
```

**Ergebnis:** Überall schöne Vorschau – WhatsApp, Telegram, Facebook, Discord, iMessage.

### Share Buttons (nur Links, kein JavaScript SDK)

```html
<a href="https://t.me/share/url?url=[URL]&text=[TITLE]" target="_blank">Telegram</a>
<a href="https://wa.me/?text=[TITLE]%20[URL]" target="_blank">WhatsApp</a>
<a href="https://facebook.com/sharer/sharer.php?u=[URL]" target="_blank">Facebook</a>
<a href="https://x.com/intent/tweet?url=[URL]&text=[TITLE]" target="_blank">X</a>
<button onclick="navigator.clipboard.writeText('[URL]')">Link kopieren</button>
```

---

## 8. Moderations-Dashboard

### WordPress Admin: CrowdBook → Kapitel

**Spalten:** Titel, Autor, Path Label, Status, Spam Score, Likes, Datum

**Aktionen:** Vorschau / Publizieren / Ablehnen / Löschen

**Filter:** Nach Status / Datum / Spam Score

### WordPress Admin: CrowdBook → User

**Spalten:** Display Name, Email, Status, Anzahl Kapitel, Registriert

**Aktionen:** Bannen / Email senden

---

## 9. Email Benachrichtigungen

Alle via `wp_mail()` – kein externer Mail-Service.

| Event | Empfänger | Betreff |
|-------|-----------|---------|
| Magic Link angefordert | User | Dein Login Link |
| Erstes Kapitel publiziert | User | Dein Kapitel ist live! |
| Kapitel abgelehnt | User | Feedback zu deinem Kapitel |
| 10 Likes | User | 10 Menschen haben deinen Weg gelesen |
| 50 Likes | User | 50 Menschen haben deinen Weg gelesen |
| Neues Kapitel (Spam-verdächtig) | Admin | Kapitel zur Prüfung |

---

## 10. Sicherheit

- Inputs: `sanitize_text_field()` und `wp_kses()`
- Kein Passwort gespeichert – Magic Link only
- Token: kryptografisch sicher, 15 Min, einmalig
- CSRF: WordPress Nonces auf allen Formularen
- Rate Limiting: max 3 Magic Link Requests pro Email pro Stunde
- SQL: ausschließlich `$wpdb->prepare()`
- Sessions: serverseitig

---

## 11. Dateistruktur

```
crowdbook/
├── crowdbook.php                 # Haupt-Plugin, Hooks, Activation
├── includes/
│   ├── class-auth.php            # Magic Link Authentication
│   ├── class-users.php           # User System
│   ├── class-chapters.php        # Kapitel System
│   ├── class-spam-filter.php     # Ollama Integration
│   ├── class-likes.php           # Like System
│   ├── class-social.php          # Open Graph + Share Buttons
│   ├── class-mailer.php          # Email Benachrichtigungen
│   └── class-sml-renderer.php    # SML aus Markdown generieren
├── admin/
│   ├── chapters-page.php         # Admin: Kapitel Moderation
│   └── users-page.php            # Admin: User Verwaltung
├── frontend/
│   ├── auth.php                  # Magic Link Handler
│   ├── login.php                 # [crowdbook_login] Shortcode
│   ├── dashboard.php             # [crowdbook_dashboard] Shortcode
│   ├── editor.php                # [crowdbook_editor] Shortcode
│   └── book-index.php            # [crowdbook_index] Shortcode
├── assets/
│   ├── monaco/                   # Monaco Editor (bereits vorhanden)
│   └── crowdbook.css             # Frontend Styles
└── languages/                    # i18n vorbereitet
```

---

## 12. Technische Voraussetzungen

- PHP 8.1+
- WordPress 6.0+
- Ollama auf Server (`phi3:mini` Modell)
- WordPress Coding Standards
- Keine externen PHP Dependencies
- Server: min. 4GB RAM (8GB empfohlen)

---

## Newscaster Liste (Zukünftige Use Cases)

- Android App mit Whisper.cpp für mobile Autoren (Sister in Málaga)
- ForgeSTA Integration – Markdown direkt vom Mac einspielen
- Mehrere Bücher gleichzeitig
- Kapitel-Baum Visualisierung
- Mehrsprachigkeit (DE/EN)

---

*"The book is open. The pen is yours."*
*crowdware.info*

---

*Diese Speck wurde mit ForgeSTA (Gerda 0.0.1) diktiert,*
*von Claude (Anthropic) strukturiert,*
*und wird von Codex (OpenAI) implementiert.*
*Ubuntu in der Praxis.* 🌱

*Der Speck ist gar.*
