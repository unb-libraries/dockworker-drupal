<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\DrupalCodeTrait;
use Dockworker\Robo\Plugin\Commands\DockworkerApplicationCommands;
use Dockworker\ScssCompileTrait;
use Symfony\Component\Finder\Finder;

/**
 * Defines commands used to build Drupal themes.
 */
class DrupalThemeCommands extends DockworkerApplicationCommands {

  use DrupalCodeTrait;
  use ScssCompileTrait;

  /**
   * The path to the theme being built.
   *
   * @var string
   */
  private $path = NULL;

  /**
   * Compile Drupal themes before building the application containers.
   *
   * @hook post-command theme:build-all
   * @throws \Exception
   */
  public function setBuildAllDrupalThemes() {
    $this->getCustomModulesThemes();
    foreach ($this->drupalThemes as $theme) {
      $this->buildDrupalThemeAssets($theme->getPath());
    }
  }

  /**
   * Build a Drupal theme's assets.
   *
   * @param string $path
   *   The absolute path of the theme to build.
   */
  private function buildDrupalThemeAssets($path) {
    if (file_exists($path)) {
      $this->path = $path;
      $this->setPermissionsThemeDist();
      $this->setCompiler($this->repoRoot . '/vendor/bin/pscss');
      $this->buildThemeScss();
      $this->buildImageAssets();
      $this->buildJsAssets();
    }
  }

  /*
   * Ensure the theme's dist directory exists, and is writable.
   */
  private function setPermissionsThemeDist() {
    $this->say("Setting Permissions of dist in $this->path");
    $this->taskExecStack()
      ->stopOnFail()
      ->dir($this->path)
      ->exec("mkdir -p dist")
      ->run();
    $this->taskExecStack()
      ->stopOnFail()
      ->dir($this->path)
      ->exec("chmod -R g+w dist")
      ->run();
  }

  /*
   * Build the theme's SCSS files into CSS.
   */
  private function buildThemeScss() {
    $finder = new Finder();
    $finder->in($this->path)
      ->files()
      ->name('/^[^_].*\.scss$/');
    foreach ($finder as $file) {
      $source_file = $file->getRealPath();
      $target_file = str_replace(['/src/scss/', '.scss'], ['/dist/', '.css'], $source_file);
      $this->compileScss($source_file, $target_file, $this->repoRoot);
    }
  }

  /*
   * Build image assets.
   *
   * @TODO Optimize images into a standard instead of just copying them.
   */
  private function buildImageAssets() {
    $this->copyThemeAssets('img', 'Image');

  }

  /*
   * Build Javascript assets.
   *
   * @TODO Minify javascript files instead of just copying them.
   */
  private function buildJsAssets() {
    $this->copyThemeAssets('js', 'Javascript');
  }

  /*
   * Copy asset files unmodified from src to dist.
   *
   * @param string $asset_dir
   *   The directory to copy.
   * @param string $type
   *   A label to use when identifying the directory contents.
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
