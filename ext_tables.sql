#
# Table structure for table 'cache_extbase_reflection'
#
CREATE TABLE cache_extbase_reflection (
  id int(11) unsigned NOT NULL auto_increment,
  identifier varchar(250) DEFAULT '' NOT NULL,
  crdate int(11) unsigned DEFAULT '0' NOT NULL,
  content mediumtext,
  tags mediumtext,
  lifetime int(11) unsigned DEFAULT '0' NOT NULL,
  PRIMARY KEY (id),
  KEY cache_id (identifier)
) ENGINE=InnoDB;