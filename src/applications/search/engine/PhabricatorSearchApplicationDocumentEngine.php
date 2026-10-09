<?php

final class PhabricatorSearchApplicationDocumentEngine extends Phobject {

  private static $objects;

  public function getObjects() {
    if (self::$objects !== null) {
      return self::$objects;
    }
    $objects = array();
    foreach (PhabricatorPHIDType::getAllTypes() as $type) {
      $object = $type->newObject();
      if (!($object instanceof PhabricatorLiskDAO) ||
          !($object instanceof PhabricatorPolicyInterface) ||
          $object instanceof PhabricatorApplicationTransaction ||
          $object instanceof PhabricatorApplicationTransactionComment) {
        continue;
      }
      if ($object instanceof PhrictionContent) {
        continue;
      }
      if ($object->getConfigOption(LiskDAO::CONFIG_NO_TABLE)) {
        continue;
      }
      $application = $type->getPHIDTypeApplicationClass();
      if ($application && !PhabricatorApplication::isClassInstalled($application)) {
        continue;
      }
      $objects[get_class($object)] = $object;
    }
    self::$objects = $objects;
    return $objects;
  }

  public function supportsObject($object) {
    return isset($this->getObjects()[get_class($object)]);
  }

  public function newDocument(PhabricatorUser $viewer, $object,
    $extract_content = false) {
    if (!$extract_content) {
      $stored = id(new PhabricatorSearchApplicationDocument())
        ->loadOneWhere('objectPHID = %s', $object->getPHID());
      if ($stored) {
        return id(new PhabricatorSearchAbstractDocument())
          ->setPHID($object->getPHID())
          ->setDocumentType($stored->getObjectType())
          ->setDocumentTitle($stored->getTitle())
          ->addField(PhabricatorSearchDocumentFieldType::FIELD_BODY,
            $stored->getBody());
      }
    }
    $handle = $viewer->loadHandles(array($object->getPHID()))
      [$object->getPHID()];
    $title = $handle->getFullName();
    $body = array();
    if ($object instanceof PhabricatorFulltextInterface) {
      $document = $object->newFulltextEngine()->setObject($object)
        ->newSearchDocument($viewer);
      $title = $document->getDocumentTitle();
      foreach ($document->getFieldData() as $field) {
        if ($field[0] == PhabricatorSearchDocumentFieldType::FIELD_BODY) {
          $body[] = $field[1];
        }
      }
    } else {
      // Only explicitly named public-content columns are searchable. Never
      // serialize arbitrary object data (credentials contain secret material).
      $columns = $object->getSchemaColumns();
      foreach (array('name', 'title', 'description', 'summary', 'content', 'body',
        'message', 'text', 'slug', 'path', 'username', 'realName', 'altText',
        'preamble', 'filename')
        as $column) {
        if (!isset($columns[$column])) {
          continue;
        }
        $value = $object->{'get'.ucfirst($column)}();
        if (is_string($value)) {
          $body[] = $value;
        }
      }
    }
    if ($object instanceof PhabricatorFile) {
      if ($extract_content) {
        $body[] = $this->newFileText($object);
      } else {
        $stored = id(new PhabricatorSearchApplicationDocument())
          ->loadOneWhere('objectPHID = %s', $object->getPHID());
        if ($stored) {
          $body[] = $stored->getBody();
        }
      }
    }
    if ($object instanceof PhabricatorCustomFieldInterface) {
      $fields = PhabricatorCustomField::getObjectFields(
        $object, PhabricatorCustomField::ROLE_DEFAULT);
      $fields->setViewer($viewer)->readFieldsFromStorage($object);
      $field_document = id(new PhabricatorSearchAbstractDocument());
      $fields->updateAbstractDocument($field_document);
      foreach ($field_document->getFieldData() as $field) {
        $body[] = $field[1];
      }
    }
    if ($object instanceof LegalpadDocument) {
      $legalpad = id(new LegalpadDocumentQuery())->setViewer($viewer)
        ->withPHIDs(array($object->getPHID()))->needDocumentBodies(true)
        ->executeOne();
      if ($legalpad) {
        $body[] = $legalpad->getDocumentBody()->getText();
      }
    }
    if ($object instanceof HarbormasterBuildLog) {
      $body[] = $object->getLogText();
    }
    if ($object instanceof DifferentialChangeset) {
      $changeset = id(new DifferentialChangesetQuery())->setViewer($viewer)
        ->withPHIDs(array($object->getPHID()))->needHunks(true)->executeOne();
      if ($changeset) {
        foreach ($changeset->getHunks() as $hunk) {
          $body[] = $hunk->getChanges();
        }
      }
    }
    if ($object instanceof PhabricatorDashboardPanel) {
      $text = $object->getProperty('text');
      if (is_string($text)) {
        $body[] = $text;
      }
    }
    if ($object instanceof PhluxVariable) {
      $body[] = $object->getVariableKey();
      $value = $object->getVariableValue();
      $body[] = is_string($value) ? $value : phutil_json_encode($value);
    }
    return id(new PhabricatorSearchAbstractDocument())
      ->setPHID($object->getPHID())
      ->setDocumentType(phid_get_type($object->getPHID()))
      ->setDocumentTitle((string)$title)
      ->addField(PhabricatorSearchDocumentFieldType::FIELD_BODY,
        implode("\n", $body));
  }

  private function newFileText(PhabricatorFile $file) {
    if ($file->getByteSize() > 16 * 1024 * 1024) {
      return '';
    }
    $mime = $file->getMimeType();
    if (strncmp($mime, 'text/', 5) === 0 || in_array($mime, array(
      'application/json', 'application/xml', 'application/javascript'))) {
      $data = $file->loadFileData();
      return phutil_is_utf8($data) ? $data : '';
    }
    if ($mime == 'application/pdf') {
      $data = $file->loadFileData();
      $temporary = new TempFile();
      Filesystem::writeFile($temporary, $data);
      list($error, $text) = id(new ExecFuture('pdftotext -enc UTF-8 %s -',
        $temporary))->setTimeout(30)->resolve();
      return $error ? '' : $text;
    }
    if (class_exists('ZipArchive') && in_array($mime, array(
      'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      'application/vnd.openxmlformats-officedocument.presentationml.presentation',
      'application/vnd.oasis.opendocument.text',
      'application/vnd.oasis.opendocument.spreadsheet',
      'application/vnd.oasis.opendocument.presentation',
    ))) {
      $temporary = new TempFile();
      Filesystem::writeFile($temporary, $file->loadFileData());
      $archive = new ZipArchive();
      if ($archive->open((string)$temporary) !== true) {
        return '';
      }
      $parts = array();
      $length = 0;
      for ($ii = 0; $ii < $archive->numFiles; $ii++) {
        $entry = $archive->statIndex($ii);
        if (!preg_match('@^(word/document|xl/sharedStrings|xl/worksheets/sheet\d+|'.
            'ppt/slides/slide\d+|content)\.xml$@', $entry['name'])) {
          continue;
        }
        $length += $entry['size'];
        if ($length > 16 * 1024 * 1024) {
          break;
        }
        $xml = $archive->getFromIndex($ii);
        $parts[] = html_entity_decode(strip_tags(str_replace(
          array('</w:p>', '</a:p>', '</text:p>'), "\n", $xml)),
          ENT_QUOTES | ENT_XML1, 'UTF-8');
      }
      $archive->close();
      return implode("\n", $parts);
    }
    return '';
  }

  public function indexObject($object) {
    $viewer = PhabricatorUser::getOmnipotentUser();
    $document = $this->newDocument($viewer, $object, true);
    $body = array();
    foreach ($document->getFieldData() as $field) {
      if ($field[0] == PhabricatorSearchDocumentFieldType::FIELD_BODY) {
        $body[] = $field[1];
      }
    }
    $comments = array();
    if ($object instanceof PhabricatorApplicationTransactionInterface) {
      $query = PhabricatorApplicationTransactionQuery::newQueryForObject($object);
      if ($query) {
        $query->setViewer($viewer)
          ->withObjectPHIDs(array($object->getPHID()))
          ->withComments(true)->needComments(true)->needHandles(false);
        foreach (new PhabricatorQueryIterator($query) as $transaction) {
          if ($transaction->hasComment()) {
            $comment = $transaction->getComment();
            if (!$comment->getIsDeleted() && !$comment->getIsRemoved()) {
              $comments[] = $comment->getContent();
            }
          }
        }
      }
    }
    $title = Normalizer::normalize($document->getDocumentTitle(), Normalizer::FORM_C);
    $body = Normalizer::normalize(implode("\n", $body), Normalizer::FORM_C);
    $comments = Normalizer::normalize(implode("\n", $comments), Normalizer::FORM_C);
    $table = new PhabricatorSearchApplicationDocument();
    $corpus = $title."\n".$body."\n".$comments;
    $ferret = new ManiphestTaskFerretEngine();
    $normal = $ferret->newStemmer()->stemCorpus($corpus);
    $ngrams = id(new PhabricatorSearchNgramEngine())
      ->getSubstringNgramsFromString($corpus."\n".$normal);
    $ngram_table = new PhabricatorSearchApplicationNgram();
    $conn = $ngram_table->establishConnection('w');
    $ngram_table->openTransaction();
    try {
      queryfx($table->establishConnection('w'),
        'INSERT INTO %T (objectPHID, objectType, title, body, comments, corpus,
          dateCreated, dateModified) VALUES (%s, %s, %s, %s, %s, %s, %d, %d)
          ON DUPLICATE KEY UPDATE title=VALUES(title), body=VALUES(body),
          comments=VALUES(comments), corpus=VALUES(corpus),
          dateModified=VALUES(dateModified)',
        $table->getTableName(), $object->getPHID(), phid_get_type($object->getPHID()),
        $title, $body, $comments, $title."\n".$body."\n".$comments,
        PhabricatorTime::getNow(), PhabricatorTime::getNow());
      queryfx($conn, 'DELETE FROM %T WHERE objectPHID=%s',
        $ngram_table->getTableName(), $object->getPHID());
      $values = array();
      foreach ($ngrams as $ngram) {
        $values[] = qsprintf($conn, '(%s, %s)', $object->getPHID(),
          str_pad($ngram, 12, "\0"));
      }
      foreach (PhabricatorLiskDAO::chunkSQL($values) as $chunk) {
        queryfx($conn, 'INSERT INTO %T (objectPHID, ngram) VALUES %LQ',
          $ngram_table->getTableName(), $chunk);
      }
      $ngram_table->saveTransaction();
    } catch (Throwable $ex) {
      $ngram_table->killTransaction();
      throw $ex;
    }
  }

}
