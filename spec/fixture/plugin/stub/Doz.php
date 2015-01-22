<?php
namespace kahlan\spec\fixture\plugin\stub;

abstract class Doz
{
    protected $_inited = false;

    public function __construct()
    {
        $this->_inited = true;
    }

    public function rand($min = 0, $max = 100)
    {
        return rand($min, $max);
    }

    public function datetime($datetime = 'now')
    {
        return new \DateTime($datetime);
    }

    abstract public function bar($var1 = null, array $var2 = []);
}

?>