<?php

namespace ABC\Composer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Installer;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Package\PackageInterface;
use Composer\Installer\LibraryInstaller;
use Composer\Util\Filesystem;
use Composer\Util\ProcessExecutor;

/**
 * This class is in charge of handling the installation of an external package
 * that will be downloaded.
 *
 *
 * @author David Négrier
 */
class NonDestructiveArchiveInstallerInstaller extends LibraryInstaller {

    protected $rfs;

    /**
     * Initializes library installer.
     *
     * @param IOInterface $io
     * @param Composer    $composer
     * @param string      $type
     */
    public function __construct(IOInterface $io, Composer $composer, $type = 'library')
    {
        parent::__construct($io, $composer, $type);
        $this->fs = new Filesystem(new ProcessExecutor($io));
    }

    /**
     * {@inheritDoc}
     */
    public function update(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $package)
    {
        parent::update($repo, $initial, $package);

        $this->moveDownloadedPackage($package);
    }

    /**
     * {@inheritDoc}
     */
    public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        parent::install($repo, $package);

        $this->moveDownloadedPackage($package);
    }

    /**
     * Move the package, only if the URL to download has not been downloaded before.
     *
     * @param PackageInterface $package
     * @throws \RuntimeException
     * @throws \UnexpectedValueException
     */
    private function moveDownloadedPackage(PackageInterface $package) {

        // get extra data
        $c_extra = $this->composer->getPackage()->getExtra();
        $p_extra = $package->getExtra();

        $url = $package->getDistUrl();

        if ($url) {

            // handle package level config
            // ---------------------------
             
            $omitFirstDirectory = (isset($p_extra['omit-first-directory']))
                ? strtolower($p_extra['omit-first-directory']) == "true"
                : false;

            $targetDir = isset($p_extra['target-dir'])
                ? realpath('./' . trim($p_extra['target-dir'], '/')) . '/'
                : $this->getInstallPath($package);

            // handle overrides
            // ---------------------------

            if (isset($c_extra['installer-paths'])) {
                foreach ($c_extra['installer-paths'] as $path => $pkgs) {
                    foreach ($pkgs as $pkg) {
                        if ($pkg == $package->getName()) {
                            $targetDir = realpath('./' . trim($path, '/')) . '/';
                        }
                    }
                }
            }

            // If the archive has been downloaded then do nothing
            if (self::getLastDownloadedFileUrl($package) == $url) {
                return;
            }

            // Move all downloaded files to the target directory.
            $installDir = $this->getInstallPath($package) . '/';
            foreach (scandir($installDir) as $file) {
                if (!in_array($file, array('.', '..', 'download-status.txt'))) {
                    $this->filesystem->rename($installDir . $file, $targetDir);
                }
            }

            // Save last download URL
            self::setLastDownloadedFileUrl($package, $url);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        parent::uninstall($repo, $package);
    }

    /**
     * {@inheritDoc}
     */
    public function supports($packageType)
    {
        return 'non-destructive-archive-installer' === $packageType;
    }

    /**
     * Returns the URL of the last file that this install process ever downloaded.
     *
     * @param PackageInterface $package
     * @return string
     */
    public static function getLastDownloadedFileUrl(PackageInterface $package) {
        $packageDir = self::getPackageDir($package);
        if (file_exists($packageDir."download-status.txt")) {
            return file_get_contents($packageDir."download-status.txt");
        } else {
            return null;
        }
    }

    /**
     * Saves the URL of the last file that this install process downloaded into a file for later retrieval.
     *
     * @param PackageInterface $package
     * @param unknown $url
     */
    protected static function setLastDownloadedFileUrl(PackageInterface $package, $url) {
        $packageDir = self::getPackageDir($package);
        file_put_contents($packageDir."download-status.txt", $url);
    }

    /**
     * Returns the package directory, with a trailing /
     *
     * @param PackageInterface $package
     * @return string
     */
    protected static function getPackageDir(PackageInterface $package) {
        return __DIR__."/../../../../../".$package->getName()."/";
    }


}
