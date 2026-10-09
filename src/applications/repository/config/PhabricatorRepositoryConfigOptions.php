<?php

final class PhabricatorRepositoryConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht('Repositories');
  }

  public function getDescription() {
    return pht('Configure repositories.');
  }

  public function getIcon() {
    return 'fa-hdd-o';
  }

  public function getGroup() {
    return 'apps';
  }

  public function getApplicationClassName() {
    return PhabricatorDiffusionApplication::class;
  }

  public function getOptions() {
    return array(
      $this->newOption('repository.default-git-branch', 'string', 'master')
        ->setSummary(pht('Default Git branch when no repository override is set.'))
        ->setDescription(pht(
          'Repository-specific default branch settings take priority.')),
      $this->newOption('repository.default-local-path', 'string', '/var/repo/')
        ->setLocked(true)
        ->setSummary(
          pht('Default location to store local copies of repositories.'))
        ->setDescription(
          pht(
            'The default location in which to store working copies and other '.
            'data about repositories. %s will control and manage '.
            'data here, so you should **not** choose an existing directory '.
            'full of data you care about.',
            PlatformSymbols::getPlatformServerName())),
    );
  }

  protected function didValidateOption(
    PhabricatorConfigOption $option,
    $value) {
    if ($option->getKey() === 'repository.default-git-branch' &&
        (!phutil_nonempty_string($value) || trim($value) !== $value ||
         preg_match('/[\x00-\x20\x7f]/', $value))) {
      throw new PhabricatorConfigValidationException(
        pht('Enter a nonempty Git branch name without whitespace.'));
    }
  }

}
