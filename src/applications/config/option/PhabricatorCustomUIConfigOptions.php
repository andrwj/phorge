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
      'ui.custom.fonts.enabled' => array('bool', true,
        'Enable custom font families and per-user font family preferences.'),
      'ui.custom.fonts.ui-family' => array('string', '',
        'Default interface font family list. User preferences take priority.'),
      'ui.custom.document-font-size.enabled' => array('bool', true,
        'Enable font size overrides in Paste and Files document views.'),
      'ui.custom.paste.font-size' => array('int', 12,
        'Paste document text size in pixels (8 to 72).'),
      'ui.custom.files.font-size' => array('int', 12,
        'Files document text size in pixels (8 to 72).'),
      'ui.custom.document-font-size.windows-offset' => array('int', 1,
        'Additional pixels for document text on Windows (0 to 10).'),
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
    if ($key === 'ui.custom.fonts.ui-family') {
      if ($value !== '' &&
          !PhabricatorFontFamilySetting::isValidFontFamily($value)) {
        throw new PhabricatorConfigValidationException(pht('Enter a valid comma-separated font family list.'));
      }
    }
    $ranges = array(
      'ui.custom.paste.font-size' => array(8, 72),
      'ui.custom.files.font-size' => array(8, 72),
      'ui.custom.document-font-size.windows-offset' => array(0, 10),
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
