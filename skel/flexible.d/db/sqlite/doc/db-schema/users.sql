CREATE TABLE IF NOT EXISTS users 
( 
 id INTEGER PRIMARY KEY, 
 email TEXT, 
 name TEXT, 
 hash TEXT, 
 token TEXT,
 reset TEXT
);

