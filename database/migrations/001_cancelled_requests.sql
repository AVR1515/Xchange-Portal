ALTER TABLE requests
    MODIFY COLUMN status ENUM('open','submitted','returned','accepted','cancelled')
    NOT NULL DEFAULT 'open';
