<?php

final class PhabricatorSearchApplicationContentQuery extends Phobject {

  public function execute(PhabricatorSavedQuery $query) {
    $compiler = id(new PhutilSearchQueryCompiler())->setEnableFunctions(true);
    $tokens = $compiler->newTokens(Normalizer::normalize(
      (string)$query->getParameter('query', ''), Normalizer::FORM_C));
    $fields = $query->getParameter('searchFields',
      array('title', 'body', 'comment'));
    $fields = array_values(array_intersect(
      array('title', 'body', 'comment'), $fields));
    if (!$fields) {
      return array();
    }
    $table = new PhabricatorSearchApplicationDocument();
    $conn = $table->establishConnection('r');
    $where = array();
    $relevance = array();
    foreach ($tokens as $token) {
      $function = $token->getFunction();
      if (in_array($function, array('title', 'body', 'comment')) &&
          !in_array($function, $fields)) {
        return array();
      }
      if ($function == 'core' && !array_intersect($fields, array('title', 'body'))) {
        return array();
      }
      $column = 'corpus';
      switch ($token->getFunction()) {
        case 'title': $column = 'title'; break;
        case 'body': $column = 'body'; break;
        case 'comment': $column = 'comments'; break;
        case 'core':
          $column = null;
          break;
        case null:
        case 'all': break;
        default: return array();
      }
      $core_fields = array_values(array_intersect(
        $fields, array('title', 'body')));
      $core_columns = array();
      foreach ($core_fields as $field) {
        $core_columns[] = qsprintf($conn, '%C', $field);
      }
      $expression = $column ? qsprintf($conn, '%C', $column)
        : qsprintf($conn, 'CONCAT_WS(%s, %LQ)', "\n", $core_columns);
      if ($token->getFunction() === null || $token->getFunction() == 'all') {
        $selected = array();
        foreach ($fields as $field) {
          $selected[] = qsprintf($conn, '%C',
            $field == 'comment' ? 'comments' : $field);
        }
        $expression = qsprintf($conn, 'CONCAT_WS(%s, %LQ)', "\n", $selected);
      }
      if (in_array($token->getOperator(), array(
          PhutilSearchQueryCompiler::OPERATOR_AND,
          PhutilSearchQueryCompiler::OPERATOR_EXACT,
          PhutilSearchQueryCompiler::OPERATOR_SUBSTRING))) {
        $relevance[] = qsprintf($conn,
          '(IF(LOWER(title) LIKE %~, 100, 0) + IF(LOWER(body) LIKE %~, 10, 0))',
          phutil_utf8_strtolower($token->getValue()),
          phutil_utf8_strtolower($token->getValue()));
      }
      switch ($token->getOperator()) {
        case PhutilSearchQueryCompiler::OPERATOR_PRESENT:
          $where[] = qsprintf($conn, 'LENGTH(%Q) > 0', $expression);
          break;
        case PhutilSearchQueryCompiler::OPERATOR_ABSENT:
          $where[] = qsprintf($conn, 'LENGTH(%Q) = 0', $expression);
          break;
        case PhutilSearchQueryCompiler::OPERATOR_NOT:
          $where[] = qsprintf($conn, 'LOWER(%Q) NOT LIKE %~', $expression,
            phutil_utf8_strtolower($token->getValue()));
          break;
        case PhutilSearchQueryCompiler::OPERATOR_AND:
          // Bare terms use literal substring matching in every language.
          $where[] = qsprintf($conn, 'LOWER(%Q) LIKE %~', $expression,
            phutil_utf8_strtolower($token->getValue()));
          break;
        default:
          $where[] = qsprintf($conn, 'LOWER(%Q) LIKE %~', $expression,
            phutil_utf8_strtolower($token->getValue()));
          break;
      }
    }
    if (!$where) {
      $where[] = qsprintf($conn, '1 = 1');
    }
    $types = $query->getParameter('types', array());
    if ($types) {
      $where[] = qsprintf($conn, 'objectType IN (%Ls)', $types);
    }
    $ngram_table = new PhabricatorSearchApplicationNgram();
    $ngram_engine = new PhabricatorSearchNgramEngine();
    foreach ($tokens as $token) {
      if (!in_array($token->getOperator(), array(
          PhutilSearchQueryCompiler::OPERATOR_AND,
          PhutilSearchQueryCompiler::OPERATOR_EXACT,
          PhutilSearchQueryCompiler::OPERATOR_SUBSTRING))) {
        continue;
      }
      $value = $token->getValue();
      $ngrams = $ngram_engine->getSubstringNgramsFromString($value);
      if ($ngrams) {
        $where[] = qsprintf($conn,
          '(objectPHID IN (SELECT objectPHID FROM %T WHERE ngram=%s) OR
            objectPHID NOT IN (SELECT objectPHID FROM %T))',
          $ngram_table->getTableName(), str_pad(head($ngrams), 12, "\0"),
          $ngram_table->getTableName());
      }
    }
    $order = $relevance
      ? qsprintf($conn, '%Q DESC, id DESC',
        qsprintf($conn, '%LJ', array_map(function($item) use ($conn) {
          return qsprintf($conn, '+ %Q', $item);
        }, $relevance)))
      : qsprintf($conn, 'id DESC');
    $rows = queryfx_all($conn,
      'SELECT objectPHID, title, body, comments, corpus FROM %T WHERE %LA
        ORDER BY %Q',
      $table->getTableName(), $where, $order);
    $engine = new ManiphestTaskFerretEngine();
    foreach ($rows as $key => $row) {
      foreach ($tokens as $token) {
        $function = $token->getFunction();
        $parts = array();
        foreach ($fields as $field) {
          $parts[] = $row[$field == 'comment' ? 'comments' : $field];
        }
        $text = implode("\n", $parts);
        if ($function == 'title') {
          $text = $row['title'];
        } else if ($function == 'body') {
          $text = $row['body'];
        } else if ($function == 'comment') {
          $text = $row['comments'];
        } else if ($function == 'core') {
          $core_parts = array();
          foreach (array_intersect($fields, array('title', 'body')) as $field) {
            $core_parts[] = $row[$field];
          }
          $text = implode("\n", $core_parts);
        }
        $operator = $token->getOperator();
        if (in_array($operator, array(
          PhutilSearchQueryCompiler::OPERATOR_PRESENT,
          PhutilSearchQueryCompiler::OPERATOR_ABSENT,
          PhutilSearchQueryCompiler::OPERATOR_SUBSTRING,
        ))) {
          continue;
        }
        $value = phutil_utf8_strtolower($token->getValue());
        if ($operator == PhutilSearchQueryCompiler::OPERATOR_EXACT) {
          $corpus = phutil_utf8_strtolower($engine->newTermsCorpus($text));
          $term = phutil_utf8_strtolower($engine->newTermsCorpus($value));
          $found = strpos($corpus, $term) !== false;
        } else {
          $found = strpos(phutil_utf8_strtolower($text), $value) !== false;
        }
        if (($operator == PhutilSearchQueryCompiler::OPERATOR_NOT && $found) ||
            ($operator != PhutilSearchQueryCompiler::OPERATOR_NOT && !$found)) {
          unset($rows[$key]);
          break;
        }
      }
    }
    $phids = ipull($rows, 'objectPHID');
    if (!$phids) {
      return array();
    }
    $objects = id(new PhabricatorObjectQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withPHIDs($phids)->execute();
    $result = array();
    $document_engine = new PhabricatorSearchApplicationDocumentEngine();
    foreach ($phids as $phid) {
      $object = idx($objects, $phid);
      if (!$object || !$document_engine->supportsObject($object)) {
        continue;
      }
      $columns = $object instanceof PhabricatorLiskDAO
        ? $object->getSchemaColumns() : array();
      $authors = $query->getParameter('authorPHIDs', array());
      if ($authors && (!isset($columns['authorPHID']) ||
          !in_array($object->getAuthorPHID(), $authors))) {
        continue;
      }
      $owners = $query->getParameter('ownerPHIDs', array());
      $unowned = $query->getParameter('withUnowned');
      $any_owner = $query->getParameter('withAnyOwner');
      if ($owners || $unowned || $any_owner) {
        if (!isset($columns['ownerPHID'])) {
          continue;
        }
        $owner = $object->getOwnerPHID();
        if (!in_array($owner, $owners) &&
            !($unowned && !$owner) && !($any_owner && $owner)) {
          continue;
        }
      }
      $statuses = $query->getParameter('statuses', array());
      if ($statuses) {
        $closed = false;
        if (method_exists($object, 'isClosed')) {
          $closed = $object->isClosed();
        } else if (method_exists($object, 'isArchived')) {
          $closed = $object->isArchived();
        } else if ($object instanceof PhabricatorCountdown) {
          $closed = $object->getEpoch() <= PhabricatorTime::getNow();
        }
        if (!in_array($closed ? 'closed' : 'open', $statuses)) {
          continue;
        }
      }
      foreach (array(
        'projectPHIDs' => 'PhabricatorProjectObjectHasProjectEdgeType',
        'subscriberPHIDs' => 'PhabricatorObjectHasSubscriberEdgeType',
      ) as $parameter => $edge_class) {
        $values = $query->getParameter($parameter, array());
        if ($values) {
          $edges = PhabricatorEdgeQuery::loadDestinationPHIDs(
            $phid, constant($edge_class.'::EDGECONST'));
          if (!array_intersect($values, $edges)) {
            continue 2;
          }
        }
      }
      $result[] = $phid;
    }
    return $result;
  }

}
