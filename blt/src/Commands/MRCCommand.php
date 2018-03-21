<?php

namespace Acquia\Blt\Custom\Commands;

use Acquia\Blt\Robo\BltTasks;

/**
 * Defines commands in the "custom" namespace.
 */
class MRCCommand extends BltTasks {

  /**
   * Set up local environment.
   *
   * @command local:setup
   */
  public function localSetup() {
    $this->invokeCommand('setup:settings');

    $multisites = $this->getConfigValue('multisites');
    $initial_site = $this->getConfigValue('site');
    $current_site = $initial_site;

    foreach ($multisites as $multisite) {
      if ($current_site != $multisite) {
        $this->switchSiteContext($multisite);
        $current_site = $multisite;
      }
      $status = $this->getInspector()->getStatus();

      // Generate settings.php.
      $multisite_dir = $this->getConfigValue('docroot') . "/sites/$multisite";
      $project_local_settings_file = "$multisite_dir/settings/local.settings.php";
      $settings_contents = file_get_contents($project_local_settings_file);

      $database_name = $this->getDatabaseName($multisite, $status['db-name']);

      if ($multisite == 'default') {
        $database_user_name = $this->askDefault('What is the database user name?', $status['db-username']);
        $database_password = $this->askDefault('What is the database password?', $status['db-password']);
      }
      else {
        $database_user_name = $this->askDefault("What is the database user name for $multisite site?", $status['db-username']);
        $database_password = $this->askDefault("What is the database password for $multisite site?", $status['db-password']);
      }

      $settings_contents = str_replace("'database' => 'drupal',", "'database' => '$database_name',", $settings_contents);
      $settings_contents = str_replace("'username' => 'drupal',", "'username' => '$database_user_name',", $settings_contents);
      $settings_contents = str_replace("'password' => 'drupal',", "'password' => '$database_password',", $settings_contents);
      file_put_contents($project_local_settings_file, $settings_contents);

      $status = $this->getInspector()->getStatus();
      $connection = @mysqli_connect(
        $status['db-hostname'],
        $status['db-username'],
        $status['db-password'],
        '',
        $status['db-port']
      );

      $connection->query('CREATE DATABASE IF NOT EXISTS ' . $status['db-name']);
    }
    $this->syncDbDefault('prod');
    $this->syncFiles('prod');
  }

  /**
   * @param string $multisite
   *
   * @return string
   */
  protected function getDatabaseName($multisite = 'default', $default = 'drupal') {
    $database_name = '';
    $count = 0;
    while (!preg_match("/^[a-z0-9_]+$/", $database_name)) {

      if (!$count) {
        $this->say('<info>Only lower case alphanumeric characters and underscores are allowed in the database name.</info>');
      }
      $question = "What is the database name for $multisite site?";
      if ($multisite == 'default') {
        $question = 'What is the database name?';
      }
      $database_name = $this->askDefault($question, '');
      $count++;
    }
    return $database_name;
  }

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
