<?php declare(strict_types=1);

namespace Tolkam\Composer\CustomPath;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

class Plugin implements PluginInterface
{
    /**
     * @inheritDoc
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $composer->getInstallationManager()
            ->addInstaller(new Installer($io, $composer));
    }
}
