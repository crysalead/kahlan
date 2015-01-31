<?php
namespace kahlan\analysis;

use Exception;
use ReflectionClass;
use string\String;

/**
 * The `Debugger` class provides basic facilities for generating and rendering meta-data about the
 * state of an application in its current context.
 */
class Debugger
{
    /**
     * Store the autoloader
     */
    public static $_loader = null;

    public static $_classes = [];


    /**
     * Config method
     *
     * @param array $options Options config array.
     */
    public function config($options = [])
    {
        $defaults = ['classes' => []];
        $options += $defaults;
        static::$_classes += $options['classes'];
    }

    /**
     * Gets a backtrace string based on the supplied options.
     *
     * @param  array $options Format for outputting stack trace. Available options are:
     *                        - `'start'`: The depth to start with.
     *                        - `'depth'`: The maximum depth of the trace.
     *                        - `'message'`: Either `null` for default message or a string.
     *                        - `'trace'`: A trace to use instead of generating one.
     * @return array          The formatted backtrace.
     */
    public static function trace($options = [])
    {
        $defaults = ['trace' => []];
        $options += $defaults;
        $back = [];
        $backtrace = static::backtrace($options);

        foreach ($backtrace as $trace) {
            $back[] =  static::_traceToString($trace);
        }
        return join("\n", $back);
    }

    /**
     * Gets a string representation of a trace.
     *
     * @param  array  $trace A trace array.
     * @return string The string representation of a trace.
     */
    protected static function _traceToString($trace)
    {
        $loader = static::loader();

        if (!empty($trace['class'])) {
            $trace['function'] = $trace['class'] . '::' . $trace['function'] . '()';
        } else {
            $line = static::_line($trace);
            $trace['line'] = $line !== $trace['line'] ? $line . ' to ' . $trace['line'] : $trace['line'];
        }

        if (preg_match("/eval\(\)'d code/", $trace['file']) && $trace['class'] && $loader) {
            $trace['file'] = $loader->findFile($trace['class']);
        }

        // This code will never fire, because of backtrace closure checking
        // Please check this!
        // if (strpos($trace['function'], '{closure}') !== false) {
        //     $trace['function'] = "{closure}";
        // }
        return $trace['function'] .' - ' . $trace['file'] . ', line ' . $trace['line'];
    }

    /**
     * Return a backtrace array based on the supplied options.
     *
     * @param array $options Format for outputting stack trace. Available options are:
     *                       - `'start'`: The depth to start with.
     *                       - `'depth'`: The maximum depth of the trace.
     *                       - `'message'`: Either `null` for default message or a string.
     *                       - `'trace'`: A trace to use instead of generating one.
     * @return array         The backtrace array
     */
    public static function backtrace($options = [])
    {
        $defaults = [
            'trace' => [],
            'start' => 0,
            'depth' => 0
        ];
        $options += $defaults;

        $backtrace = static::normalize($options['trace'] ?: debug_backtrace());

        $traceDefaults = [
            'line' => '?',
            'file' => '[internal]',
            'class' => null,
            'function' => '[NA]'
        ];

        $back = [];
        $ignoreFunctions = ['call_user_func_array', 'trigger_error'];

        foreach($backtrace as $i => $trace) {
            $trace += $traceDefaults;
            if (strpos($trace['function'], '{closure}') !== false || in_array($trace['function'], $ignoreFunctions)) {
                continue;
            }
            $back[] = $trace;
        }

        $count = count($back);
        return array_splice($back, $options['start'], $options['depth'] ?: $count);
    }

    public static function normalize($backtrace)
    {
        if (!$backtrace instanceof Exception) {
            return $backtrace;
        }
        return array_merge([[
            'function' => '[NA]',
            'file' => $backtrace->getFile(),
            'line' => $backtrace->getLine(),
            'args' => []
        ]], $backtrace->getTrace());
    }

    public static function message($backtrace)
    {
        if ($backtrace instanceof Exception) {
            $name = get_class($backtrace);
            $code = $backtrace->getCode();
            return "`{$name}` Code({$code}): " . $backtrace->getMessage();
        } elseif (isset($backtrace['message'])) {
            $code = isset($backtrace['code']) ? $backtrace['code'] : 0;
            $name = static::errorType($code);
            return "`{$name}` Code({$code}): " . $backtrace['message'];
        }
    }

    /**
     * Locates original location of call from a trace.
     *
     * @param  array $trace A backtrace array.
     * @return mixed        Returns the line number where the method called is defined.
     */
    protected static function _line($trace)
    {
        $path = $trace['file'];
        $callLine = $trace['line'];
        if (!file_exists($path)) {
            return;
        }
        $file = file_get_contents($path);
        if (($i = static::_findPos($file, $callLine)) === null) {
            return;
        }
        $line = $callLine;

        $brackets = 0;
        while ($i >= 0) {
            if ($file[$i] === ')') {
                $brackets--;
            } elseif ($file[$i] === '(') {
                $brackets++;
            } elseif ($file[$i] === "\n") {
                $line--;
            }
            if ($brackets > 0) {
                return $line;
            }
            $i--;
        }
    }

    /**
     * Return the first character position of a specific line in a file.
     *
     * @param  string  $file     A file content.
     * @param  integer $callLine The number of line to find.
     * @return mixed             Returns the character position or null if not found.
     */
    protected static function _findPos($file, $callLine)
    {
        $len = strlen($file);
        $line = 1;
        $i = 0;
        while ($i < $len) {
            if ($file[$i] === "\n") {
                $line++;
            }
            if ($line === $callLine) {
                return $i;
            }
            $i++;
        }
    }

    /**
     * Get/set a compatible composer autoloader.
     *
     * @param  object|null $loader The autoloader to set or `null` to get the default one.
     * @return object      The autoloader.
     */
    public static function loader($loader = null)
    {
        if ($loader) {
            return static::$_loader = $loader;
        }
        if (static::$_loader !== null) {
            return static::$_loader;
        }
        $loaders = spl_autoload_functions();
        foreach ($loaders as $key => $loader) {
            if (is_array($loader) && method_exists($loader[0], 'findFile')) {
                return static::$_loader = $loader[0];
            }
        }
    }

    public static function errorType($value)
    {
        switch($value)
        {
            case E_ERROR:
                return 'E_ERROR';
            case E_WARNING:
                return 'E_WARNING';
            case E_PARSE:
                return 'E_PARSE';
            case E_NOTICE:
                return 'E_NOTICE';
            case E_CORE_ERROR:
                return 'E_CORE_ERROR';
            case E_CORE_WARNING:
                return 'E_CORE_WARNING';
            case E_COMPILE_ERROR:
                return 'E_COMPILE_ERROR';
            case E_COMPILE_WARNING:
                return 'E_COMPILE_WARNING';
            case E_USER_ERROR:
                return 'E_USER_ERROR';
            case E_USER_WARNING:
                return 'E_USER_WARNING';
            case E_USER_NOTICE:
                return 'E_USER_NOTICE';
            case E_STRICT:
                return 'E_STRICT';
            case E_RECOVERABLE_ERROR:
                return 'E_RECOVERABLE_ERROR';
            case E_DEPRECATED:
                return 'E_DEPRECATED';
            case E_USER_DEPRECATED:
                return 'E_USER_DEPRECATED';
        }
        return '<INVALID>';
    }
}
