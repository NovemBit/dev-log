CREATE TABLE IF NOT EXISTS logs
(
    id   INTEGER
        constraint logs_pk
        primary key autoincrement,
    name varchar(16),
    type varchar(32)
);

create unique index logs_hash_uindex
    on logs (name);

create unique index logs_id_uindex
    on logs (id);

CREATE TABLE IF NOT EXISTS logs_data
(
    id     INTEGER
        constraint logs_data_pk
        primary key autoincrement,
    log_id INTEGER,
    key varchar (255),
    value  text
);

create index logs_data_log_id_index
    on logs_data (log_id);

CREATE TABLE IF NOT EXISTS logs_messages
(
    id       INTEGER
        constraint logs_messages_pk
        primary key autoincrement,
    type     varchar(64),
    message  text,
    category text,
    time     REAL,
    log_id   INTEGER
);

create index logs_messages_log_id_index
    on logs_messages (log_id);