SELECT 'CREATE DATABASE pushusers'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'pushusers')\gexec

\c pushusers;

CREATE TABLE IF NOT EXISTS pushtokens (
    devicetoken VARCHAR(255) PRIMARY KEY,
    user_id UUID NOT NULL
);

-- Every notify.php lookup filters by user_id; the reference schema this was adapted from only
-- indexed the primary key (devicetoken), leaving every notification-send a full table scan.
CREATE INDEX IF NOT EXISTS idx_pushtokens_user_id ON pushtokens (user_id);
