<?php

namespace Acquia\Blt\Custom\Commands;

/**
 * Defines commands in the "custom" namespace.
 */
class LocalCommand extends MRCCommand {

  /**
   * Overrides BLT function to sync to specific environment.
   *
   * @param string $environment
   *   The environment as defined in project.yml or project.local.yml.
   *
   * @return object
   *   The Robo/Result object.
   *
   * @command local:sync:db
   * @description Copies remote db to local db for default site.
   */
  public function syncDbDefault($environment = 'remote') {
    $this->invokeCommand('drupal:sync:default:db');
    $task = $this->taskDrush()
      ->drush('pmu simplesamlphp_auth -y')
      ->drush('en devel kint dblog devel_debug_log -y')
      ->drush('cset devel.settings devel_dumper var_dumper');
    $result = $task->run();
    return $result;
  }

}
