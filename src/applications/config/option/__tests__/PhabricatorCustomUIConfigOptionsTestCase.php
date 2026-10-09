<?php

final class PhabricatorCustomUIConfigOptionsTestCase extends PhabricatorTestCase {

  public function testFontFamilyValidation() {
    foreach (array('"Noto Sans KR", sans-serif', 'D2Coding, monospace',
      "'한글 폰트', serif", 'Arial', 'JetBrains Mono') as $value) {
      $this->assertTrue(PhabricatorFontFamilySetting::isValidFontFamily($value));
    }
    foreach (array('</style><script>alert(1)</script>', 'Arial; color:red',
      'url(https://example.com)', 'Arial\\', "Arial\n", 'Arial,',
      '"Arial', str_repeat('A', 1025)) as $value) {
      $this->assertFalse(PhabricatorFontFamilySetting::isValidFontFamily($value));
    }
  }

  public function testRenderedFontSettings() {
    $env = PhabricatorEnv::beginScopedEnv();
    $env->overrideEnvConfig('ui.custom.fonts.ui-family', 'Arial');
    $user = id(new PhabricatorUser())->setPHID('PHID-USER-fonttest')
      ->attachRawCacheData(array(
        PhabricatorUserPreferencesCacheType::KEY_PREFERENCES => phutil_json_encode(array('monospaced' => null, 'font-family-ui' => null))));
    $request = id(new AphrontRequest('localhost', '/'))->setUser($user);
    $page = id(new PhabricatorStandardPageView())->setRequest($request);
    $method = new ReflectionMethod($page, 'newCustomUIStyle');
    $html = (string)$method->invoke($page);
    $this->assertTrue(strpos($html, '--phabricator-font-ui: Arial;') !== false);
    $user = id(new PhabricatorUser())->setPHID('PHID-USER-fontoverride')
      ->attachRawCacheData(array(
        PhabricatorUserPreferencesCacheType::KEY_PREFERENCES => phutil_json_encode(array('monospaced' => '15px Monaco', 'font-family-ui' => 'Pretendard'))));
    $request->setUser($user);
    $html = (string)$method->invoke($page);
    $this->assertTrue(strpos($html, '--phabricator-font-ui: Pretendard;') !== false);
    $this->assertFalse(strpos($html, '--phabricator-font-code:') !== false);
    $env->overrideEnvConfig('ui.custom.fonts.enabled', false);
    $html = (string)$method->invoke($page);
    $this->assertFalse(strpos($html, '--phabricator-font-ui:') !== false);
    $this->assertFalse(strpos($html, '--phabricator-font-code:') !== false);
  }

}
