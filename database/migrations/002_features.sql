-- users_settings (password change & lock)
CREATE TABLE IF NOT EXISTS user_settings (
  user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
  failed_logins INTEGER DEFAULT 0,
  last_password_change DATETIME,
  locked_until DATETIME
);

-- audit log
CREATE TABLE IF NOT EXISTS audit_log (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
  action TEXT NOT NULL,
  entity TEXT,
  entity_id INTEGER,
  meta TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- knowledge articles (promoted reports)
CREATE TABLE IF NOT EXISTS knowledge_articles (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  title TEXT NOT NULL,
  summary TEXT NOT NULL,
  ticket_id INTEGER REFERENCES tickets(id),
  report_id INTEGER REFERENCES ticket_reports(id),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- import job logs
CREATE TABLE IF NOT EXISTS import_jobs (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  type TEXT NOT NULL,
  file_name TEXT NOT NULL,
  status TEXT NOT NULL,
  message TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);