<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\DrupalCodeTrait;
use Dockworker\Robo\Plugin\Commands\DockworkerLocalCommands;
use Dockworker\ScssCompileTrait;
use Symfony\Component\Finder\Finder;

/**
 * Defines commands used to build themes for the local Drupal application.
 */
class DrupalThemeCommands extends DockworkerLocalCommands {

  use DrupalCodeTrait;
  use ScssCompileTrait;

  /**
   * The path to the theme being built.
   *
   * @var string
   */
  private $path = NULL;

  /**
   * Compiles Drupal themes.
   *
   * @hook post-command theme:build-all
   *
   * @drupalcode
   */
  public function setBuildAllDrupalThemes() {
    $this->getCustomModulesThemes();
    foreach ($this->drupalThemes as $theme) {
      $this->buildDrupalThemeAssets($theme->getPath());
    }

  }

  /**
   * Builds a Drupal theme's assets.
   *
   * @param string $path
   *   The absolute path of the theme to build.
   */
  private function buildDrupalThemeAssets($path) {
    if (file_exists($path)) {
      $this->path = $path;
      $this->setPermissionsThemeDist();
      $this->setScssCompiler($this->repoRoot . '/vendor/bin/pscss');
      $this->buildThemeScss();
      $this->buildImageAssets();
      $this->buildJsAssets();
    }
  }

  /*
   * Ensures the current theme's dist directory exists, and is writable.
   *
   * @throws \Robo\Exception\TaskException
   */
  private function setPermissionsThemeDist() {
    $this->say("Setting Permissions of dist in $this->path");
    $this->taskExecStack()
      ->stopOnFail()
      ->dir($this->path)
      ->exec("mkdir -p dist/css")
      ->run();

    $gid = posix_getgid();
    $this->taskExec('sudo chgrp')
      ->arg($gid)
      ->arg('-R')
      ->arg($this->path)
      ->run();
    $this->taskExecStack()
      ->stopOnFail()
      ->dir($this->path)
      ->exec("sudo chmod -R g+w dist")
      ->run();
  }

  /*
   * Compiles the current theme's SCSS files into CSS.
   */
  private function buildThemeScss() {
    $finder = new Finder();
    $finder->in($this->path)
      ->files()
      ->name('/^[^_].*\.scss$/');
    foreach ($finder as $file) {
      $source_file = $file->getRealPath();
      $target_file = str_replace(['/src/scss/', '.scss'], ['/dist/css/', '.css'], $source_file);
      $this->say("Compiling $source_file to $target_file...");
      $this->compileScss($source_file, $target_file);
    }
  }

  /*
   * Builds the current theme's image assets.
   *
   * @TODO Optimize images into a standard instead of just copying them.
   */
  private function buildImageAssets() {
    $this->copyThemeAssets('img', 'Image');
  }

  /*
   * Builds the current theme's Javascript assets.
   *
   * @TODO Minify javascript files instead of just copying them.
   */
  private function buildJsAssets() {
    $this->copyThemeAssets('js', 'Javascript');
  }

  /*
   * Copies asset files unmodified from src to dist.
   *
   * @param string $asset_dir
   *   The directory to copy.
   * @param string $type
   *   A label to use when identifying the directory contents.
   *
   * @throws \Robo\Exception\TaskException
   */
  private function copyThemeAssets($asset_dir, $type) {
    $src_path = "$this->path/src/$asset_dir";
    if (file_exists($src_path)) {
      $this->say("Deploying $type Assets in $src_path");
      $this->taskExecStack()
        ->stopOnFail()
        ->dir($this->path)
        ->exec("cp -r src/$asset_dir dist/ || true")
        ->run();
    }
  }

}
