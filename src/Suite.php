<?php
namespace kahlan;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Exception;
use InvalidArgumentException;
use set\Set;
use kahlan\PhpErrorException;
use kahlan\analysis\Debugger;

class Suite extends Scope
{
    /**
     * Store all hashed references.
     *
     * @var array
     */
    protected static $_registered = [];

    /**
     * The return status value (`0` for success).
     *
     * @var integer
     */
    protected $_status = null;

    /**
     * Matcher instance for the test suite
     *
     * @var array
     */
    protected $_matcher = null;

    /**
     * The childs array.
     *
     * @var array
     */
    protected $_childs = [];

    /**
     * Suite statistics.
     *
     * @var array
     */
    protected $_stats = null;

    /**
     * The each callbacks.
     *
     * @var array
     */
    protected $_callbacks = [
        'before' => [],
        'after' => [],
        'beforeEach' => [],
        'afterEach' => []
    ];

    /**
     * Array of fully-namespaced class name to clear on each `it()`.
     *
     * @var array
     */
    protected $_autoclear = [];

    /**
     * Saved backtrace of focused specs.
     *
     * @var array
     */
    protected $_focuses = [];

    /**
     * Set the number of fails allowed before aborting. `0` mean no fast fail.
     *
     * @see ::failfast()
     * @var integer
     */
    protected $_ff = 0;

    /**
     * The Constructor.
     *
     * @param array $options The Suite config array. Options are:
     *                       -`'closure'` _Closure_: the closure of the test.
     *                       -`'name'`    _string_ : the type of the suite.
     *                       -`'scope'`   _string_ : supported scope are `'normal'` & `'focus'`.
     *                       -`'matcher'` _object_ : the matcher instance.
     */
    public function __construct($options = [])
    {
        $defaults = [
            'closure' => null,
            'name'    => 'describe',
            'scope'   => 'normal',
            'matcher' => null
        ];
        $options += $defaults;
        parent::__construct($options);

        extract($options);

        if ($this->_root === $this) {
            $this->_matcher = $matcher;
            return;
        }
        $closure = $this->_bind($closure, $name);
        $this->_closure = $closure;
        if ($scope === 'focus') {
            $this->_emitFocus();
        }
    }

    /**
     * Adds a group/class related spec.
     *
     * @param  string  $message Description message.
     * @param  Closure $closure A test case closure.
     * @return $this
     */
    public function describe($message, $closure, $scope = 'normal')
    {
        $parent = $this;
        $name = 'describe';
        $suite = new Suite(compact('message', 'closure', 'parent', 'name', 'scope'));
        return $this->_childs[] = $suite;
    }

    /**
     * Adds a context related spec.
     *
     * @param  string  $message Description message.
     * @param  Closure $closure A test case closure.
     * @return $this
     */
    public function context($message, $closure, $scope = 'normal')
    {
        $parent = $this;
        $name = 'context';
        $suite = new Suite(compact('message', 'closure', 'parent', 'name', 'scope'));
        return $this->_childs[] = $suite;
    }

    /**
     * Adds a spec.
     *
     * @param  string|Closure $message Description message or a test closure.
     * @param  Closure|null   $closure A test case closure or `null`.
     * @param  string         $scope   The scope.
     * @return $this
     */
    public function it($message, $closure = null, $scope = 'normal')
    {
        static $inc = 1;
        if ($closure === null) {
            $closure = $message;
            $message = "spec #" . $inc++;
        }
        $parent = $this;
        $root = $this->_root;
        $matcher = $this->_root->_matcher;
        $spec = new Spec(compact('message', 'closure', 'parent', 'root', 'scope', 'matcher'));
        $this->_childs[] = $spec;
        return $this;
    }

    /**
     * Comments out a group/class related spec.
     *
     * @param  string  $message Description message.
     * @param  Closure $closure A test case closure.
     * @return $this
     */
    public function xdescribe($message, $closure)
    {
    }

    /**
     * Comments out a context related spec.
     *
     * @param  string  $message Description message.
     * @param  Closure $closure A test case closure.
     * @return $this
     */
    public function xcontext($message, $closure)
    {
    }

    /**
     * Comments out a spec.
     *
     * @param  string|Closure $message Description message or a test closure.
     * @param  Closure|null   $closure A test case closure or `null`.
     * @return $this
     */
    public function xit($message, $closure = null)
    {
    }

    /**
     * Adds an focused group/class related spec.
     *
     * @param  string  $message Description message.
     * @param  Closure $closure A test case closure.
     * @return $this
     */
    public function fdescribe($message, $closure)
    {
        return $this->describe($message, $closure, 'focus');
    }

    /**
     * Adds an focused context related spec.
     *
     * @param  string  $message Description message.
     * @param  Closure $closure A test case closure.
     * @return $this
     */
    public function fcontext($message, $closure)
    {
        return $this->context($message, $closure, 'focus');
    }

    /**
     * Adds an focused spec.
     *
     * @param  string|Closure $message Description message or a test closure.
     * @param  Closure|null   $closure A test case closure or `null`.
     * @return $this
     */
    public function fit($message, $closure = null)
    {
        return $this->it($message, $closure, 'focus');
    }

    /**
     * Executed before tests.
     *
     * @param  Closure $closure A closure
     * @return $this
     */
    public function before($closure)
    {
        $this->_bind($closure, 'before');
        $this->_callbacks['before'][] = $closure;
        return $this;
    }

    /**
     * Executed after tests.
     *
     * @param Closure $closure A closure
     */
    public function after($closure)
    {
        $this->_bind($closure, 'after');
        $this->_callbacks['after'][] = $closure;
        return $this;
    }

    /**
     * Executed before each tests.
     *
     * @param  Closure $closure A closure
     * @return $this
     */
    public function beforeEach($closure)
    {
        $this->_bind($closure, 'beforeEach');
        $this->_callbacks['beforeEach'][] = $closure;
        return $this;
    }

    /**
     * Executed after each tests.
     *
     * @param Closure $closure A closure
     */
    public function afterEach($closure)
    {
        $this->_bind($closure, 'afterEach');
        $this->_callbacks['afterEach'][] = $closure;
        return $this;
    }

    /**
     * Suite run.
     *
     * @return array Process options.
     */
    protected function process($options = [])
    {
        if ($this->_root->focused() && !$this->focused()) {
            return;
        }
        static::$_instances[] = $this;
        $this->_errorHandler(true, $options);

        try {
            $this->_suiteStart();
            foreach($this->_childs as $child) {
                if ($this->failfast()) {
                    break;
                }
                $child->process();
            }
            $this->_suiteEnd();
        } catch (Exception $exception) {
            $this->_exception($exception);
            try {
                $this->_suiteEnd();
            } catch (Exception $exception) {}
        }

        $this->_errorHandler(false);
        array_pop(static::$_instances);
    }

    /**
     * Suite start helper.
     */
    protected function _suiteStart()
    {
        if ($this->message()) {
            $this->emitReport('suiteStart', $this->report());
        }
        $this->runCallbacks('before', false);
    }

    /**
     * Suite end helper.
     */
    protected function _suiteEnd()
    {
        $this->runCallbacks('after', false);
        if ($this->message()) {
            $this->emitReport('suiteEnd', $this->report());
        }
    }

    /**
     * Returns `true` if the suite reach the number of allowed failure by the fail-fast parameter.
     *
     * @return boolean;
     */
    public function failfast()
    {
        return $this->_root->_ff && $this->_root->_failure >= $this->_root->_ff;
    }

    /**
     * Runs a callback.
     *
     * @param string $name The name of the callback (i.e `'beforeEach'` or `'afterEach'`).
     */
    public function runCallbacks($name, $recursive = true)
    {
        $instances = $recursive ? $this->_parents(true) : [$this];
        foreach ($instances as $instance) {
            foreach($instance->_callbacks[$name] as $closure) {
                $closure($this);
            }
        }
    }

    /**
     * Overrides the default error handler
     *
     * @param boolean $enable If `true` override the default error handler,
     *                if `false` restore the default handler.
     * @param array   $options An options array. Available options are:
     *                - 'handler': An error handler closure.
     */
    protected function _errorHandler($enable, $options = [])
    {
        $defaults = ['handler' => null];
        $options += $defaults;
        if (!$enable) {
            return restore_error_handler();
        }
        $handler = function($code, $message, $file, $line = 0, $args = []) {
            $trace = debug_backtrace();
            $trace = array_slice($trace, 1, count($trace));
            $message = "`" . Debugger::errorType($code) . "` {$message}";
            $code = 0;
            $exception = compact('code', 'message', 'file', 'line', 'trace');
            throw new PhpErrorException($exception);
        };
        $options['handler'] = $options['handler'] ?: $handler;
        set_error_handler($options['handler']);
    }

    /**
     * Runs all specs.
     *
     * @param  array $options Run options.
     * @return array The result array.
     */
    public function run($options = [])
    {
        $defaults = [
            'reporters'      => null,
            'autoclear'      => [],
            'ff'             => 0,
            'clearCache'     => false,
            'cachePath'      => false            
        ];
        $options += $defaults;

        if ($this->_locked) {
            throw new Exception('Method not allowed in this context.');
        }

        if ($options['clearCache'] && $options['cachePath']) {
            if (!is_dir($options['cachePath'])) {
                throw new Exception("Cache path {$options['cachePath']} is not a directory");
            }

            $dir   = new RecursiveDirectoryIterator($options['cachePath'], RecursiveDirectoryIterator::SKIP_DOTS);
            $files = new RecursiveIteratorIterator($dir, RecursiveIteratorIterator::CHILD_FIRST);

            foreach($files as $file) {
                $path = $file->getRealPath();
                if ($file->isDir()) {
                    rmdir($path);
                } else {
                    unlink($path);
                }
            }

            rmdir($options['cachePath']);
        }

        $this->_locked = true;

        $this->_reporters = $options['reporters'];
        $this->_autoclear = (array) $options['autoclear'];
        $this->_ff = $options['ff'];

        $this->emitReport('start', ['total' => $this->enabled()]);
        $this->process();
        $this->emitReport('end', [
            'specs'   => $this->_results,
            'focuses' => $this->_focuses
        ]);

        $this->_locked = false;

        return $this->passed();
    }

    /**
     * Gets number of total specs.
     *
     * @return integer
     */
    public function total()
    {
        if ($this->_stats === null) {
            $this->stats();
        }
        return $this->_stats['focused'] + $this->_stats['normal'];
    }

    /**
     * Gets number of enabled specs.
     *
     * @return integer
     */
    public function enabled()
    {
        if ($this->_stats === null) {
            $this->stats();
        }
        return $this->focused() ? $this->_stats['focused'] : $this->_stats['normal'];
    }

    /**
     * Triggers the `stop` event.
     */
    public function stop()
    {
        $this->emitReport('stop', [
            'specs'   => $this->_results,
            'focuses' => $this->_focuses
        ]);
    }

    /**
     * Builds the suite.
     *
     * @return array The suite stats.
     */
    protected function stats()
    {
        static::$_instances[] = $this;
        if($closure = $this->_closure) {
            $closure($this);
        }

        $normal = 0;
        $focused = 0;
        foreach($this->childs() as $child) {
            if ($child instanceof Suite) {
                $result = $child->stats();
                if ($child->focused() && !$result['focused']) {
                    $focused += $result['normal'];
                    $child->_broadcastFocus();
                } else {
                    $focused += $result['focused'];
                    $normal += $result['normal'];
                }
            } else {
                $child->focused() ? $focused++ : $normal++;
            }
        }
        array_pop(static::$_instances);
        return $this->_stats = compact('normal', 'focused');
    }

    /**
     * Gets exit status code according passed results.
     *
     * @param  integer $status If set force a specific status to be retruned.
     * @return boolean         Returns `0` if no error occurred, `-1` otherwise.
     */
    public function status($status = null)
    {
        if ($status !== null) {
            $this->_status = $status;
        }

        if ($this->_status !== null) {
            return $this->_status;
        }

        if ($this->focused()) {
            return -1;
        }
        return $this->passed() ? 0 : -1;
    }

    /**
     * Gets childs.
     *
     * @return array The array of childs instances.
     */
    public function childs()
    {
        return $this->_childs;
    }

    /**
     * Gets callbacks.
     *
     * @param  string $type The type of callbacks to get.
     * @return array        The array callbacks instances.
     */
    public function callbacks($type)
    {
        return isset($this->_callbacks[$type]) ? $this->_callbacks[$type] : [];
    }

    /**
     * Gets references of focused specs.
     *
     * @return array
     */
    public function focuses()
    {
        return $this->_focuses;
    }

    /**
     * Autoclears plugins.
     */
    public function autoclear()
    {
        foreach ($this->_root->_autoclear as $plugin) {
            if (method_exists($plugin, 'clear')) {
                is_object($plugin) ? $plugin->clear() : $plugin::clear();
            }
        }
        static::clear();
    }

    /**
     * Applies focus downward to the leaf.
     */
    protected function _broadcastFocus()
    {
        foreach ($this->_childs as $child) {
            $child->focus();
            if ($child instanceof Suite) {
                $child->_broadcastFocus();
            }
        }
    }

    /**
     * Generates a hash from an instance or a string.
     *
     * @param  mixed $reference An instance or a fully namespaced class name.
     * @return string           A string hash.
     * @throws InvalidArgumentException
     */
    public static function hash($reference)
    {
        if (is_object($reference)) {
            return spl_object_hash($reference);
        }
        if (is_string($reference)) {
            return $reference;
        }
        throw new InvalidArgumentException("Error, the passed argument is not hashable.");
    }

    /**
     * Registers a hash. [Mainly used for optimization]
     *
     * @param  mixed  $hash A hash to register.
     */
    public static function register($hash)
    {
        static::$_registered[$hash] = true;
    }

    /**
     * Gets registered hashes. [Mainly used for optimizations]
     *
     * @param  string  $hash The hash to look up. If `null` return all registered hashes.
     */
    public static function registered($hash = null)
    {
        if(!$hash) {
            return static::$_registered;
        }
        return isset(static::$_registered[$hash]);
    }

    /**
     * Clears the registered hash.
     */
    public static function clear()
    {
        static::$_registered = [];
    }

}
