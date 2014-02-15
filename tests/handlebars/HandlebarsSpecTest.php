<?php

use Snidely\Snidely;
use Snidely\Scope;

class HandlebarsSpecTest extends PHPUnit_Framework_TestCase {
    public $skip = [
        'basic context-pathed functions with context argument-00' => 1,
        'basic context-depthed functions with context argument-00' => 1,
        'basic context-block functions with context argument-00' => 1,
        'basic context-depthed block functions with context argument-00' => 1,
        'basic context-block functions without context argument-00' => 1,
        'basic context-pathed block functions without context argument-00' => 1,
        'basic context-depthed block functions without context argument-00' => 1,
        'basic context-this keyword nested inside path-00' => 0,
        'basic context-this keyword in helpers-00' => 0,
        'basic context-this keyword in helpers-01' => 0,
        'basic context-this keyword nested inside helpers param-00' => 0,
        'basic context-functions returning safestrings shouldn\'t be escaped-00' => 1,
        'basic context-that current context path ({{.}}) doesn\'t hit helpers-00' => 1,
    ];


    /**
     * @dataProvider provider
     * @param string The name of the test.
     * @param array $spec The spec to test.
     */
    public function testSpec($name, $spec) {
        // Check to see if the spec is being skipped.
        if (isset($this->skip[$name])) {
            $this->markTestSkipped($this->skip[$name]);
            return;
        }

        // Load and compile the template.
        $snidely = new Snidely();
        $snidely->compilerFlags = \Snidely\PhpCompiler::HANDLEBARS;

        $snidely->cachePath($cache_path = PATH_TEST_CACHE.'/handlebars-spec');
        if (!file_exists($snidely->cachePath()))
            mkdir($snidely->cachePath(), 0777, true);

        // Grab the helpers.
        if (isset($spec['helpers'])) {
            foreach ($spec['helpers'] as $fname => $defs) {
                if (isset($defs['php'])) {
                    eval("\$helper_fn = {$defs['php']};");
                    $snidely->registerHelper($fname, $helper_fn);
                } else {
                    $this->markTestIncomplete("No definition for helper $fname.");
                }
            }
        }

        $fn = $snidely->compile($spec['template'], $name);

        // Grab the data.
        if (isset($spec['data']))
            $data = $spec['data'];
        else
            $data = [];

        // Run the template.
        $actual = $snidely->fetch($fn, $data);

        // Compare to the expected.
        $expected = $spec['expected'];

        $this->assertStrippedEquals($expected, $actual);
    }


    /**
     * Handlebars doesn't strip whitespace like mustache or snidely.
     * This method strips all applicable whitespace before comparing two strings.
     * @param string $expected
     * @param string $actual
     */
    public function assertStrippedEquals($expected, $actual) {
        $expected = trimWhitespace($expected);
        $actual = trimWhitespace($actual);
        $this->assertEquals($expected, $actual);
    }

    public function provider() {
        $paths = glob(PATH_TEST."/handlebars-spec/spec/*.json");

        $result = array();
        foreach ($paths as $path) {
            $basename = basename($path, '.json');

            $json = json_decode(file_get_contents($path), true);
            foreach ($json as $spec) {
                $desc =  $spec['description'];
                $it = $spec['it'];

                $base_testname = strtolower("$desc-$it");

                for ($i = 0; $i < 20; $i++) {
                    $testname = "$base_testname-".sprintf('%02d', $i);

                    if (!isset($result[$testname])) {
                        $result[$testname] = [$testname, $spec];
                        break;
                    }
                }
            }
        }
        return $result;
    }
}