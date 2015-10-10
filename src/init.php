<?php
use kahlan\Suite;
use kahlan\Spec;

if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}
error_reporting(E_ALL);

if (!defined('KAHLAN_DISABLE_FUNCTIONS') || !KAHLAN_DISABLE_FUNCTIONS) {

    function before($closure) {
        return Suite::current()->before($closure);
    }

    function after($closure) {
        return Suite::current()->after($closure);
    }

    function beforeEach($closure) {
        return Suite::current()->beforeEach($closure);
    }

    function afterEach($closure) {
        return Suite::current()->afterEach($closure);
    }

    function describe($message, $closure, $timeout = null, $scope = 'normal') {
        if (!Suite::current()) {
            $suite = box('kahlan')->get('suite.global');
            return $suite->describe($message, $closure, $timeout, $scope);
        }
        return Suite::current()->describe($message, $closure, $timeout, $scope);
    }

    function context($message, $closure, $timeout = null, $scope = 'normal') {
        return Suite::current()->context($message, $closure, $timeout, $scope);
    }

    function it($message, $closure, $timeout = null, $scope = 'normal') {
        return Suite::current()->it($message, $closure, $timeout, $scope);
    }

    function fdescribe($message, $closure, $timeout = null) {
        return describe($message, $closure, $timeout, 'focus');
    }

    function fcontext($message, $closure, $timeout = null) {
        return context($message, $closure, $timeout, 'focus');
    }

    function fit($message, $closure = null, $timeout = null) {
        return it($message, $closure, $timeout, 'focus');
    }

    function xdescribe($message, $closure) {
    }

    function xcontext($message, $closure) {
    }

    function xit($message, $closure = null) {
    }

    /**
     * Deprecated method use `fdescribe`.
     */
    function ddescribe($message, $closure) {
        return fdescribe($message, $closure);
    }

    /**
     * Deprecated method use `fcontext`.
     */
    function ccontext($message, $closure) {
        return fcontext($message, $closure);
    }

    /**
     * Deprecated method use `fit`.
     */
    function iit($message, $closure = null) {
        return fit($message, $closure);
    }

    function expect($actual) {
        return Spec::current()->expect($actual);
    }

    function wait($actual, $timeout = null) {
        return Spec::current()->wait($actual, $timeout);
    }

    function skipIf($condition) {
        $current = Spec::current() ?: Suite::current();
        return $current->skipIf($condition);
    }
}
