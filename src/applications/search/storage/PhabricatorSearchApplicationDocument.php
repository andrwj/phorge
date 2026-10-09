<?php

final class PhabricatorSearchApplicationDocument extends PhabricatorSearchDAO {

  protected $objectPHID;
  protected $objectType;
  protected $title;
  protected $body;
  protected $comments;
  protected $corpus;

  protected function getConfiguration() {
    return array(
      self::CONFIG_COLUMN_SCHEMA => array(
        'objectPHID' => 'phid',
        'objectType' => 'text4',
        'title' => 'text',
        'body' => 'text',
        'comments' => 'text',
        'corpus' => 'text',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_object' => array('columns' => array('objectPHID'), 'unique' => true),
        'key_type' => array('columns' => array('objectType')),
      ),
    ) + parent::getConfiguration();
  }

}
