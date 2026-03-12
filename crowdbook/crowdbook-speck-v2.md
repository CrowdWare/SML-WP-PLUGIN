# CrowdBook WordPress Plugin
## Die Speck (Specification) – v2.0
## Mit Speckmantel (vollständig dokumentiert für Codex)

**Projekt:** CrowdBook – Kollaborative Buch-Plattform
**Autor:** Olaf Japp / CrowdWare
**Website:** crowdware.info
**Für:** Codex (AI Code Generation)
**Datum:** 2026-03-12

---

## Glossar (DKI-Übersetzungsschicht)

*Die DKI (Diktier-KI / ForgeSTA) erfindet manchmal neue Begriffe. Diese Tabelle hilft beim Verständnis:*

| DKI-Begriff | Echte Bedeutung |
|-------------|-----------------|
| Speck | Spec / Specification |
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
    display_name VARCHAR(200) NOT NULL,
    email VARCHAR(200) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    bio TEXT,
    status ENUM('pending', 'approved', 'banned') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    approved_at DATETIME NULL
);
```

**Hinweise:**
- Email ist optional sichtbar – nur für Admin und Benachrichtigungen
- Display Name kann Pseudonym sein – wird im Kapitel angezeigt
- Kein Realname erforderlich
- Status `pending` bis Admin approvet – oder Ollama-Filter übernimmt

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
    status ENUM('draft', 'pending', 'published', 'rejected') DEFAULT 'pending',
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
    fingerprint VARCHAR(64) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_like (chapter_id, fingerprint),
    FOREIGN KEY (chapter_id) REFERENCES crowdbook_chapters(id)
);
```

**Hinweis zu Likes:**
- Kein Login nötig zum Liken
- Fingerprint = Hash aus IP + User Agent (anonym, nicht rückverfolgbar)
- Verhindert Doppel-Likes ohne Tracking

---

## 2. User System

### Registrierung
- Formular: Display Name, Email, Passwort, Passwort wiederholen, Bio (optional)
- Kein Realname erforderlich – Pseudonym explizit erlaubt
- Nach Registrierung: Status `pending`
- Ollama prüft Bio-Text auf Spam
- Bei Spam-Score > 0.8: still verwerfen
- Bei Spam-Score < 0.8: automatisch auf `approved` setzen
- Admin kann jederzeit manuell eingreifen

### Login / Session
- PHP Sessions (keine WordPress Cookies)
- Session Key: `crowdbook_user_id`
- Login via Email + Passwort

### Passwort
- `password_hash()` / `password_verify()`
- Passwort Reset via Email-Link

---

## 3. Spam Filter (Ollama – lokal)

**Wichtig: Kein Akismet, kein Cross-Domain, keine externen APIs.**
Alles läuft lokal auf dem Server.

### Voraussetzung
```bash
# Ollama muss auf dem Server installiert sein
# Modell: phi3:mini (2.3GB, läuft auf 8GB RAM)
ollama pull phi3:mini
```

### PHP Integration

```php
function crowdbook_check_spam($text) {
    $prompt = "You are a spam filter. Analyze this text and respond with ONLY 'spam' or 'ok'. 
    Spam includes: advertising, viagra, casino, penis enlargement, SEO links, gibberish.
    Ok includes: personal stories, spiritual content, nature, community, creative writing.
    
    Text: " . $text;
    
    $response = wp_remote_post('http://localhost:11434/api/generate', [
        'body' => json_encode([
            'model' => 'phi3:mini',
            'prompt' => $prompt,
            'stream' => false
        ]),
        'headers' => ['Content-Type' => 'application/json'],
        'timeout' => 30
    ]);
    
    $body = json_decode(wp_remote_retrieve_body($response));
    $result = strtolower(trim($body->response));
    
    return $result === 'spam';
}
```

### Spam Flow
```
Text eingereicht
        ↓
Ollama: spam oder ok?
        ↓
spam → still verwerfen + Admin-Notiz
ok   → publizieren
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
4. "Als Entwurf speichern" Button
5. "Einreichen" Button

**User sieht NICHT:**
- SML Wrapper
- Template Konfiguration
- Technische Details

### SML Wrapper (automatisch generiert)

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
- Prolog (fest, von Olaf)
- Alle publizierten Kapitel als Karten
- Pro Karte: Titel, Autor, Path Label, Excerpt (erste 150 Zeichen), Like-Anzahl
- "Lesen" Button
- Am Ende: "Schreib deinen eigenen Weg" → Registrierung/Login

---

## 6. Likes

**Newscaster: Likes ohne Login**

- Nur 👍 – kein 👎
- Kein Login nötig
- Fingerprint verhindert Spam-Likes
- Like-Anzahl sichtbar auf Index und Kapitel
- Autor bekommt Email wenn Kapitel 1, 5, 10, 25, 50, 100 Likes erreicht

```javascript
// Ajax Like ohne Reload
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
    // Like-Anzahl aktualisieren
    document.querySelector('.like-count').textContent = data.count;
    // Button deaktivieren
    likeButton.disabled = true;
    likeButton.classList.add('liked');
});
```

---

## 7. Social Sharing (Newscaster #2)

**Ohne externe Scripts. Ohne Plugins. Nur HTML + Links.**

### Open Graph Tags (automatisch pro Kapitel)

```html
<!-- Im <head> jedes publizierten Kapitels -->
<meta property="og:title" content="[Kapitel Titel] – Choose Your Incarnation">
<meta property="og:description" content="[Erste 150 Zeichen des Kapitels]">
<meta property="og:image" content="[crowdware.info/images/crowdbook-share.jpg]">
<meta property="og:url" content="[Vollständige URL des Kapitels]">
<meta property="og:type" content="article">
<meta property="og:site_name" content="Choose Your Incarnation – crowdware.info">

<!-- Twitter/X -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="[Kapitel Titel]">
<meta name="twitter:description" content="[Erste 150 Zeichen]">
<meta name="twitter:image" content="[crowdware.info/images/crowdbook-share.jpg]">
```

### Share Buttons (nur Links, kein JavaScript SDK)

```html
<!-- Telegram -->
<a href="https://t.me/share/url?url=[URL]&text=[TITLE]" target="_blank">
    Telegram teilen
</a>

<!-- WhatsApp -->
<a href="https://wa.me/?text=[TITLE]%20[URL]" target="_blank">
    WhatsApp teilen
</a>

<!-- Facebook -->
<a href="https://facebook.com/sharer/sharer.php?u=[URL]" target="_blank">
    Facebook teilen
</a>

<!-- X / Twitter -->
<a href="https://x.com/intent/tweet?url=[URL]&text=[TITLE]" target="_blank">
    X teilen
</a>

<!-- Link kopieren -->
<button onclick="navigator.clipboard.writeText('[URL]')">
    Link kopieren
</button>
```

**Ergebnis:** Überall schöne Vorschau – WhatsApp, Telegram, Facebook, Discord, iMessage – ohne einen einzigen externen Script-Tag.

---

## 8. Moderations-Dashboard

### WordPress Admin: CrowdBook → Kapitel

**Spalten:**
- Titel
- Autor (Display Name)
- Path Label
- Status
- Spam Score
- Like Count
- Eingereicht am

**Aktionen:**
- Vorschau
- Publizieren
- Ablehnen
- Löschen (mit Bestätigung)

**Filter:**
- Nach Status
- Nach Datum
- Nach Spam Score

### WordPress Admin: CrowdBook → User

**Spalten:**
- Display Name
- Email
- Status
- Anzahl Kapitel
- Registriert am

**Aktionen:**
- Approven
- Bannen
- Email senden

---

## 9. Email Benachrichtigungen

Alle via `wp_mail()` – kein externer Mail-Service nötig.

| Event | Empfänger | Betreff |
|-------|-----------|---------|
| Neues Kapitel eingereicht | Admin | Neues Kapitel wartet |
| Kapitel publiziert | Autor | Dein Kapitel ist live! |
| Kapitel abgelehnt | Autor | Feedback zu deinem Kapitel |
| 10 Likes erreicht | Autor | 10 Menschen haben deinen Weg gelesen |
| 50 Likes erreicht | Autor | 50 Menschen haben deinen Weg gelesen |

---

## 10. Dateistruktur

```
crowdbook/
├── crowdbook.php                 # Haupt-Plugin, Hooks, Activation
├── includes/
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
│   ├── register.php              # [crowdbook_register] Shortcode
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

## 11. Sicherheit

- Alle Inputs: `sanitize_text_field()` und `wp_kses()`
- Passwörter: `password_hash()` / `password_verify()`
- CSRF: WordPress Nonces auf allen Formularen
- Rate Limiting: max 3 Registrierungen pro IP pro Stunde
- SQL: ausschließlich `$wpdb->prepare()`
- Sessions: serverseitig, kein sensitive Data im Cookie

---

## 12. Technische Voraussetzungen

- PHP 8.1+
- WordPress 6.0+
- Ollama installiert auf Server (`phi3:mini` Modell)
- WordPress Coding Standards
- Keine externen PHP Dependencies

---

## Newscaster Liste (Neue Use Cases für später)

- Android App mit Whisper.cpp für mobile Autoren
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
