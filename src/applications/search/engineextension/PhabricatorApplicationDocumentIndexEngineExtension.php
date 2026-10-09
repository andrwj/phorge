<?php

final class PhabricatorApplicationDocumentIndexEngineExtension
  extends PhabricatorIndexEngineExtension {

  const EXTENSIONKEY = 'application-document';

  public function getExtensionName() {
    return pht('Application Content');
  }

  public function shouldIndexObject($object) {
    return id(new PhabricatorSearchApplicationDocumentEngine())
      ->supportsObject($object);
  }

  public function indexObject(PhabricatorIndexEngine $engine, $object) {
    id(new PhabricatorSearchApplicationDocumentEngine())->indexObject($object);
  }

}
