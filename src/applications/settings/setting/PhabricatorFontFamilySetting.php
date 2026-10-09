<?php

abstract class PhabricatorFontFamilySetting extends PhabricatorStringSetting {

  protected function isEnabledForViewer(PhabricatorUser $viewer) {
    return PhabricatorEnv::getEnvConfig('ui.custom.fonts.enabled');
  }

  public function getSettingPanelKey() {
    return PhabricatorDisplayPreferencesSettingsPanel::PANELKEY;
  }

  public function validateTransactionValue($value) {
    if ($value === null || $value === '') {
      return;
    }
    if (!self::isValidFontFamily($value)) {
      throw new Exception(pht(
        'Enter a comma-separated list of font names, without CSS rules or '.
        'a font size. Quote names containing spaces, for example: '.
        '"Noto Sans KR", sans-serif.'));
    }
  }

  public static function isValidFontFamily($value) {
    if (!is_string($value) || strlen((($value) ?? '')) > 1024) {
      return false;
    }
    // Accept Unicode font names, but no CSS escapes, functions, markup,
    // declarations or control characters. Each quoted/unquoted name is complete.
    $name = '(?:"[\p{L}\p{N} _.-]+"|\'[\p{L}\p{N} _.-]+\'|'.
      '[\p{L}_][\p{L}\p{N} _.-]*)';
    return (bool)preg_match('/\A *'.$name.'(?: *, *'.$name.')* *\z/u', $value);
  }
}
