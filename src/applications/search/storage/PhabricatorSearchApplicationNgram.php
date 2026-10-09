<?php

final class PhabricatorSearchApplicationNgram extends PhabricatorSearchDAO {

  protected $objectPHID;
  protected $ngram;

  protected function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_COLUMN_SCHEMA => array(
        'objectPHID' => 'phid',
        'ngram' => 'bytes12',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_ngram' => array('columns' => array('ngram', 'objectPHID'),
          'unique' => true),
        'key_object' => array('columns' => array('objectPHID')),
      ),
    ) + parent::getConfiguration();
  }

}
