#
# Table structure for table 'tx_extensionmanager_domain_model_repository'
#
CREATE TABLE tx_extensionmanager_domain_model_repository (
  title varchar(150) NOT NULL default '',
  description mediumtext,
  wsdl_url varchar(100) NOT NULL default '',
  mirror_list_url varchar(100) NOT NULL default '',
  last_update int(11) unsigned DEFAULT '0' NOT NULL,
  extension_count int(11) DEFAULT '0' NOT NULL
);

#
# Table structure for table 'tx_extensionmanager_domain_model_extension'
#
CREATE TABLE tx_extensionmanager_domain_model_extension (
  extension_key varchar(60) NOT NULL default '',
  repository int(11) unsigned NOT NULL default '1',
  version varchar(15) NOT NULL default '',
  alldownloadcounter int(11) unsigned NOT NULL default '0',
  downloadcounter int(11) unsigned NOT NULL default '0',
  title varchar(150) NOT NULL default '',
  description mediumtext,
  state int(4) NOT NULL default '0',
  review_state int(4) NOT NULL default '0',
  category int(4) NOT NULL default '0',
  last_updated int(11) unsigned NOT NULL default '0',
  serialized_dependencies mediumtext,
  author_name varchar(255) NOT NULL default '',
  author_email varchar(255) NOT NULL default '',
  ownerusername varchar(50) NOT NULL default '',
  md5hash varchar(35) NOT NULL default '',
  update_comment mediumtext,
  authorcompany varchar(255) NOT NULL default '',
  integer_version int(11) NOT NULL default '0',
  current_version int(3) NOT NULL default '0',
  lastreviewedversion int(3) NOT NULL default '0',

  KEY index_extrepo (extension_key,repository),
  KEY index_versionrepo (integer_version,repository,extension_key),
  KEY index_currentversions (current_version,review_state),
  UNIQUE versionextrepo (extension_key,version,repository)
);
