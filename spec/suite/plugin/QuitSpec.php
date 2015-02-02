<?php
namespace kahlan\spec\suite\plugin;

use jit\Interceptor;
use jit\Parser;
use kahlan\QuitException;
use kahlan\plugin\Quit;
use kahlan\jit\patcher\Quit as QuitPatcher;

use kahlan\spec\fixture\plugin\quit\Foo;

describe("Quit", function() {

    /**
     * Save current & reinitialize the Interceptor class.
     */
    before(function() {
        $this->previous = Interceptor::instance();
        Interceptor::unpatch();

        $cachePath = rtrim(sys_get_temp_dir(), DS) . DS . 'kahlan';
        $include = ['kahlan\spec\\'];
        $interceptor = Interceptor::patch(compact('include', 'cachePath'));
        $interceptor->patchers()->add('quit', new QuitPatcher());
    });

    /**
     * Restore Interceptor class.
     */
    after(function() {
        Interceptor::load($this->previous);
    });

    describe("::enable()", function() {

      beforeEach(function() {

        Quit::enable();

      });


      it("should enables", function() {

          expect(Quit::enabled())->toBe(true);

      });

      after(function() {

          Quit::disable();

      });

    });

    describe("::disable()", function() {

        it("throws an exception when an exit statement occurs if not allowed", function() {

            Quit::disable();

            $closure = function() {
                $foo = new Foo();
                $foo->exitStatement(-1);
            };

            expect($closure)->toThrow(new QuitException('Exit statement occured', -1));

        });

    });

});
