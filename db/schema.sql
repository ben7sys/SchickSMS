-- SchickSMS Datenbankschema

-- Kontakte-Tabelle
CREATE TABLE IF NOT EXISTS contacts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    number TEXT NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- SMS-Verlauf-Tabelle
CREATE TABLE IF NOT EXISTS sms_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    recipient TEXT NOT NULL,
    message TEXT NOT NULL,
    status TEXT NOT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    filename TEXT,
    archived INTEGER DEFAULT 0
);

-- Indizes für schnellere Suche
CREATE INDEX IF NOT EXISTS idx_contacts_number ON contacts(number);
CREATE INDEX IF NOT EXISTS idx_sms_recipient ON sms_history(recipient);
CREATE INDEX IF NOT EXISTS idx_sms_status ON sms_history(status);
CREATE INDEX IF NOT EXISTS idx_sms_archived ON sms_history(archived);
