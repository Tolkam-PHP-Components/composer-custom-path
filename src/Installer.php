<?php declare(strict_types=1);

namespace Tolkam\Composer\CustomPath;

use Composer\Composer;
use Composer\Installer\LibraryInstaller;
use Composer\Package\PackageInterface;
use InvalidArgumentException;

class Installer extends LibraryInstaller
{
    private const ROOT_TYPES_KEY        = 'custom-types';
    private const ROOT_PATHS_KEY        = 'custom-paths';
    private const CHILD_CUSTOM_NAME_KEY = 'custom-name';
    
    /**
     * @var array|null
     */
    protected ?array $customTypes = null;
    
    /**
     * @var array|null
     */
    protected ?array $customPaths = null;
    
    /**
     * {@inheritDoc}
     */
    public function __construct(...$args)
    {
        parent::__construct(...$args);
        
        [$this->customTypes, $this->customPaths] = $this->extractConfiguration($this->composer);
    }
    
    /**
     * {@inheritDoc}
     */
    public function supports($packageType): bool
    {
        return in_array($packageType, $this->customTypes ?? []);
    }
    
    /**
     * {@inheritDoc}
     */
    public function getInstallPath(PackageInterface $package): string
    {
        // we have custom types but no paths found
        if (empty($this->customPaths)) {
            throw new InvalidArgumentException(sprintf(
                '"%s" must be defined and not empty',
                self::ROOT_PATHS_KEY
            ));
        }
        
        $prettyName = $package->getPrettyName();
        $type = $package->getType();
        
        // get placeholders
        if (str_contains($prettyName, '/')) {
            [$vendor, $name] = explode('/', $prettyName);
        }
        else {
            $vendor = '';
            $name = $prettyName;
        }
        
        $placeholders = compact('name', 'vendor', 'type');
        
        // custom child package name
        $extra = $package->getExtra();
        if ($customName = $extra[self::CHILD_CUSTOM_NAME_KEY] ?? null) {
            $placeholders['name'] = (string) $customName;
        }
        
        $path = $this->resolveCustomPath(
            $this->customPaths,
            $prettyName,
            $type,
            $vendor
        );
        
        // no path resolved - fallback to default
        if ($path === false) {
            return parent::getInstallPath($package);
        }
        
        return $this->replacePathPlaceholders($path, $placeholders);
    }
    
    /**
     * Extracts plugin configuration
     *
     * @param Composer $composer
     *
     * @return array
     */
    protected function extractConfiguration(Composer $composer): array
    {
        $extra = $composer->getPackage()->getExtra();
        $types = $extra[self::ROOT_TYPES_KEY] ?? null;
        $paths = $extra[self::ROOT_PATHS_KEY] ?? null;
        
        if (!is_null($types) && !is_array($types)) {
            throw new InvalidArgumentException(sprintf(
                'Extra\'s "%s" value must be array, %s given',
                self::ROOT_TYPES_KEY,
                gettype($types)
            ));
        }
        
        if (!is_null($paths) && !is_array($paths)) {
            throw new InvalidArgumentException(sprintf(
                'Extra\'s "%s" value must be array, %s given',
                self::ROOT_PATHS_KEY,
                gettype($paths)
            ));
        }
        
        return [$types, $paths];
    }
    
    /**
     * Search through a passed paths array for a custom install path
     *
     * @param array  $paths
     * @param string $name
     * @param string $type
     * @param string $vendor
     *
     * @return bool|string
     */
    protected function resolveCustomPath(
        array $paths,
        string $name,
        string $type,
        string $vendor = ''
    ): bool|string {
        foreach ($paths as $path => $names) {
            if (
                in_array($name, $names)
                || in_array('type:' . $type, $names)
                || in_array('vendor:' . $vendor, $names)
            ) {
                return $path;
            }
        }
        
        return false;
    }
    
    /**
     * Replaces placeholders with values
     *
     * @param string $path
     * @param array  $placeholders
     *
     * @return string
     */
    protected function replacePathPlaceholders(string $path, array $placeholders = []): string
    {
        if (str_contains($path, '{')) {
            
            extract($placeholders);
            preg_match_all('~\{\$([A-Za-z0-9_]*)\}~i', $path, $matches);
            
            if (!empty($matches[1])) {
                foreach ($matches[1] as $var) {
                    $path = str_replace('{$' . $var . '}', $$var, $path);
                }
            }
        }
        
        return $path;
    }
}
