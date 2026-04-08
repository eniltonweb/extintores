<?php
// Simple Test Runner

// Base class for tests to avoid dependencies on PHPUnit
// Defined before requirements to avoid "Class not found" errors
if (!class_exists('MiniTestCase')) {
    class MiniTestCase {
        protected function assertEquals($expected, $actual, $message = '') {
            if ($expected !== $actual) {
                throw new Exception("Expected " . var_export($expected, true) . ", but got " . var_export($actual, true) . ". {$message}");
            }
        }

        protected function assertTrue($condition, $message = '') {
            if ($condition !== true) {
                throw new Exception("Expected true, but got " . var_export($condition, true) . ". {$message}");
            }
        }
    }
}

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    $tests_dir = __DIR__;
    $test_files = glob($tests_dir . '/*Test.php');

    $passed = 0;
    $failed = 0;

    foreach ($test_files as $file) {
        require_once $file;
        $class_name = basename($file, '.php');
        if (class_exists($class_name)) {
            $test_obj = new $class_name();
            $methods = get_class_methods($test_obj);
            foreach ($methods as $method) {
                if (strpos($method, 'test') === 0) {
                    echo "Running {$class_name}::{$method}... ";
                    try {
                        $test_obj->$method();
                        echo "PASSED\n";
                        $passed++;
                    } catch (Exception $e) {
                        echo "FAILED: " . $e->getMessage() . "\n";
                        $failed++;
                    } catch (Error $e) {
                        echo "ERROR: " . $e->getMessage() . "\n";
                        $failed++;
                    }
                }
            }
        }
    }

    echo "\nSummary: {$passed} passed, {$failed} failed.\n";
    exit($failed > 0 ? 1 : 0);
}
?>
