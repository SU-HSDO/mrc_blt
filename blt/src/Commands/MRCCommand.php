<?php

namespace Acquia\Blt\Custom\Commands;

use Acquia\Blt\Robo\BltTasks;

/**
 * Defines commands in the "custom" namespace.
 */
class MRCCommand extends BltTasks {

  /**
   * Copies remote db to local db for default site.
   *
   * @param string $environment
   *   The environment as defined in project.yml or project.local.yml.
   *
   * @return object
   *   The Robo/Result object.
   *
   * @command drupal:sync:default:db
   *
   * @aliases dsb drupal:sync:db sync:db
   */
  public function syncDbDefault($environment = 'remote') {

    $this->invokeCommand('setup:settings');

    $local_alias = '@' . $this->getConfigValue('drush.aliases.local');
    $remote_alias = $this->getRemoteAlias($environment);

    $task = $this->taskDrush()
      ->alias('')
      ->drush('cache-clear drush')
      ->drush('sql-drop')
      ->drush('sql-sync')
      ->arg("@$remote_alias")
      ->arg($local_alias)
      // @see https://github.com/drush-ops/drush/releases/tag/9.2.1
      // @see https://github.com/acquia/blt/issues/2641
      ->option('--source-dump', sys_get_temp_dir() . '/tmp.sql')
      ->option('structure-tables-key', 'lightweight')
      ->option('create-db');

    if ($this->getConfigValue('drush.sanitize')) {
      $task->drush('sql-sanitize');
    }

    $task->drush('cr');
    $task->drush('sqlq "TRUNCATE cache_entity"');

    $result = $task->run();

    return $result;
  }

  /**
   * Overrides blt sync files command.
   *
   * @param string $environment
   *   The environment as defined in project.yml or project.local.yml.
   *
   * @return object
   *   The Robo/Result object.
   *
   * @command sync:files
   * @description Copies remote files to local machine.
   */
  public function syncFiles($environment = 'remote') {
    $remote_alias = $this->getRemoteAlias($environment);
    $site_dir = $this->getConfigValue('site');

    $task = $this->taskDrush()
      ->alias('')
      ->uri('')
      ->drush('rsync')
      ->arg("@$remote_alias" . ':%files/')
      ->arg($this->getConfigValue('docroot') . "/sites/$site_dir/files")
      ->option('exclude-paths', implode(':', $this->getConfigValue('sync.exclude-paths')));

    $result = $task->run();

    return $result;
  }

  /**
   * Get the remote alias.
   *
   * @param string $environment
   *   Environment name defined in project.yml or project.local.yml.
   *
   * @return string
   *   Drush alias name.
   */
  protected function getRemoteAlias($environment = 'remote') {

    // For ODE environments, just get the remote and replace with the ode name.
    if (strpos($environment, 'ode') !== FALSE) {
      $alias = $this->getConfigValue('drush.aliases.remote');
      return str_replace('.test', ".$environment", $alias);
    }

    return $this->getConfigValue("drush.aliases.$environment");
  }

}
