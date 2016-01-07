<?php

namespace Concrete\ComposerInstallers;

use Composer\Installer\LibraryInstaller;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Util\Filesystem;

class CoreInstaller extends LibraryInstaller
{
    const PACKAGE_TYPE = 'concrete5-core';

    const DEFAULT_TARGET_PATH = 'concrete';

    /**
     * {@inheritDoc}
     */
    public function supports($packageType)
    {
        return $packageType === self::PACKAGE_TYPE;
    }

    /**
     * {@inheritDoc}
     */
    public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        parent::install($repo, $package);

        $this->installCore($package);
    }

    /**
     * {@inheritDoc}
     */
    public function update(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $target)
    {
        parent::update($repo, $initial, $target);

        $this->installCore($target);
    }

    /**
     * {@inheritDoc}
     */
    public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        parent::uninstall($repo, $package);

        $coreInstallPath = $this->getCoreInstallPath($package);

        // make sure we have absolute paths
        $coreInstallPath = realpath('') . '/' . rtrim($coreInstallPath, '/');

        if (is_link($coreInstallPath)) {

            $this->io->write(sprintf('    Removing concrete5 symlink %s - %s', $coreInstallPath, $this->filesystem->unlink($coreInstallPath) ? '<comment>removed</comment>' : '<error>not removed</error>'));
            $this->io->write('');
        }
    }

    protected function installCore(PackageInterface $package)
    {
        $installPath = $this->getInstallPath($package);
        
        // find core, might vary, depending on download type
        if (file_exists($installPath . '/web/concrete')) {

            // source
            $coreSourcePath = $installPath . '/web/concrete';

        } else {
            // dist
            $coreSourcePath = $installPath . '/concrete';
        }

        $coreInstallPath = $this->getCoreInstallPath($package);

        // make sure we have absolute paths
        $coreSourcePath = realpath($coreSourcePath);
        $coreInstallPath = realpath('') . '/' . rtrim($coreInstallPath, '/');

        // make sure parent core path exists
        $parentPath = dirname($coreInstallPath);
        $this->filesystem->ensureDirectoryExists($parentPath);

        // check for existing core path
        if (file_exists($coreInstallPath) && !is_link($coreInstallPath)) {

            throw new \RuntimeException('Can\'t create concrete5 symlink as folder already exists. Please remove ' . $coreInstallPath);

        } else {

            if (is_link($coreInstallPath)) {

                if (realpath(readlink($coreInstallPath)) == $coreSourcePath) {

                    // already has correct symlink, done here
                    return;
                }

                // remove existing incorrect symlink
                $this->filesystem->unlink($coreInstallPath);
            }

            $this->io->write(sprintf('    Creating concrete5 symlink %s - %s', $coreInstallPath, $this->filesystem->relativeSymlink($coreSourcePath, $coreInstallPath) ? '<info>created</info>' : '<error>not created</error>'));
            $this->io->write('');
        }
    }

    protected function getCoreInstallPath(PackageInterface $package)
    {
        $type = $package->getType();

        $prettyName = $package->getPrettyName();

        if (strpos($prettyName, '/') !== false) {
            list($vendor, $name) = explode('/', $prettyName);
        } else {
            $vendor = '';
            $name = $prettyName;
        }

        $availableVars = compact('name', 'vendor', 'type');

        $extra = $package->getExtra();
        if (!empty($extra['installer-name'])) {
            $availableVars['name'] = $extra['installer-name'];
        }

        if ($this->composer->getPackage()) {
            $extra = $this->composer->getPackage()->getExtra();
            if (!empty($extra['installer-paths'])) {
                $customPath = $this->mapCustomInstallPaths($extra['installer-paths'], $prettyName, $type);
                if ($customPath !== false) {
                    return $this->templatePath($customPath, $availableVars);
                }
            }
        }

        $path = self::DEFAULT_TARGET_PATH;

        return $this->templatePath($path, $availableVars);
    }

    /**
     * Replace vars in a path
     */
    protected function templatePath($path, array $vars = array())
    {
        if (strpos($path, '{') !== false) {
            extract($vars);
            preg_match_all('@\{\$([A-Za-z0-9_]*)\}@i', $path, $matches);
            if (!empty($matches[1])) {
                foreach ($matches[1] as $var) {
                    $path = str_replace('{$' . $var . '}', $$var, $path);
                }
            }
        }
        return $path;
    }

    /**
     * Search through a passed paths array for a custom install path.
     */
    protected function mapCustomInstallPaths(array $paths, $name, $type)
    {
        foreach ($paths as $path => $names) {
            if (in_array($name, $names) || in_array('type:' . $type, $names)) {
                return $path;
            }
        }
        return false;
    }
}
