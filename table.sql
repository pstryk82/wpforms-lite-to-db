-- auto-generated definition
CREATE TABLE wp_participant
(
  id                INT AUTO_INCREMENT
    PRIMARY KEY,
  competition_id    INT         NOT NULL,
  name              VARCHAR(50) NOT NULL,
  gender            ENUM('K', 'M') NOT NULL,
  gear              VARCHAR(30) NULL,
  age_category      VARCHAR(30) NULL,
  club_or_city      VARCHAR(30) NULL,
  email             VARCHAR(50) NULL,
  send_confirmation TINYINT(1)  NULL,
  message           TEXT        NULL,
  meal_type         ENUM('T', 'W') NOT NULL,
)
  ENGINE = InnoDB, CHARSET = utf8;

CREATE INDEX competition_id
  ON wp_participant (competition_id);

