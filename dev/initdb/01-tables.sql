USE dugnaden;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

CREATE TABLE bs_admin_access (
  admin_access_id int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  admin_access_ip varchar(25) NOT NULL DEFAULT '',
  admin_access_date datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  admin_access_success tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE bs_beboer (
  beboer_id int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  beboer_for varchar(100) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL DEFAULT '',
  beboer_etter varchar(100) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL DEFAULT '',
  beboer_rom int(11) NOT NULL DEFAULT '0',
  beboer_flyttet_inn datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  beboer_passord varchar(15) NOT NULL DEFAULT '',
  beboer_spesial tinyint(4) NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE bs_bot (
  bot_id int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  bot_registrert tinyint(1) NOT NULL DEFAULT '0',
  bot_deltager int(11) NOT NULL DEFAULT '0',
  bot_beboer int(11) NOT NULL DEFAULT '0',
  bot_annulert tinyint(4) NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE bs_deltager (
  deltager_id int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  deltager_beboer int(11) NOT NULL DEFAULT '0',
  deltager_dugnad int(11) NOT NULL DEFAULT '0',
  deltager_gjort tinyint(1) DEFAULT '0',
  deltager_type smallint(6) NOT NULL DEFAULT '0',
  deltager_notat varchar(255) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE bs_dugnad (
  dugnad_id int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  dugnad_dato datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  dugnad_slettet tinyint(4) NOT NULL DEFAULT '0',
  dugnad_checked tinyint(4) NOT NULL DEFAULT '0',
  dugnad_type enum('lordag','dagdugnad','anretning','vakt','ryddevakt') NOT NULL DEFAULT 'lordag',
  dugnad_min_kids smallint(3) NOT NULL DEFAULT '10',
  dugnad_max_kids smallint(3) NOT NULL DEFAULT '20'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE bs_innstillinger (
  innstillinger_id int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  innstillinger_felt varchar(64) DEFAULT NULL,
  innstillinger_verdi varchar(64) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE bs_notat (
  notat_id int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  notat_txt varchar(255) NOT NULL DEFAULT '',
  notat_beboer int(11) NOT NULL DEFAULT '0',
  notat_mottaker tinyint(4) NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE bs_rom (
  rom_id int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  rom_nr varchar(15) NOT NULL DEFAULT '0',
  rom_type varchar(5) DEFAULT NULL,
  rom_tlf smallint(6) NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
