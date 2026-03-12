# WordPress Plugin Specification
## CrowdBook – Collaborative SML Book Platform

**Version:** 1.0 Spec  
**Author:** Olaf Japp  
**For:** Codex (AI Code Generation)  
**Date:** 2026-03-12

---

## Overview

Extend the existing WordPress SML plugin to support a collaborative book platform where users can register, write chapters in Markdown, and contribute to a branching narrative book structure.

The platform is NOT using WordPress users. It has its own lightweight user system.

---

## Existing Foundation

The existing plugin already provides:
- SML pages that render to static HTML
- `tweak` as template engine
- Markdown rendering within SML
- Monaco editor with syntax highlighting
- Static HTML generation on save

Do NOT rewrite existing functionality. Extend it.

---

## New Features to Implement

### 1. User System (Custom – No WordPress Users)

Users are stored in a **custom database table** (not WordPress user tables).

#### Database Table: `crowdbook_users`

```sql
CREATE TABLE crowdbook_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(200) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(200),
    bio TEXT,
    status ENUM('pending', 'approved', 'rejected', 'banned') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    approved_at DATETIME NULL,
    approved_by INT NULL
);
```

#### User Registration Flow
1. User fills out registration form (username, email, password, display_name, optional bio)
2. Status is set to `pending`
3. Admin receives notification in WordPress dashboard
4. Admin approves or rejects the user
5. On approval: user receives email notification
6. Approved users can log in and write chapters

#### Session Management
- Use PHP sessions (not WordPress cookies)
- Session key: `crowdbook_user_id`
- Login/logout via custom endpoints

#### Admin Interface (WordPress Dashboard)
- Menu item: **CrowdBook → Users**
- List all users with status
- Approve / Reject / Ban buttons
- Filter by status

---

### 2. Chapter System

#### Database Table: `crowdbook_chapters`

```sql
CREATE TABLE crowdbook_chapters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    book_id VARCHAR(100) NOT NULL,
    parent_id INT NULL,
    author_id INT NOT NULL,
    title VARCHAR(300) NOT NULL,
    slug VARCHAR(300) NOT NULL,
    markdown_content LONGTEXT,
    sml_template VARCHAR(100) DEFAULT 'tweak',
    status ENUM('draft', 'pending_review', 'published', 'rejected') DEFAULT 'draft',
    path_label VARCHAR(100),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    published_at DATETIME NULL,
    FOREIGN KEY (author_id) REFERENCES crowdbook_users(id)
);
```

#### Chapter Structure in SML
Each chapter is wrapped in a fixed SML structure. The user only writes the Markdown content and title. The plugin generates the full SML automatically:

```sml
Page {
    template: "tweak"
    
    Chapter {
        book: "[book_id]"
        title: "[user_title]"
        author: "[display_name]"
        path: "[path_label]"
        
        Markdown {
            content: "[user_markdown_content]"
        }
        
        Navigation {
            back: "Choose your path"
            back_url: "/[book_id]/"
        }
    }
}
```

The user never sees the SML wrapper. They only edit:
- **Title** (text input)
- **Path Label** (short label, e.g. "Ubuntu Path", "Rainbow Way") 
- **Markdown Content** (Monaco editor)

---

### 3. Frontend – User-Facing Pages

#### Registration Page
- Shortcode: `[crowdbook_register]`
- Fields: username, email, password, password confirm, display name, bio (optional)
- On submit: creates pending user, shows success message

#### Login Page
- Shortcode: `[crowdbook_login]`
- Fields: email/username, password
- On success: redirect to writer dashboard

#### Writer Dashboard
- Shortcode: `[crowdbook_dashboard]`
- Shows: list of user's own chapters with status
- Actions: New Chapter, Edit Draft, View Published
- Only accessible to approved + logged-in users

#### Chapter Editor Page
- Shortcode: `[crowdbook_editor]`
- Fields:
  - Title (text input)
  - Path Label (text input, short)
  - Content (Monaco editor, Markdown mode)
- Save as Draft button
- Submit for Review button
- On submit: status changes to `pending_review`

#### Book Index Page
- Shortcode: `[crowdbook_index book="[book_id]"]`
- Shows: prologue + all published chapters as branching paths
- Each path shows: title, author name, short excerpt
- "Read" button → goes to chapter
- At bottom: "Write your own path" → registration/login

---

### 4. Moderation

#### Admin Interface: CrowdBook → Chapters
- List all chapters filtered by status
- Columns: title, author, book, path label, status, submitted date
- Actions: Preview, Approve (publish), Reject (with optional reason)
- On approval: static HTML is generated from SML template + markdown
- On rejection: author receives notification with reason

---

### 5. Static HTML Generation

On chapter approval/publish:
1. Generate full SML from template + user content
2. Render SML → HTML (using existing rendering pipeline)
3. Save static HTML to `/wp-content/uploads/crowdbook/[book_id]/[slug].html`
4. Register as accessible URL

---

### 6. Email Notifications

Simple wp_mail() based notifications:

| Event | Recipient | Subject |
|-------|-----------|---------|
| New registration | Admin | New author registration pending |
| User approved | User | Your account has been approved |
| User rejected | User | Your registration status |
| Chapter submitted | Admin | New chapter pending review |
| Chapter approved | User | Your chapter has been published |
| Chapter rejected | User | Feedback on your chapter |

---

### 7. Security

- All user inputs sanitized with `sanitize_text_field()` and `wp_kses()`
- Passwords hashed with `password_hash()` / verified with `password_verify()`
- CSRF protection via WordPress nonces on all forms
- Rate limiting on registration (max 3 attempts per IP per hour)
- Only approved users can submit chapters
- Only logged-in users can access editor

---

## File Structure

```
crowdbook/
├── crowdbook.php              # Main plugin file, hooks
├── includes/
│   ├── class-users.php        # User system
│   ├── class-chapters.php     # Chapter system
│   ├── class-mailer.php       # Email notifications
│   ├── class-sml-renderer.php # SML generation from markdown
│   └── class-static-gen.php   # Static HTML generation
├── admin/
│   ├── users-page.php         # Admin: user management
│   └── chapters-page.php      # Admin: chapter moderation
├── frontend/
│   ├── register.php           # Registration shortcode
│   ├── login.php              # Login shortcode
│   ├── dashboard.php          # Writer dashboard shortcode
│   ├── editor.php             # Chapter editor shortcode
│   └── book-index.php         # Book index shortcode
├── templates/
│   └── tweak/                 # Default SML template
├── assets/
│   ├── monaco/                # Monaco editor (already exists)
│   └── crowdbook.css          # Frontend styles
└── languages/                 # i18n ready
```

---

## Integration with Existing Plugin

- Reuse existing SML → HTML rendering pipeline
- Reuse Monaco editor integration
- Reuse static file generation on save
- Add new database tables on plugin activation via `register_activation_hook`
- New admin menu items under existing CrowdBook/SML menu

---

## Out of Scope (v1.0)

- Social login (GitHub, Google)
- Comments on chapters
- Voting / rating chapters
- Multiple books simultaneously (prepare DB for it, but UI for one book)
- Payment / commercial features

---

## Notes for Codex

- Use WordPress coding standards (https://developer.wordpress.org/coding-standards/)
- All database queries via `$wpdb` with prepared statements
- No external PHP dependencies – vanilla WordPress only
- Monaco editor is already integrated – just initialize it on the editor textarea
- The SML rendering pipeline already exists – call it, don't rewrite it
- Test with PHP 8.1+

---

*This spec was written as part of the CrowdBook / "Choose Your Incarnation" project.*  
*crowdware.info*
