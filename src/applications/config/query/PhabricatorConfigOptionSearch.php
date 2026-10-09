<?php

final class PhabricatorConfigOptionSearch extends Phobject {

  private $viewer;

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function execute(PhabricatorSavedQuery $query, array $options) {
    // Match metadata only. Never read current, default, or example values.
    if (!$this->viewer || !$this->viewer->getIsAdmin() ||
        !PhabricatorEnv::getEnvConfig('ui.custom.search.config-settings')) {
      return array();
    }
    $types = $query->getParameter('types', array());
    if ($types && !in_array(PhabricatorConfigConfigPHIDType::TYPECONST, $types)) {
      return array();
    }
    foreach (array('authorPHIDs', 'ownerPHIDs', 'subscriberPHIDs',
      'projectPHIDs', 'withUnowned', 'withAnyOwner') as $parameter) {
      if ($query->getParameter($parameter)) {
        return array();
      }
    }
    $statuses = $query->getParameter('statuses', array());
    if ($statuses && !in_array('open', $statuses)) {
      return array();
    }
    $fields = array_intersect(array('title', 'body'),
      $query->getParameter('searchFields', array('title', 'body', 'comment')));
    if (!$fields) {
      return array();
    }
    $text = $this->normalize($query->getParameter('query', ''));
    $tokens = id(new PhutilSearchQueryCompiler())
      ->setEnableFunctions(true)
      ->newTokens($text);
    if (!$tokens) {
      return array();
    }
    $matches = array();
    foreach ($options as $key => $option) {
      $group = $option->getGroup();
      $application = PhabricatorApplication::getByClass(
        $group->getApplicationClassName());
      if ($option->getHidden() || !$application->isInstalled()) {
        continue;
      }
      $corpus = array(
        'title' => $this->normalize(
          $option->getKey()."\n".$option->getSummary()),
        'body' => $this->normalize(
          $option->getDescription()."\n".$group->getName()."\n".
          $group->getDescription()."\n".$application->getName()),
      );
      if ($this->matchesTokens($tokens, $fields, $corpus)) {
        $matches[$key] = $option;
      }
    }
    ksort($matches);
    return $matches;
  }

  private function normalize($text) {
    return phutil_utf8_strtolower(
      Normalizer::normalize((string)$text, Normalizer::FORM_C));
  }

  private function matchesTokens(array $tokens, array $fields, array $corpus) {
    foreach ($tokens as $token) {
      $function = $token->getFunction();
      if ($function === null || $function === 'all' || $function === 'core') {
        $selected = $fields;
      } else if (in_array($function, array('title', 'body')) &&
                 in_array($function, $fields)) {
        $selected = array($function);
      } else if ($function === 'comment') {
        $selected = array();
      } else {
        return false;
      }
      $parts = array();
      foreach ($selected as $field) {
        $parts[] = $corpus[$field];
      }
      $text = implode("\n", $parts);
      $operator = $token->getOperator();
      switch ($operator) {
        case PhutilSearchQueryCompiler::OPERATOR_PRESENT:
          $match = strlen($text) > 0;
          break;
        case PhutilSearchQueryCompiler::OPERATOR_ABSENT:
          $match = strlen($text) === 0;
          break;
        case PhutilSearchQueryCompiler::OPERATOR_EXACT:
          $engine = new ManiphestTaskFerretEngine();
          $match = strpos($this->normalize($engine->newTermsCorpus($text)),
            $this->normalize($engine->newTermsCorpus($token->getValue())))
              !== false;
          break;
        default:
          $match = strpos($text, $this->normalize($token->getValue())) !== false;
          if ($operator === PhutilSearchQueryCompiler::OPERATOR_NOT) {
            $match = !$match;
          }
          break;
      }
      if (!$match) {
        return false;
      }
    }
    return true;
  }

}
