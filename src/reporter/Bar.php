<?php
namespace kahlan\reporter;

use set\Set;
use string\String;

class Bar extends Terminal
{
    /**
     * Colors preference.
     *
     * var array
     */
    protected $_preferences = [];

    /**
     * Format of the progress bar.
     *
     * var string
     */
    protected $_format = '';

    /**
     * Chars preference.
     *
     * var array
     */
    protected $_chars = [];

    /**
     * Progress bar color.
     *
     * var integer
     */
    protected $_color = 37;

    /**
     * Size of the progress bar.
     *
     * var integer
     */
    protected $_size = 0;

    /**
     * Constructor
     *
     * @param array $config The config array.
     */
    public function __construct($config = [])
    {
        parent::__construct($config);
        $defaults = [
            'size' => 50,
            'preferences' => [
                'failure' => 'red',
                'success' => 'green',
                'incomplete' => 'yellow'
            ],
            'chars' => [
                'bar' => '=',
                'indicator' => '>'
            ],
            'format' => '[{:b}{:i}] {:p}%'
        ];
        $config = Set::merge($defaults, $config);

        foreach ($config as $key => $value) {
            $_key = "_{$key}";
            $this->$_key = $value;
        }
        $this->_color = $this->_preferences['success'];
    }

    public function begin($params)
    {
        parent::begin($params);
        $this->write("\n");
    }

    /**
     * Callback called when entering a new spec.
     */
    public function before($report)
    {
        parent::before($report);
        $this->_progressBar();
    }

    /**
     * Callback called on failure.
     */
    public function fail($report)
    {
        $this->_color = $this->_preferences['failure'];
        $this->write("\n");
        $this->_report($report);
        $this->write("\n");
    }

    /**
     * Callback called when an exception occur.
     */
    public function exception($report)
    {
        $this->_color = $this->_preferences['failure'];
        $this->write("\n");
        $this->_report($report);
        $this->write("\n");
    }

    /**
     * Callback called when a `kahlan\IncompleteException` occur.
     */
    public function incomplete($report)
    {
        $this->_color = $this->_preferences['incomplete'];
        $this->write("\n");
        $this->_report($report);
        $this->write("\n");
    }

    /**
     * Ouput the progress bar to STDOUT.
     */
    protected function _progressBar()
    {
        if ($this->_current > $this->_total) {
            return;
        }

        $percent = $this->_current / $this->_total;
        $nb = $percent * $this->_size;

        $b = str_repeat($this->_chars['bar'], floor($nb));
        $i = '';

        if ($nb < $this->_size) {
            $i = str_pad($this->_chars['indicator'], $this->_size - strlen($b));
        }

        $p = floor($percent * 100);

        $string = String::insert($this->_format, compact('p', 'b', 'i'));

        $this->write("\r" . $string, $this->_color);
    }

    /**
     * Callback called at the end of specs processing.
     */
    public function end($results = [])
    {
        $this->write("\n");
        $this->_summary($results);
        $this->_exclusive($results);
    }
}
