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

  public function testConfigValidation() {
    $provider = new PhabricatorCustomUIConfigOptions();
    $options = mpull($provider->getOptions(), null, 'getKey');
    foreach (array(
      array('ui.custom.calendar.drag-step-minutes', 5, true),
      array('ui.custom.calendar.drag-step-minutes', 15, true),
      array('ui.custom.calendar.drag-step-minutes', 60, true),
      array('ui.custom.calendar.drag-step-minutes', null, true),
      array('ui.custom.calendar.drag-step-minutes', 0, false),
      array('ui.custom.calendar.drag-step-minutes', -5, false),
      array('ui.custom.calendar.drag-step-minutes', 7, false),
      array('ui.custom.calendar.drag-step-minutes', 90, false),
      array('ui.custom.calendar.drag-step-minutes', 2.5, false),
      array('ui.custom.home.default-dashboard', 123, true),
      array('ui.custom.home.default-dashboard', 0, true),
      array('ui.custom.home.default-dashboard', -1, false),
      array('ui.custom.home.default-dashboard', 'D123', false),
      array('ui.custom.paste.font-size', 16, true),
      array('ui.custom.paste.font-size', 0, false),
      array('ui.custom.files.font-size', 80, false),
      array('ui.custom.files.font-size', null, true),
      array('ui.custom.fonts.ui-family', '"Noto Sans KR", sans-serif', true),
      array('ui.custom.fonts.ui-family', 'monospace; color:red', false),
      array('ui.custom.workboard.control-pan', false, true),
    ) as $case) {
      list($key, $value, $expected) = $case;
      $valid = true;
      try {
        $provider->validateOption($options[$key], $value);
      } catch (Exception $ex) {
        $valid = false;
      }
      $this->assertEqual($expected, $valid, $key);
    }
  }

  public function testRenderedFontSettings() {
    $env = PhabricatorEnv::beginScopedEnv();
    $env->overrideEnvConfig('ui.custom.fonts.ui-family', 'Arial');
    $env->overrideEnvConfig('ui.custom.paste.font-size', 16);
    $env->overrideEnvConfig('ui.custom.files.font-size', 18);
    $user = id(new PhabricatorUser())->setPHID('PHID-USER-fonttest')
      ->attachRawCacheData(array(
        PhabricatorUserPreferencesCacheType::KEY_PREFERENCES => phutil_json_encode(array('monospaced' => null, 'font-family-ui' => null))));
    $request = id(new AphrontRequest('localhost', '/'))->setUser($user);
    $page = id(new PhabricatorStandardPageView())->setRequest($request);
    $method = new ReflectionMethod($page, 'newCustomUIStyle');
    $html = (string)$method->invoke($page);
    $this->assertTrue(strpos($html, '--phabricator-font-ui: Arial;') !== false);
    $this->assertTrue(strpos($html, 'font-size: 16px;') !== false);
    $this->assertTrue(strpos($html, 'font-size: 18px;') !== false);
    $user = id(new PhabricatorUser())->setPHID('PHID-USER-fontoverride')
      ->attachRawCacheData(array(
        PhabricatorUserPreferencesCacheType::KEY_PREFERENCES => phutil_json_encode(array('monospaced' => '15px Monaco', 'font-family-ui' => 'Pretendard'))));
    $request->setUser($user);
    $html = (string)$method->invoke($page);
    $this->assertTrue(strpos($html, '--phabricator-font-ui: Pretendard;') !== false);
    $this->assertFalse(strpos($html, '--phabricator-font-code:') !== false);
    $env->overrideEnvConfig('ui.custom.fonts.enabled', false);
    $env->overrideEnvConfig('ui.custom.document-font-size.enabled', false);
    $html = (string)$method->invoke($page);
    $this->assertFalse(strpos($html, '--phabricator-font-ui:') !== false);
    $this->assertFalse(strpos($html, '--phabricator-font-code:') !== false);
    $this->assertFalse(strpos($html, 'font-size: 16px;') !== false);
  }

  public function testBuiltInHomeFallback() {
    $env = PhabricatorEnv::beginScopedEnv();
    $env->overrideEnvConfig('ui.custom.home.default-dashboard', 0);
    $item = id(new PhabricatorHomeProfileMenuItem())
      ->setViewer(new PhabricatorUser());
    $view = $item->newPageContent(
      new PhabricatorProfileMenuItemConfiguration());
    $this->assertTrue($view instanceof PHUIHomeView);
    $dashboard_item = new PhabricatorDashboardProfileMenuItem();
    $this->assertTrue($dashboard_item->canMakeDefault(
      new PhabricatorProfileMenuItemConfiguration()));
  }

  public function testPasteEmbedExpansion() {
    $env = PhabricatorEnv::beginScopedEnv();
    $paste = id(new PhabricatorPaste())
      ->setID(123)
      ->attachContent(implode("\n", array_fill(0, 60, 'example')));
    $handle = id(new PhabricatorObjectHandle())->setName('Example Paste');
    $view = id(new PasteEmbedView())
      ->setPaste($paste)
      ->setHandle($handle)
      ->setLines(2);

    $env->overrideEnvConfig('ui.custom.paste.expand-embeds', true);
    $html = (string)$view->render();
    $this->assertTrue(strpos($html, 'paste-embed-expanded') !== false);
    $this->assertFalse(strpos($html, 'max-height:') !== false);

    $env->overrideEnvConfig('ui.custom.paste.expand-embeds', false);
    $html = (string)$view->render();
    $this->assertFalse(strpos($html, 'paste-embed-expanded') !== false);
    $this->assertTrue(strpos($html, 'max-height: 2.3em;') !== false);
  }

}
