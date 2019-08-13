CREATE TABLE `logs`
(
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    NAME VARCHAR(16),
    TYPE VARCHAR(32)
);

CREATE UNIQUE INDEX `logs_hash_uindex`
    ON `logs` (NAME);

CREATE UNIQUE INDEX `logs_id_uindex`
    ON `logs` (id);

CREATE TABLE `logs_data`
(
    `id`     INT PRIMARY KEY AUTO_INCREMENT,
    `log_id` INT,
    `key`    VARCHAR(255),
    `value`  TEXT

);

CREATE INDEX `logs_data_log_id_index`
    ON `logs_data` (log_id);

CREATE TABLE `logs_messages`
(
    `id`       INT PRIMARY KEY AUTO_INCREMENT,
    `type`     VARCHAR(64),
    `message`  TEXT,
    `category` TEXT,
    `time`     REAL,
    `log_id`   INT
);

CREATE INDEX `logs_messages_log_id_index`
    ON `logs_messages` (log_id);