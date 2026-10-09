<?php

final class PhabricatorConfigSettingsListController
  extends PhabricatorConfigSettingsController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $filter = $request->getURIData('filter');
    if (!phutil_nonempty_string($filter)) {
      $filter = 'settings';
    }

    $is_core = ($filter === 'settings');
    $is_advanced = ($filter === 'advanced');
    $is_all = ($filter === 'all');

    $show_core = ($is_core || $is_all);
    $show_advanced = ($is_advanced || $is_all);
    $show_apps_config = $is_all;

    if ($is_core) {
      $title = pht('Core Settings');
    } else if ($is_advanced) {
      $title = pht('Advanced Settings');
    } else {
      $title = pht('All Settings');
    }

    $list = id(new PhorgeConfigOptionListView())
      ->setViewer($viewer)
      ->setFlush(true);

    static $system_applications = array(
      PhabricatorAuthApplication::class,
      PhabricatorDaemonsApplication::class,
      PhabricatorFilesApplication::class,
      PhabricatorMetaMTAApplication::class,
      PhabricatorNotificationsApplication::class,
      PhabricatorPolicyApplication::class,
      PhabricatorSystemApplication::class,
      PhorgeExtensionsApplication::class,
    );

    if ($show_apps_config) {
      $options = PhabricatorApplicationConfigOptions::loadAllOptions();
      $change_notice = null;

    } else {
      $options =
        PhabricatorApplicationConfigOptions::loadOptionsForApplications(
          $system_applications);

      $notice = array(
        pht(
          'Application-specific settings are moved into dedicated pages '.
          'found under %s > %s.',
          pht('Applications'),
          pht('Configure')),
        phutil_tag('br'),
        pht(
          'All settings (system and applications) are available under "%s" in '.
          'the navigation menu.',
          pht('All Settings')),
      );

      $change_notice = id(new PHUIActionPanelView())
        ->setIcon('fa-server')
        ->setHeader(array(pht('Notice')))
        ->setHref('/applications/')
        ->setSubHeader($notice)
        ->setState(PHUIActionPanelView::COLOR_PINK);
    }

    ksort($options);

    foreach ($options as $key => $option) {
      $is_advanced = (bool)$option->getLocked();
      if ($is_advanced && $show_advanced) {
        continue;
      }

      if (!$is_advanced && $show_core) {
        continue;
      }

      unset($options[$key]);
    }

    $search_form = null;
    $search_error = null;
    if (PhabricatorEnv::getEnvConfig('ui.custom.search.config-settings')) {
      $search_text = $request->getStr('query', '');
      $search_form = id(new AphrontFormView())
        ->setUser($viewer)
        ->setMethod('GET')
        ->setAction($request->getPath())
        ->appendControl(id(new AphrontFormTextControl())
          ->setLabel(pht('Search Settings'))
          ->setName('query')
          ->setValue($search_text))
        ->appendControl(id(new AphrontFormSubmitControl())
          ->setValue(pht('Search')));
      if (strlen(trim($search_text))) {
        $saved = id(new PhabricatorSavedQuery())
          ->setParameter('query', $search_text);
        try {
          $options = id(new PhabricatorConfigOptionSearch())
            ->setViewer($viewer)
            ->execute($saved, $options);
        } catch (PhutilSearchQueryCompilerSyntaxException $ex) {
          $options = array();
          $search_error = id(new PHUIInfoView())
            ->setErrors(array($ex->getMessage()));
        }
      }
    }

    $list->setOptions($options);

    $header = $this->buildHeaderView($title);

    $crumbs = $this->newCrumbs()
      ->addTextCrumb($title);

    $content = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setSubheader($change_notice)
      ->setFooter(array($search_form, $search_error, $list));

    $nav = $this->newNavigation($filter);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->setNavigation($nav)
      ->appendChild($content);
  }

}
