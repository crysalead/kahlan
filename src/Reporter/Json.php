<?php
namespace Kahlan\Reporter;

class Json extends OutputReporter
{
    /**
     * Store the current number of dots.
     * 
     * @var integer
     */
    protected $_counter = 0;

    /**
     * Store schema for JSON output
     *
     * @var array
     */
    protected $_json = [
    	"errors" => [

    	],
    	"summary" => [
    		"success" => 0,
    		"failed" => 0,
    		"skipped" => 0,
    		"error" => 0,
    		"passed" => 0,
    		"incomplete" => 0,
    	]
    ];

    /**
     * Callback called before any specs processing.
     *
     * @param array $params The suite params array.
     */
    public function start($params)
    {
        parent::start($params);
        $this->write("\n");
    }

    /**
     * Callback called on successful expect.
     *
     * @param object $report An expect report object.
     */
    public function pass($report = null)
    {
    	$this->_json["summary"]["passed"] += 1;
        $this->_write('.');
    }

    /**
     * Callback called on failure.
     *
     * @param object $report An expect report object.
     */
    public function fail($report = null)
    {
    	$this->_json["summary"]["failed"] += 1;
        $this->_write('F', 'red');
    }

    /**
     * Callback called when an exception occur.
     *
     * @param object $report An expect report object.
     */
    public function exception($report = null)
    {
        $this->_write('E', 'magenta');
    }

    /**
     * Callback called on a skipped spec.
     *
     * @param object $report An expect report object.
     */
    public function skip($report = null)
    {
    	$this->_json["summary"]["skipped"] += 1;
        $this->_write('S', 'cyan');
    }

    /**
     * Callback called when a `Kahlan\IncompleteException` occur.
     *
     * @param object $report An expect report object.
     */
    public function incomplete($report = null)
    {
    	$this->_json["summary"]["incomplete"] += 1;
        $this->_write('I', 'yellow');
    }

    /**
     * Callback called at the end of specs processing.
     */
    public function end($results = [])
    {
        do {
            $this->_write(' ');
        } while ($this->_counter % 80 !== 0);

        $this->write("\n");

        foreach ($results['specs'] as $type => $reports) {
            foreach ($reports as $report) {
                if ($report->type() !== 'pass' && $report->type() !== 'skip') {
                    $this->_report($report);

                    // Saving error report into JSON structure
                    switch($report->type()) {
                    	case "fail" : 
							$this->_json["errors"][] = [
		                    	'spec' => trim(implode(" ", $report->messages())),
		                    	'suite' => $report->file(),
		                    	'actual' => $report->params()["actual"],
		                    	'expected' => $report->params()["expected"]
		                    ];
                    		break;
                    	case "exception":
                    		$exception = $report->exception();

                    		$this->_json["errors"][] = [
		                    	'spec' => trim(implode(" ", $report->messages())),
		                    	'suite' => $report->file(),
		                    	'exception' => '`' . get_class($exception) .'` Code(' . $exception->getCode() . ')',
		                    	'trace' => $exception->getMessage()
		                    ];
                    		break;
                    }

                }
            }
        }

        $this->write("\n\n");
        $this->_summary($results);
        $this->_reportFocused($results);

        // Write report to file and exit
        fwrite($this->_fp, json_encode($this->_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        fclose($this->_fp);
    }

    /**
     * Outputs the string message in the console.
     *
     * @param string       $string  The string message.
     * @param array|string $options The color options.
     */
    protected function _write($string, $options = null)
    {
        $this->write($string, $options);
        $this->_counter++;
        if ($this->_counter % 80 === 0) {
            $this->write(' ' . floor(($this->_current * 100) / $this->_total) . "%\n");
        }
    }

}