CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    legacy_user_id INTEGER NULL UNIQUE,
    name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL CHECK (role IN ('admin', 'creator')),
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS feedbacks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    slug TEXT NOT NULL UNIQUE,
    status TEXT NOT NULL CHECK (status IN ('draft', 'live', 'closed')),
    layout TEXT NOT NULL CHECK (layout IN ('one-per-page', 'classic')),
    description TEXT NOT NULL DEFAULT '',
    settings_json TEXT NOT NULL DEFAULT '{}',
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS activity_blocks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    feedback_id INTEGER NOT NULL,
    activity_type TEXT NOT NULL,
    activity_data TEXT NOT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    page_number INTEGER NOT NULL DEFAULT 0,
    is_required INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (feedback_id) REFERENCES feedbacks(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS responses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    feedback_id INTEGER NOT NULL,
    session_token TEXT NOT NULL UNIQUE,
    submitted_at TEXT NOT NULL,
    created_at TEXT NOT NULL,
    FOREIGN KEY (feedback_id) REFERENCES feedbacks(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS answers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    response_id INTEGER NOT NULL,
    block_id INTEGER NOT NULL,
    value_text TEXT NULL,
    value_json TEXT NULL,
    media_path TEXT NULL,
    created_at TEXT NOT NULL,
    FOREIGN KEY (response_id) REFERENCES responses(id) ON DELETE CASCADE,
    FOREIGN KEY (block_id) REFERENCES activity_blocks(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS media (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    feedback_id INTEGER NULL,
    block_id INTEGER NULL,
    response_id INTEGER NULL,
    kind TEXT NOT NULL,
    file_name TEXT NOT NULL,
    original_name TEXT NOT NULL,
    mime_type TEXT NOT NULL,
    size_bytes INTEGER NOT NULL,
    created_at TEXT NOT NULL,
    FOREIGN KEY (feedback_id) REFERENCES feedbacks(id) ON DELETE CASCADE,
    FOREIGN KEY (block_id) REFERENCES activity_blocks(id) ON DELETE SET NULL,
    FOREIGN KEY (response_id) REFERENCES responses(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_activity_blocks_feedback_order ON activity_blocks (feedback_id, page_number, sort_order);
CREATE INDEX IF NOT EXISTS idx_answers_response_block ON answers (response_id, block_id);
CREATE INDEX IF NOT EXISTS idx_responses_feedback ON responses (feedback_id, submitted_at);