<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\Cli\CliCommandTrait;
use Dockworker\IO\DockworkerIO;
use Dockworker\Robo\Plugin\Commands\DockworkerThemeCommands;
use Dockworker\Drupal\DrupalCodeTrait;
use Dockworker\IO\DockworkerIOTrait;
use Dockworker\Scss\ScssCompileTrait;
use Symfony\Component\Finder\Finder;

/**
 * Defines commands used to build themes for the Drupal application.
 *
 * @TODO: Needs review, was migrated from Dockworker 5.x.
 */
class DrupalThemeCommands extends DockworkerThemeCommands {
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
     * Compiles Drupal themes.
     *
     * @hook post-command theme:build-all
     */
    public function setBuildAllDrupalThemes(): void {
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
            $this->setScssCompiler(
                $this->applicationRoot . '/vendor/bin/pscss'
            );
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
    private function setPermissionsThemeDist(DockworkerIO $io): void {
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
    private function buildThemeScss(DockworkerIO $io): void {
        $finder = new Finder();
        $finder->in($this->path)
            ->files()
            ->name('/^[^_].*\.scss$/');
        foreach ($finder as $file) {
            $source_file = $file->getRealPath();
            $target_file = str_replace(['/src/scss/', '.scss'], ['/dist/css/', '.css'], $source_file);
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
     * @TODO Optimize images into a standard instead of just copying them.
     *
     * @throws \Robo\Exception\TaskException
     */
    private function buildImageAssets(DockworkerIO $io): void {
        $this->copyThemeAssets($io, 'img', 'Image');
    }

    /**
     * Builds the current theme's Javascript assets.
     *
     * @param \Dockworker\IO\DockworkerIO $io
     *   The IO to use for input and output.
     *
     * @TODO Minify javascript files instead of just copying them.
     *
     * @throws \Robo\Exception\TaskException
     */
    private function buildJsAssets(DockworkerIO $io): void {
        $this->copyThemeAssets($io, 'js', 'Javascript');
    }

    /**
     * Builds the current theme's font assets.
     *
     * @param \Dockworker\IO\DockworkerIO $io
     *   The IO to use for input and output.
     *
     * @throws \Robo\Exception\TaskException
     */
    private function buildFontAssets(DockworkerIO $io): void {
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
