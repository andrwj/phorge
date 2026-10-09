<?php

final class PhabricatorConfigOptionSearchTestCase extends PhabricatorTestCase {

  private function newOptions() {
    $group = new PhabricatorCustomUIConfigOptions();
    $option = id(new PhabricatorConfigOption())
      ->setKey('example.CardputerZero')
      ->setType('string')
      ->setSummary('도어락 기술자')
      ->setDescription('검색 기능 및 dashboard configuration')
      ->setDefault('SecretDefaultMustNotMatch')
      ->addExample('SecretExampleMustNotMatch', 'Private example')
      ->setGroup($group);
    return array($option->getKey() => $option);
  }

  private function newSearch() {
    return id(new PhabricatorConfigOptionSearch())
      ->setViewer(id(new PhabricatorUser())->setIsAdmin(true));
  }

  public function testMetadataSubstringSearch() {
    $env = PhabricatorEnv::beginScopedEnv();
    $env->overrideEnvConfig('ui.custom.search.config-settings', true);
    $options = $this->newOptions();
    foreach (array('Cardputer', 'CARDPUTER', '도어',
      Normalizer::normalize('도어', Normalizer::FORM_D), '검색',
      Normalizer::normalize('검색', Normalizer::FORM_D),
      'dashboard', 'Custom User', 'Cardputer 도어',
      'title:도어', 'body:검색', 'Cardputer -unknown') as $text) {
      $saved = id(new PhabricatorSavedQuery())->setParameter('query', $text);
      $this->assertEqual(array_keys($options),
        array_keys($this->newSearch()->execute($saved, $options)), $text);
    }
    foreach (array('SecretDefault', 'SecretExample', 'Private example',
      'unknown', 'Cardputer -도어', 'comment:도어', '=Cardputer',
      'body:Cardputer') as $text) {
      $saved = id(new PhabricatorSavedQuery())->setParameter('query', $text);
      $this->assertEqual(array(), $this->newSearch()->execute($saved, $options),
        $text);
    }
  }

  public function testPermissionsAndFilters() {
    $env = PhabricatorEnv::beginScopedEnv();
    $env->overrideEnvConfig('ui.custom.search.config-settings', true);
    $options = $this->newOptions();
    $saved = id(new PhabricatorSavedQuery())->setParameter('query', 'Cardputer');
    foreach (array(new PhabricatorUser(),
      id(new PhabricatorUser())->setPHID('PHID-USER-regular')) as $viewer) {
      $this->assertEqual(array(), id(new PhabricatorConfigOptionSearch())
        ->setViewer($viewer)->execute($saved, $options));
    }
    foreach (array(
      'searchFields' => array('comment'),
      'types' => array('TASK'),
      'statuses' => array('closed'),
      'authorPHIDs' => array('PHID-USER-example'),
      'projectPHIDs' => array('PHID-PROJ-example'),
    ) as $key => $value) {
      $filtered = clone $saved;
      $filtered->setParameter($key, $value);
      $this->assertEqual(array(), $this->newSearch()->execute($filtered, $options),
        $key);
    }
    $saved->setParameter('types', array('CONF'));
    $this->assertEqual(array_keys($options),
      array_keys($this->newSearch()->execute($saved, $options)));
    $env->overrideEnvConfig('ui.custom.search.config-settings', false);
    $this->assertEqual(array(), $this->newSearch()->execute($saved, $options));
    $env->overrideEnvConfig('ui.custom.search.config-settings', true);
    head($options)->setHidden(true);
    $this->assertEqual(array(), $this->newSearch()->execute($saved, $options));
  }

  public function testGlobalResultsRendering() {
    $env = PhabricatorEnv::beginScopedEnv();
    $env->overrideEnvConfig('ui.custom.search.config-settings', true);
    $viewer = id(new PhabricatorUser())->setIsAdmin(true);
    $engine = id(new PhabricatorSearchApplicationSearchEngine())
      ->setViewer($viewer);
    $property = new ReflectionProperty($engine, 'resultSet');
    $property->setValue($engine, id(new PhabricatorFulltextResultSet())
      ->setFulltextTokens(array()));
    $method = new ReflectionMethod($engine, 'renderResultList');
    $saved = id(new PhabricatorSavedQuery())
      ->setParameter('query', 'ui.custom.home.default');
    $result = $method->invoke($engine, array(), $saved, array());
    $html = hsprintf('%s', $result->getContent())->getHTMLContent();
    $this->assertTrue(strpos($html, '/config/edit/ui.custom.home.default-dashboard/')
      !== false);
    $this->assertEqual(null, $result->getObjectList());
    $viewer->setIsAdmin(false);
    $result = $method->invoke($engine, array(), $saved, array());
    $html = hsprintf('%s', $result->getContent())->getHTMLContent();
    $this->assertFalse(strpos($html, '/config/edit/') !== false);
    $this->assertTrue($result->getObjectList() instanceof PHUIObjectItemListView);
  }

}
