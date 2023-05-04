<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\Cli\CliCommandTrait;
use Dockworker\Drupal\DrupalCodeTrait;
use Dockworker\IO\DockworkerIO;
use Dockworker\IO\DockworkerIOTrait;
use Dockworker\Robo\Plugin\Commands\ThemeCommands;
use Dockworker\Scss\ScssCompileTrait;
use Symfony\Component\Finder\Finder;

/**
 * Defines commands used to build themes for the Drupal application.
 */
class DrupalThemeCommands extends ThemeCommands
{
    use CliCommandTrait;
    use DockworkerIOTrait;
    use DrupalCodeTrait;
    use ScssCompileTrait;

    /**
     * The path to the theme being built.
     *
     * @var string
     */
    private string $path;

    /**
     * Compiles this application's Drupal themes.
     *
     * @hook post-command theme:build-all
     */
    public function setBuildAllDrupalThemes(): void
    {
        $this->initOptions();
        $this->initDockworkerIO();
        $this->preInitDockworkerPersistentDataStorageDir();
        $this->registerSassCliTool($this->dockworkerIO);
        $this->checkPreflightChecks($this->dockworkerIO);
        $this->getCustomModulesThemes();
        if (!empty($this->drupalThemes)) {
            $this->dockworkerIO->title('Building Drupal Themes');
        }
        foreach ($this->drupalThemes as $theme) {
            $this->buildDrupalTheme(
                $this->dockworkerIO,
                $theme->getPath()
            );
        }
    }

    /**
     * Builds a Drupal theme's assets.
     *
     * @param \Dockworker\IO\DockworkerIO $io
     *   The IO to use for input and output.
     * @param string $path
     *   The absolute path of the theme to build.
     */
    private function buildDrupalTheme(
        DockworkerIO $io,
        string $path
    ): void {
        if (file_exists($path)) {
            $io->section("Building $path");
            $this->path = $path;
            $this->setPermissionsThemeDist($io);
            $this->buildThemeScss($io);
            $this->buildImageAssets($io);
            $this->buildJsAssets($io);
            $this->buildFontAssets($io);
        }
    }

    /**
     * Ensures the current theme's dist directory exists, and is writable.
     *
     * @param \Dockworker\IO\DockworkerIO $io
     *   The IO to use for input and output.
     */
    private function setPermissionsThemeDist(DockworkerIO $io): void
    {
        $this->executeCliCommandSet(
            [
                [
                    'command' => [
                        'mkdir',
                        '-p',
                        "$this->path/dist/css"
                    ],
                    'message' => 'Creating dist directory',
                    'tty' => false
                ],
                [
                    'command' => [
                        'sudo',
                        'chgrp',
                        '-R',
                        $this->userGid,
                        "$this->path/dist"
                    ],
                    'message' => 'Setting group ownership of theme',
                    'tty' => false
                ],
                [
                    'command' => [
                        'sudo',
                        'chmod',
                        '-R',
                        'g+w',
                        "$this->path/dist"
                    ],
                    'message' => 'Setting group write of dist directory',
                    'tty' => false
                ],
            ],
            $io,
            ''
        );
    }

    /**
     * Compiles the current theme's SCSS files into CSS.
     *
     * @param \Dockworker\IO\DockworkerIO $io
     *   The IO to use for input and output.
     */
    private function buildThemeScss(DockworkerIO $io): void
    {
        $finder = new Finder();
        $finder->in($this->path)
            ->files()
            ->name('/^[^_].*\.scss$/');
        foreach ($finder as $file) {
            $source_file = $file->getRealPath();

            // Determine the source directory.
            $source_file_name = basename($source_file);
            $source_dirstring = str_replace(
                [
                    $this->path,
                    $source_file_name,
                ],
                ['', ''],
                $source_file
            );

            $target_file_name = preg_replace(
                '/\.scss/',
                '.css',
                $source_file_name
            );

            $target_file = "$this->path/dist/css/$target_file_name";
            $this->compileScss(
                $source_file,
                $target_file,
                $io,
                $this->path
            );
        }
    }

    /**
     * Builds the current theme's image assets.
     *
     * @param \Dockworker\IO\DockworkerIO $io
     *   The IO to use for input and output.
     *
     * @TODO Optimize images into a standard instead of simply copying them.
     */
    private function buildImageAssets(DockworkerIO $io): void
    {
        $this->copyThemeAssets($io, 'img', 'Image');
    }

    /**
     * Builds the current theme's Javascript assets.
     *
     * @param \Dockworker\IO\DockworkerIO $io
     *   The IO to use for input and output.
     *
     * @TODO Minify javascript files instead of simply copying them.
     */
    private function buildJsAssets(DockworkerIO $io): void
    {
        $this->copyThemeAssets($io, 'js', 'Javascript');
    }

    /**
     * Builds the current theme's font assets.
     *
     * @param \Dockworker\IO\DockworkerIO $io
     *   The IO to use for input and output.
     */
    private function buildFontAssets(DockworkerIO $io): void
    {
        $this->copyThemeAssets($io, 'fonts', 'Font');
    }

    /**
     * Copies asset files unmodified from src to dist.
     *
     * @param \Dockworker\IO\DockworkerIO $io
     *   The IO to use for input and output.
     * @param string $asset_dir
     *   The directory to copy.
     * @param string $type
     *   A label to use when identifying the directory contents.
     */
    private function copyThemeAssets(
        DockworkerIO $io,
        string $asset_dir,
        string $type
    ): void {
        $src_path = "$this->path/src/$asset_dir";
        if (file_exists($src_path)) {
            $this->executeCliCommand(
                [
                    'cp',
                    '-r',
                    "src/$asset_dir",
                    'dist/',
                ],
                $io,
                $this->path,
                '',
                "Deploying $type Assets in $src_path",
                false
            );
        }
    }
}
