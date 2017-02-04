CREATE TABLE streams
(
  id TEXT PRIMARY KEY NOT NULL
);
CREATE TABLE events
(
  id INTEGER PRIMARY KEY NOT NULL,
  stream_id TEXT NOT NULL,
  type TEXT NOT NULL,
  event TEXT NOT NULL,
  metadata TEXT NOT NULL,
  occurred_on TEXT NOT NULL,
  version TEXT NOT NULL,
  FOREIGN KEY (stream_id) REFERENCES streams (id) DEFERRABLE INITIALLY DEFERRED
);
CREATE TABLE snapshots
(
  id INTEGER PRIMARY KEY NOT NULL,
  aggregate_type TEXT NOT NULL,
  aggregate_id TEXT NOT NULL,
  type TEXT NOT NULL,
  version INTEGER NOT NULL,
  snapshot TEXT NOT NULL
);