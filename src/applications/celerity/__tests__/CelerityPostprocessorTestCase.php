<?php

final class CelerityPostprocessorTestCase extends PhabricatorTestCase {

  public function testGetAllCelerityPostprocessors() {
    CelerityPostprocessor::getAllPostprocessors();
    $this->assertTrue(true);
  }

  public function testSaffronInkResources() {
    $theme = CelerityPostprocessor::getPostprocessor('saffron-ink');
    $this->assertTrue($theme instanceof CeleritySaffronInkPostprocessor);
    $variables = $theme->getVariables();
    foreach (id(new CelerityLargeFontPostprocessor())->buildVariables()
      as $key => $value) {
      $this->assertEqual($value, $variables[$key]);
    }

    // Exercise the actual resource transformer on every CSS file. This also
    // detects missing variables and invalid resource references.
    $root = dirname(phutil_get_library_root('phabricator'));
    $paths = id(new FileFinder($root.'/webroot/rsrc/css'))
      ->withType('f')
      ->withSuffix('css')
      ->find();
    $transformer = id(new CelerityResourceTransformer())
      ->setPostprocessorKey('saffron-ink')
      ->setTranslateURICallback(function($matches) { return $matches[0]; });
    foreach ($paths as $path) {
      $data = Filesystem::readFile($root.'/webroot/rsrc/css/'.$path);
      $css = $transformer->transformResource($path, $data);
      $this->assertFalse(strpos($css, '{$') !== false, $path);
    }
    $this->assertEqual('#eddfb7', $variables['page.background']);
    $this->assertEqual('#4f5a63', $variables['syntax.comment']);
    $this->assertEqual('#b72434', $variables['syntax.keyword']);
    $cursor_css = Filesystem::readFile(
      $root.'/webroot/rsrc/css/core/saffron-ink.css');
    $this->assertTrue(strpos($cursor_css, 'caret-color: #d73a49') !== false);
  }

}
