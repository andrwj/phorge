<?php

final class PhabricatorFerretFulltextStorageEngine
  extends PhabricatorFulltextStorageEngine {

  private $fulltextTokens = array();

  /**
   * @return string Engine identifier string: "mysql"
   */
  public function getEngineIdentifier() {
    return 'mysql';
  }

  public function getHostType() {
    return new PhabricatorMySQLSearchHost($this);
  }

  public function reindexAbstractDocument(
    PhabricatorSearchAbstractDocument $doc) {

    // NOTE: The Ferret engine indexes are rebuilt by an extension rather than
    // by the main fulltext engine, and are always built regardless of
    // configuration.

    return;
  }

  public function executeSearch(PhabricatorSavedQuery $query) {
    $this->fulltextTokens = array();
    $query = clone $query;
    $query->setParameter('query', Normalizer::normalize(
      (string)$query->getParameter('query', ''), Normalizer::FORM_C));
    $search_fields = $query->getParameter('searchFields',
      array('title', 'body', 'comment'));
    $all_objects = id(new PhutilClassMapQuery())
      ->setAncestorClass(PhabricatorFerretInterface::class)
      ->execute();

    $type_map = array();
    foreach ($all_objects as $object) {
      $phid_type = phid_get_type($object->generatePHID());

      $type_map[$phid_type] = array(
        'object' => $object,
        'engine' => $object->newFerretEngine(),
      );
    }

    $types = $query->getParameter('types');
    if ($types) {
      $type_map = array_select_keys($type_map, $types);
    }

    $offset = (int)$query->getParameter('offset', 0);
    $limit  = (int)$query->getParameter('limit', 25);

    // NOTE: For now, it's okay to query with the omnipotent viewer here
    // because we're just returning PHIDs which we'll filter later.
    $viewer = PhabricatorUser::getOmnipotentUser();

    $type_results = array();
    $metadata = array();
    foreach ($type_map as $type => $spec) {
      if (count($search_fields) != 3) {
        continue;
      }
      $engine = $spec['engine'];
      $object = $spec['object'];

      $search_engine = $engine->newSearchEngine()
        ->setViewer($viewer);

      // Ignore result objects from SearchEngines belonging to disabled apps
      $app_class = $search_engine->getApplicationClassName();
      $app = PhabricatorApplication::isClassInstalled($app_class);
      if (!$app) {
        continue;
      }
      $local_query = new PhabricatorSavedQuery();
      $local_query->setParameter('query', $query->getParameter('query'));

      $project_phids = $query->getParameter('projectPHIDs');
      if ($project_phids) {
        $local_query->setParameter('projectPHIDs', $project_phids);
      }

      $subscriber_phids = $query->getParameter('subscriberPHIDs');
      if ($subscriber_phids) {
        $local_query->setParameter('subscriberPHIDs', $subscriber_phids);
      }

      $engine_query = $search_engine->buildQueryFromSavedQuery($local_query)
        ->setViewer($viewer);

      $engine_query
        ->withFerretQuery($engine, $query)
        ->setOrder('relevance')
        ->setLimit($offset + $limit);

      $results = $engine_query->execute();
      $results = mpull($results, null, 'getPHID');
      $type_results[$type] = $results;

      $metadata += $engine_query->getFerretMetadata();

      if (!$this->fulltextTokens) {
        $this->fulltextTokens = $engine_query->getFerretTokens();
      }
    }

    $list = array();
    foreach ($type_results as $type => $results) {
      $list += $results;
    }

    // Currently, the list is grouped by object type. For example, all the
    // tasks might be first, then all the revisions, and so on. In each group,
    // the results are ordered properly.

    // Reorder the results so that the highest-ranking results come first,
    // no matter which object types they belong to.

    $metadata = msortv($metadata, 'getRelevanceSortVector');
    $list = array_select_keys($list, array_keys($metadata)) + $list;

    $phids = array_keys($list);
    $additional = id(new PhabricatorSearchApplicationContentQuery())
      ->execute($query);
    if (!$this->fulltextTokens) {
      foreach (id(new PhutilSearchQueryCompiler())->setEnableFunctions(true)
        ->newTokens($query->getParameter('query', '')) as $token) {
        $this->fulltextTokens[] = id(new PhabricatorFulltextToken())
          ->setToken($token);
      }
    }
    // Keep a stable, complete application-index order across pages. Ferret
    // provides fallback results for objects which have not been backfilled.
    $table = new PhabricatorSearchApplicationDocument();
    $indexed = array();
    if ($phids) {
      $indexed = queryfx_all($table->establishConnection('r'),
        'SELECT objectPHID FROM %T WHERE objectPHID IN (%Ls)',
        $table->getTableName(), $phids);
      $indexed = array_fuse(ipull($indexed, 'objectPHID'));
    }
    $fallback = array();
    foreach ($phids as $phid) {
      if (!isset($indexed[$phid])) {
        $fallback[] = $phid;
      }
    }
    $phids = array_values(array_unique(array_merge($additional, $fallback)));
    return array_slice($phids, $offset, $limit);
  }

  public function indexExists() {
    return true;
  }

  public function getIndexStats() {
    return false;
  }

  public function getFulltextTokens() {
    return $this->fulltextTokens;
  }


}
