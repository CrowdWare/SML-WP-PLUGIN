# Art of Touch WordPress Theme

Dieses Theme ist ein Port der bestehenden statischen Seite (`docs/*.html`) in ein editierbares WordPress-Setup.

## Enthalten

- Theme: `wordpress-theme/art-of-touch`
- Original-Assets aus `docs/assets` unter `assets/`
- Seed-HTML-Dateien aus `docs/*.html` unter `seed/`
- Importer unter **Werkzeuge -> Art of Touch Import**

## Installation

1. Ordner `art-of-touch` nach `wp-content/themes/` kopieren.
2. Im WordPress-Backend das Theme **Art of Touch** aktivieren.
3. Zu **Werkzeuge -> Art of Touch Import** gehen.
4. **Run Import Now** klicken.

## Ergebnis

- Alle Seed-Seiten werden als WordPress-Seiten angelegt/aktualisiert.
- Startseite wird auf die importierte `index.html` gesetzt (`/home/`).
- Inhalte sind im WordPress-Seiteneditor editierbar.
- Navigation wird als WordPress-Menü angelegt (Location `primary`).

## Hinweise

- Alte interne Links wie `*.html` werden beim Rendern auf WordPress-URLs umgeschrieben.
- Asset-Links wie `assets/...` werden automatisch auf das Theme gemappt.
- Nach dem Import kannst du Seiten-Slugs, Menüstruktur und Inhalte im Backend frei anpassen.
