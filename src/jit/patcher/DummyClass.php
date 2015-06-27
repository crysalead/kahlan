<?php
namespace kahlan\jit\patcher;

use kahlan\plugin\DummyClass as DummyClassPlugin;

class DummyClass {

    /**
     * Namespaces which allow auto mock on unexisting classes.
     *
     * @var array
     */
    protected $_namespaces = [];

    /**
     * The Constructor.
     *
     * @param array $config The config array. Possibles values are:
     *                      - `'namespaces'` _string_: The namespaces where dummies are allowed.
     */
    public function __construct($config = [])
    {
        $defaults           = ['namespaces' => []];
        $config            += $defaults;
        $this->_namespaces  = (array) $config['namespaces'];
    }

    /**
     * The JIT find file patcher.
     *
     * @param  object $loader The autloader instance.
     * @param  string $class  The fully-namespaced class name.
     * @param  string $file   The correponding finded file path.
     * @return string         The patched file path.
     */
    public function findFile($loader, $class, $file)
    {
        if ($file) {
            return $file;
        }
        $allowed = empty($this->_namespaces);
        foreach ($this->_namespaces as $ns) {
            if (strpos($class, $ns) === 0) {
                $allowed = true;
            }
        }
        if (!DummyClassPlugin::enabled() || !$allowed) {
            return $file;
        }
        $classpath = strtr($class, '\\', DS);
        return $loader->cache('/dummies/' . $classpath . '.php', static::generate(compact('class')));
    }

    /**
     * The JIT patcher.
     *
     * @param  object $node The node instance to patch.
     * @param  string $path The file path of the source code.
     * @return object       The patched node.
     */
    public function process($node, $path = null)
    {
        return $node;
    }

    /**
     * Creates a Dummy Class.
     *
     *
     * @param  array $config The config array. Possibles values are:
     * @param                - `'namespace'` _string_: The namespace name.
     * @param                - `'class'`     _string_: The class name.
     * @return string        The Dummy Class source code.
     */
    public static function generate($options = [])
    {
        extract($options);

        if (($pos = strrpos($class, '\\')) !== false) {
            $namespace = substr($class, 0, $pos);
            $class = substr($class, $pos + 1);
        } else {
            $namespace = '';
        }

        if ($namespace) {
            $namespace = "namespace {$namespace};\n";
        }
return "<?php\n\n" . $namespace . <<<EOT

use kahlan\IncompleteException;

class {$class} {

    public function __construct() {
        throw new IncompleteException("PHP Fatal error: Class `{$class}` not found.");
    }

    public static function __callStatic(\$name, \$params) {
        throw new IncompleteException("PHP Fatal error: Class `{$class}` not found.");
    }
}

?>
EOT;

    }

}
