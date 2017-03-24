<?php

/**
 * @license Proprietary
 * @copyright 2009-2016 Vanilla Forums Inc.
 */

namespace Vanilla\QueueWorker\Addon;

/**
 * Addon marker object
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package productqueue
 * @since 1.0
 */
class Addon {

    /**
     * Addon folder
     * @var string
     */
    protected $dir;

    /**
     * Addon info
     * @var array
     */
    protected $info;

    /**
     * Addon autoloadable classes
     * @var array
     */
    protected $classes;

    /**
     * Special classes
     * @var array
     */
    protected $special;

    /**
     * Create addon marker instance
     *
     * @param string $definitionFile
     */
    public function __construct($definitionFile) {
        $this->dir = dirname($definitionFile);
        $this->special = [];

        // Read addon info
        $this->info = $this->scanAddonDefinition($definitionFile);

        // Scan classes
        $this->classes = $this->scanClasses($this->getPath());
    }

    /**
     * Scan an addon definition file
     *
     * @param string $definitionFile path to addon definition file
     * @return boolean
     */
    protected function scanAddonDefinition($definitionFile) {
        if (!file_exists($definitionFile)) {
            throw new \Exception("missing definition file");
        }

        // Prepare addon info array
        $definition = [
            'path'      => dirname($definitionFile),
            'requires'  => []
        ];

        // Read from definition file
        $definitionData = json_decode(file_get_contents($definitionFile), true);
        if (!$definitionData) {
            throw new \Exception("failed to parse definition file");
        }
        $definition = array_merge_recursive($definition, $definitionData);

        // Check that we got everything we need
        $requiredKeys = ['name', 'description', 'author', 'version'];
        $requiredMatch = array_fill_keys($requiredKeys, null);

        $requirementCheck = array_intersect_key($definition, $requiredMatch);
        if (count($requirementCheck) < count($requiredKeys)) {
            $missing = array_diff_key($requiredMatch, $definition);
            throw new \Exception("missing definition fields (".implode(',',array_keys($missing)).")");
        }

        return $definition;
    }

    /**
     * Scan a directory for addon classes
     *
     * @param string $dir path to scan
     * @param int $depth how deep to scan
     * @return array an array of subpaths.
     */
    protected function scanClasses($dir, $depth = 2) {

        // Don't recurse if we've hit out depth limit
        if ($depth < 0) {
            return [];
        }

        $paths = scandir($dir);

        $classes = [];
        foreach ($paths as $path) {
            if (in_array($path, ['.','..'])) {
                continue;
            }

            $full = paths($dir,$path);

            if (is_dir($full)) {
                if ($depth > 0) {
                    $classes = array_merge($classes, $this->scanClasses($full, $depth-1));
                }
            } else {

                // Only scan PHP files
                if (fnmatch('*.php', $path)) {

                    $declarations = $this->scanFile($full);

                    // Iterate over all namespaces
                    foreach ($declarations as $namespaceRow) {
                        if (isset($namespaceRow['namespace']) && $namespaceRow) {
                            $namespace = rtrim($namespaceRow['namespace'], '\\').'\\';
                            $namespaceClasses = $namespaceRow['classes'];
                        } else {
                            $namespace = '';
                            $namespaceClasses = $namespaceRow;
                        }

                        // Iterate over all classes within NS
                        foreach ($namespaceClasses as $classRow) {
                            $className = $namespace.$classRow['name'];
                            $classes[strtolower($className)] = [$className, $full];

                            // Special case for main addon file
                            if ($classRow['type'] == 'CLASS') {
                                if (preg_match('`^[\w\d]+Addon$`', $classRow['name'])) {
                                    $this->special['addon'] = $className;
                                }
                            }
                        }
                    }
                }

            }
        }

        return $classes;
    }

    /**
     * Inspect a file and return the classes and namespaces that it defines
     *
     * @param string $path Path to file.
     * @return array Returns an empty array if no classes are found or an array with namespaces and
     * classes found in the file.
     * @see http://stackoverflow.com/a/11114724/1984219
     */
    private function scanFile($path) {
        $classes = $nsPos = $final = [];
        $foundNamespace = false;
        $ii = 0;

        if (!file_exists($path)) {
            return [];
        }

        $er = error_reporting();
        error_reporting(E_ALL ^ E_NOTICE);

        $php_code = file_get_contents($path);
        $tokens = token_get_all($php_code);
//        $count = count($tokens);

        foreach ($tokens as $i => $token) { //} ($i = 0; $i < $count; $i++) {
            if (!$foundNamespace && $token[0] == T_NAMESPACE) {
                $nsPos[$ii]['start'] = $i;
                $foundNamespace = true;
            } elseif ($foundNamespace && ($token == ';' || $token == '{')) {
                $nsPos[$ii]['end'] = $i;
                $ii++;
                $foundNamespace = false;
            } elseif ($i - 2 >= 0 && $tokens[$i - 2][0] == T_CLASS && $tokens[$i - 1][0] == T_WHITESPACE && $token[0] == T_STRING) {
                if ($i - 4 >= 0 && $tokens[$i - 4][0] == T_ABSTRACT) {
                    $classes[$ii][] = array('name' => $token[1], 'type' => 'ABSTRACT CLASS');
                } else {
                    $classes[$ii][] = array('name' => $token[1], 'type' => 'CLASS');
                }
            } elseif ($i - 2 >= 0 && $tokens[$i - 2][0] == T_INTERFACE && $tokens[$i - 1][0] == T_WHITESPACE && $token[0] == T_STRING) {
                $classes[$ii][] = array('name' => $token[1], 'type' => 'INTERFACE');
            } elseif ($i - 2 >= 0 && $tokens[$i - 2][0] == T_TRAIT && $tokens[$i - 1][0] == T_WHITESPACE && $token[0] == T_STRING) {
                $classes[$ii][] = array('name' => $token[1], 'type' => 'TRAIT');
            }
        }
        error_reporting($er);
        if (empty($classes)) {
            return [];
        }

        if (!empty($nsPos)) {
            foreach ($nsPos as $k => $p) {
                $ns = '';
                for ($i = $p['start'] + 1; $i < $p['end']; $i++) {
                    $ns .= $tokens[$i][1];
                }

                $ns = trim($ns);
                $final[$k] = array('namespace' => $ns, 'classes' => $classes[$k + 1]);
            }
            $classes = $final;
        }
        return $classes;
    }

    /**
     * Autoload this addon's classes
     *
     * @param string $class
     */
    public function autoload($class) {
        $classKey = strtolower($class);

        if (isset($this->classes[$classKey])) {
            list($_, $path) = $this->classes[$classKey];
            include_once $path;
        }
    }

    /**
     * Get value from definition array
     *
     * @param string $key optional
     * @return mixed
     */
    public function getInfo($key) {
        return $this->info[$key] ?? null;
    }

    /**
     * Get addon name
     *
     * @return string
     */
    public function getName() {
        return $this->info['name'];
    }

    /**
     * Get addon path
     *
     * @return string
     */
    public function getPath() {
        return $this->info['path'];
    }

    /**
     * Get known subordinate classes
     *
     * @return array
     */
    public function getClasses() {
        return $this->classes;
    }

    /**
     * Get addon instance class name
     *
     * @return string
     */
    public function getAddonClass() {
        return $this->special['addon'] ?? null;
    }

}
