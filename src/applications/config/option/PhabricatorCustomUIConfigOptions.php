<?php

final class PhabricatorCustomUIConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht('Custom User Interface');
  }

  public function getDescription() {
    return pht('Configure the custom UI features on the andrwj branch.');
  }

  public function getIcon() {
    return 'fa-sliders';
  }

  public function getGroup() {
    return 'core';
  }

  public function getApplicationClassName() {
    return PhabricatorSystemApplication::class;
  }

  public function getOptions() {
    $specs = array(
      'ui.custom.workboard.control-pan' => array('bool', true,
        'Enable Ctrl + mouse drag scrolling on Workboards.'),
      'ui.custom.search.collapsible-filters' => array('bool', true,
        'Collapse global advanced search filters by default.'),
    );
    $options = array();
    foreach ($specs as $key => $spec) {
      list($type, $default, $description) = $spec;
      $options[] = $this->newOption($key, $type, $default)
        ->setDescription(pht('%s', $description));
    }
    return $options;
  }

  public function validateOption(PhabricatorConfigOption $option, $value) {
    parent::validateOption($option, $value);
    if ($value === null) {
      return;
    }
    $key = $option->getKey();
    $ranges = array(
    );
    if (isset($ranges[$key])) {
      list($min, $max) = $ranges[$key];
      if ($value < $min || $value > $max) {
        throw new PhabricatorConfigValidationException(pht('Option "%s" must be between %d and %d.',
          $key, $min, $max));
      }
    }
  }

}
