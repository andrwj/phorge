<?php

final class PhabricatorVariableWidthFontSetting
  extends PhabricatorFontFamilySetting {

  const SETTINGKEY = 'font-family-ui';

  public function getSettingName() {
    return pht('Variable-Width Font Family');
  }

  protected function getSettingOrder() {
    return 480;
  }

  protected function getControlInstructions() {
    return pht(
      'Font families for menus, titles, text and input controls across '.
      'Phorge. Example: `"Pretendard", "JetBatang NF", '.
      '"Noto Sans KR", sans-serif`. '.
      'Fonts must be installed on your device. Leave blank to use the default.');
  }
}
