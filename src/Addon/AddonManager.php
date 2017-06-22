<?php

/**
 * @license Proprietary
 * @copyright 2009-2016 Vanilla Forums Inc.
 */

namespace Vanilla\QueueWorker\Addon;

use Vanilla\QueueWorker\Log\LoggerBoilerTrait;

use Kaecyra\AppCommon\AbstractConfig;
use Kaecyra\AppCommon\Event\EventAwareInterface;
use Kaecyra\AppCommon\Event\EventAwareTrait;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LogLevel;

use Psr\Container\ContainerInterface;
use Garden\Container\Reference;

/**
 * Queue Worker Addon manager
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package queue-worker
 * @since 1.0
 */
class AddonManager implements LoggerAwareInterface, EventAwareInterface {

    use LoggerBoilerTrait;
    use LoggerAwareTrait;
    use EventAwareTrait;

    /**
     * Dependency Injection Container
     * @var ContainerInterface
     */
    protected $container;

    /**
     * List of source folders
     * @var array
     */
    protected $sources;

    /**
     * Addons information
     * @var array<Addon>
     */
    protected $addons;

    /**
     * Addons list
     * @var array<Addon>
     */
    protected $enabled;

    /**
     * Addon instances
     * @var array<AddonInterface>
     */
    protected $instances;

    /**
     * Autoload list
     * @var array
     */
    protected $autoload;

    public function __construct(ContainerInterface $container, array $scanDirs) {
        $this->container = $container;
        $this->sources = [];
        $this->addons = [];
        $this->enabled = [];

        // Prepare autoloader caching
        $this->autoload = [];

        // Load things
        spl_autoload_register([$this, 'autoload']);

        // Add scanned dirs
        foreach ($scanDirs as $dir) {
            $this->addSource($dir);
        }
    }

    /**
     * Autoload a class
     *
     * @param string $class
     */
    public function autoload($class) {
        $classKey = strtolower($class);

        if (isset($this->autoload[$classKey])) {
            list($_, $path) = $this->autoload[$classKey];
            include_once $path;
        }
    }

    /**
     * Add addon scan source
     *
     * @param string $sourceDir
     * @return AddonManager
     */
    public function addSource($sourceDir) {
        if (is_dir($sourceDir)) {
            $this->sources[] = $sourceDir;
        }

        return $this;
    }

    /**
     * Scan all source folders
     *
     * @return void
     */
    public function scanSourceFolders() {
        $this->addons = [];
        foreach ($this->sources as $sourceDir) {
            $this->scanSource($sourceDir);
        }
    }

    /**
     * Scan source dir for addons
     *
     * @param string $sourceDir
     */
    public function scanSource($sourceDir) {
        $this->log(LogLevel::INFO, "Scanning addon source: {$sourceDir}");

        $addonsCandidates = scandir($sourceDir);
        foreach ($addonsCandidates as $addonCandidate) {
            // Ignore hidden files and directory traversal links
            $char = substr($addonCandidate, 0, 1);
            if ($char == '.') {
                continue;
            }

            // Search for addon definition file
            $definitionFile = paths($sourceDir, $addonCandidate, "addon.json");
            if (!file_exists($definitionFile)) {
                continue;
            }

            try {
                $addon = new Addon(realpath($definitionFile));
            } catch (\Exception $ex) {
                $this->log(LogLevel::WARNING, " failed loading addon '{definition}': {message}", [
                    'definition' => $definitionFile,
                    'message' => $ex->getMessage()
                ]);
                continue;
            }

            // Addon loaded
            $this->log(LogLevel::INFO, " found addon: {name} v{version} (provides {classes} classes from {path})", [
                'name' => $addon->getInfo('name'),
                'version' => $addon->getInfo('version'),
                'path' => $addon->getPath(),
                'classes' => count($addon->getClasses())
            ]);

            $this->addons[$addon->getInfo('name')] = $addon;
        }
    }

    /**
     * Start addons
     *
     * @param array $addons list of addon keys
     */
    public function startAddons($addons) {

        // Scan source folders
        $this->scanSourceFolders();

        $this->log(LogLevel::NOTICE, "Starting addons");

        // Include and instantiate active addons
        $this->enabled = [];

        foreach ($addons as $addonName => $requiredAddonState) {

            // Ignore 'off' addons
            if ($requiredAddonState != 'on') {
                continue;
            }

            $this->startAddon($addonName);
        }
    }

    /**
     * Start an addon
     *
     * @param string $addonName addon name
     * @return boolean load success status
     */
    public function startAddon($addonName, $level = 0) {

        $nest = str_repeat(' ', $level);

        $this->log(LogLevel::NOTICE, "{$nest} start addon: {addon}", [
            'addon' => $addonName
        ]);

        // Short circuit if already started
        if ($this->isStarted($addonName)) {
            $this->log(LogLevel::INFO, "{$nest}  already started");
            return true;
        }

        // Check if we've got an addon called this
        $addon = $this->getAddon($addonName);
        if (!$addon) {
            $this->log(LogLevel::WARNING, "{$nest}  unknown addon, not loaded");
            return false;
        }

        // Check requirements
        $requiredAddons = $addon->getInfo('requires') ?? [];

        // If we have requirements, try to load them
        if (count($requiredAddons)) {
            $txtRequirements = implode(',', $requiredAddons);
            $this->log(LogLevel::INFO, "{$nest}  addon has requirements: {requirements}", [
                'requirements' => $txtRequirements
            ]);

            // Check if the requirements are all available
            $missing = [];
            foreach ($requiredAddons as $requiredAddon) {
                if (!$this->isAvailable($requiredAddon)) {
                    $missing[] = $requiredAddon;
                }
            }
            if (count($missing)) {
                $txtMissing = implode(',', $missing);
                $this->log(LogLevel::WARNING, "{$nest}  missing requirements: {missing}", [
                    'missing' => $txtMissing
                ]);
                return false;
            }

            // Keep track of which requirements we've loaded so we can unload if things went wrong
            $startedRequirements = [];

            // Loop over requirements and load each one in turn
            $loadedAllRequirements = true;
            foreach ($requiredAddons as $requiredAddon) {
                if (!$this->isStarted($requiredAddon)) {

                    // Try to load addon if available
                    $loadedRequirement = false;
                    if ($this->isAvailable($requiredAddon)) {
                        $loadedRequirement = $this->startAddon($requiredAddon, $level+1);
                    }

                    $loadedAllRequirements &= $loadedRequirement;

                    if (!$loadedRequirement) {
                        $this->log(LogLevel::WARNING, "{$nest}  failed starting required addon: {addon}", [
                            'addon' => $requiredAddon
                        ]);
                        return false;
                    }

                    $startedRequirements[] = $requiredAddon;
                }
            }
        }

        // Cache autoload info
        $this->autoload = array_merge($this->autoload, $addon->getClasses());

        $addonClass = $addon->getAddonClass();
        if ($addonClass) {

            $this->log(LogLevel::INFO, "{$nest}  creating addon instance: {$addonClass}");

            // Get instance
            $instance = $this->container->getArgs($addonClass, [
                new Reference([AbstractConfig::class, "addons.addon.{$addonName}"])
            ]);
            $instance->setAddon($addon);
            $this->instances[$addonName] = $instance;
            $instance->start();

        }

        $this->enabled[$addonName] = true;

        return true;
    }

    /**
     * Check if a addon is available for loading
     *
     * @param string $addonName
     * @return boolean
     */
    public function isAvailable($addonName) {
        return ($this->addons[$addonName] ?? false) instanceof Addon;
    }

    /**
     * Check if a addon is loaded
     *
     * @param string $addonName
     * @return boolean
     */
    public function isStarted($addonName) {
        return ($this->enabled[$addonName] ?? false) === true;
    }

    /**
     * List addons
     *
     * @param bool $active optionally list only active addons
     * @return array
     */
    public function getAddons($active = false) {
        if ($active) {
            return array_keys($this->enabled);
        }
        return array_keys($this->addons);
    }

    /**
     * List active addons
     * @return array
     */
    public function getActiveAddons() {
        return $this->getAddons(true);
    }

    /**
     * Get addon marker
     *
     * @param string $addonName
     * @throws \Exception
     * @return Addon
     */
    public function getAddon($addonName) {
        if (!$this->isAvailable($addonName)) {
            throw new \Exception("Tried to get marker of unknown addon '{$addonName}'");
        }
        return $this->addons[$addonName];
    }

    /**
     * Get addon instance
     *
     * @param string $addonName
     * @throws \Exception
     * @return AddonInterface
     */
    public function getInstance($addonName) {
        if (!$this->isStarted($addonName)) {
            throw new \Exception("Tried to get instance of inactive addon '{$addonName}'");
        }

        if (!array_key_exists($addonName, $this->instances) || !($this->instances[$addonName] instanceof AddonInterface)) {
            throw new \Exception("Addon '{$addonName}' has no instance");
        }

        return $this->instances[$addonName];
    }

}