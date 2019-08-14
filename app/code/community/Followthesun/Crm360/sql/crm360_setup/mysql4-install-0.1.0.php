<?php

$installer = $this;

$installer->startSetup();

$installer->run("

DROP TABLE IF EXISTS {$this->getTable('crm360/log')};
CREATE TABLE {$this->getTable('crm360/log')} (
  `log_id` int(11) unsigned NOT NULL auto_increment,
  `type` varchar(255) NULL,
  `message` text NULL,
  `status` varchar(50) NULL,
  `created_at` datetime NULL,
  `updated_at` datetime NULL,
  PRIMARY KEY (`log_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
");

$installer->endSetup(); 