<?php

final class PhabricatorSearchApplicationContentQueryTestCase
  extends PhutilTestCase {

  public function testSearchFieldSelection() {
    $viewer = PhabricatorUser::getOmnipotentUser();
    $engine = id(new PhabricatorSearchApplicationSearchEngine())
      ->setViewer($viewer);
    $request = id(new AphrontRequest('localhost', '/search/query/advanced/'))
      ->setUser($viewer);
    foreach (array(
      array(array(), array('title', 'body', 'comment')),
      array(array('searchFieldsPresent' => '1'), array()),
      array(array('searchFieldsPresent' => '1',
        'searchFields' => array('title', 'comment', 'invalid')),
        array('title', 'comment')),
    ) as $case) {
      list($data, $expected) = $case;
      $request->setRequestData($data);
      $saved = $engine->buildSavedQueryFromRequest($request);
      $this->assertEqual($expected, $saved->getParameter('searchFields'));
    }
  }

  public function testMigratedKoreanSearch() {
    // Opt in explicitly: ordinary unit runs must not read deployment data.
    if (!getenv('PHORGE_MIGRATION_READONLY_TEST')) {
      $this->assertSkipped(pht('Run with PHORGE_MIGRATION_READONLY_TEST=1.'));
    }
    PhabricatorEnv::initializeScriptEnvironment(false, true);
    PhabricatorEnv::setReadOnly(true, PhabricatorEnv::READONLY_CONFIG);
    $viewer = PhabricatorUser::getOmnipotentUser();
    $table = new PhabricatorSearchApplicationDocument();
    $fixture = queryfx_one($table->establishConnection('r'),
      'SELECT objectPHID, title, body, comments FROM %T WHERE objectType=%s AND title LIKE %~ LIMIT 1',
      $table->getTableName(), 'TASK', '새총');
    if (!$fixture) {
      $this->assertSkipped(pht('Requires an indexed Korean task.'));
    }
    $task = id(new ManiphestTaskQuery())->setViewer($viewer)
      ->withPHIDs(array($fixture['objectPHID']))->executeOne();
    if (!$task) {
      $this->assertSkipped(pht('Requires the indexed task to exist.'));
    }
    $extension = new PhabricatorApplicationDocumentIndexEngineExtension();
    $this->assertTrue($extension->shouldIndexObject($task));
    $document = id(new PhabricatorSearchApplicationDocumentEngine())
      ->newDocument($viewer, $task, true);
    $this->assertEqual($task->getTitle(), $document->getDocumentTitle());
    $body = array();
    foreach ($document->getFieldData() as $field) {
      if ($field[0] == PhabricatorSearchDocumentFieldType::FIELD_BODY) {
        $body[] = $field[1];
      }
    }
    $this->assertTrue(strpos(implode("\n", $body),
      $task->getDescription()) !== false);
    foreach (array('새총', Normalizer::normalize('새총', Normalizer::FORM_D))
      as $text) {
      $request = id(new AphrontRequest('localhost', '/search/query/advanced/'))
        ->setUser($viewer)->setRequestData(array('query' => $text,
          'searchFieldsPresent' => '1', 'searchFields' => array('title')));
      $engine = id(new PhabricatorSearchApplicationSearchEngine())
        ->setViewer($viewer);
      $saved = $engine->buildSavedQueryFromRequest($request);
      $rows = $engine->executeQuery($engine->buildQueryFromSavedQuery($saved),
        $engine->newPagerForSavedQuery($saved));
      $this->assertTrue(isset($rows[$task->getPHID()]));
      $html = (string)hsprintf('%s', $engine->renderResults($rows, $saved)
        ->getObjectList());
      $this->assertTrue(strpos($html, '/T'.$task->getID()) !== false);
      $saved->setParameter('searchFields', array());
      $this->assertEqual(array(), id(new PhabricatorSearchApplicationContentQuery())
        ->execute($saved));
    }
    foreach (array('title', 'body', 'comment') as $field) {
      $text = $fixture[$field == 'comment' ? 'comments' : $field];
      $expected = strpos($text, '새총') !== false;
      $saved = id(new PhabricatorSavedQuery())->setParameter('query', '새총')
        ->setParameter('searchFields', array($field));
      $found = id(new PhabricatorSearchApplicationContentQuery())->execute($saved);
      $this->assertEqual($expected, in_array($task->getPHID(), $found), $field);
      $saved->setParameter('query', $field.':새총');
      $found = id(new PhabricatorSearchApplicationContentQuery())->execute($saved);
      $this->assertEqual($expected, in_array($task->getPHID(), $found), $field);
      $saved->setParameter('searchFields',
        array_values(array_diff(array('title', 'body', 'comment'), array($field))));
      $this->assertEqual(array(),
        id(new PhabricatorSearchApplicationContentQuery())->execute($saved));
    }
    $query = id(new PhabricatorSavedQuery())->setParameter('query', '')
      ->setParameter('types', array('TASK'));
    $engine = new PhabricatorFerretFulltextStorageEngine();
    $all = $engine->executeSearch(id(clone $query)->setParameter('limit', 1000));
    $pages = array();
    for ($offset = 0; $offset < count($all); $offset += 20) {
      $pages = array_merge($pages, $engine->executeSearch(id(clone $query)
        ->setParameter('limit', 20)->setParameter('offset', $offset)));
    }
    $this->assertEqual($all, $pages);
    $this->assertEqual(count($pages), count(array_unique($pages)));
    $anonymous = new PhabricatorUser();
    $anonymous_engine = id(new PhabricatorSearchApplicationSearchEngine())
      ->setViewer($anonymous);
    $anonymous_rows = $anonymous_engine->buildQueryFromSavedQuery($query)
      ->setViewer($anonymous)->setLimit(1000)->execute();
    $visible = $anonymous_rows
      ? id(new ManiphestTaskQuery())->setViewer($anonymous)
        ->withPHIDs(array_keys($anonymous_rows))->execute()
      : array();
    $this->assertEqual(count($anonymous_rows), count($visible));
  }

  public function testMigratedEnglishSubstringSearch() {
    if (!getenv('PHORGE_MIGRATION_READONLY_TEST')) {
      $this->assertSkipped(pht('Run with PHORGE_MIGRATION_READONLY_TEST=1.'));
    }
    PhabricatorEnv::initializeScriptEnvironment(false, true);
    PhabricatorEnv::setReadOnly(true, PhabricatorEnv::READONLY_CONFIG);
    $table = new PhabricatorSearchApplicationDocument();
    $conn = $table->establishConnection('r');
    $fixture = queryfx_one($conn,
      'SELECT objectPHID FROM %T WHERE objectType=%s AND title=%s LIMIT 1',
      $table->getTableName(), 'TASK', 'CardputerZero');
    if (!$fixture) {
      $this->assertSkipped(pht('Requires the CardputerZero task.'));
    }
    $engine = new PhabricatorSearchApplicationContentQuery();
    foreach (array('Cardputer', 'ardputer', 'puterZero', 'CARDPUTER',
      'title:Cardputer', 'Cardputer -missingword', '~Cardputer') as $text) {
      $saved = id(new PhabricatorSavedQuery())->setParameter('query', $text)
        ->setParameter('types', array('TASK'))
        ->setParameter('searchFields', array('title'));
      $this->assertTrue(in_array($fixture['objectPHID'],
        $engine->execute($saved)), $text);
    }
    $saved->setParameter('query', 'Cardputer -ardputer');
    $this->assertFalse(in_array($fixture['objectPHID'], $engine->execute($saved)));
    $saved->setParameter('query', 'Cardputer');
    $saved->setParameter('searchFields', array('title', 'body', 'comment'));
    $search = id(new PhabricatorSearchApplicationSearchEngine())
      ->setViewer(PhabricatorUser::getOmnipotentUser());
    $rows = $search->executeQuery($search->buildQueryFromSavedQuery($saved),
      $search->newPagerForSavedQuery($saved));
    $this->assertTrue(isset($rows[$fixture['objectPHID']]));

    $comment = queryfx_one($conn,
      'SELECT objectPHID, comments FROM %T WHERE objectType=%s
        AND comments REGEXP %s LIMIT 1',
      $table->getTableName(), 'TASK', '[A-Za-z]{8,}');
    if (!$comment) {
      $this->assertSkipped(pht('Requires an indexed English task comment.'));
    }
    preg_match_all('/[A-Za-z]{8,}/', $comment['comments'], $words);
    usort($words[0], function($a, $b) {
      return strlen($b) - strlen($a);
    });
    $word = head($words[0]);
    foreach (array(substr($word, 0, -2), substr($word, 2, -2)) as $part) {
      foreach (array($part, 'comment:'.$part) as $text) {
        $saved = id(new PhabricatorSavedQuery())->setParameter('query', $text)
          ->setParameter('types', array('TASK'))
          ->setParameter('searchFields', array('comment'));
        $this->assertTrue(in_array($comment['objectPHID'],
          $engine->execute($saved)), 'English comment substring');
      }
    }
  }

}
