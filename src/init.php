<?php
/** 
 * this file can be called at the top of every web page or cli script
 * note, this can instead be set with auto_prepend_file in php.ini, but that would 
 * mean it is required for every web page on the server. see:
 * https://websistent.com/php-auto_prepend_file-and-auto_append_file/
 */
declare(strict_types=1);
use Dotenv\Dotenv;
use Utilities\ThrowableHandler;

/** all, including future types */
error_reporting(-1); 

/** note that until the .env var PHP_ERROR_LOG_PATH is set, errors will be logged to the default php.ini file or the web server error log */
ini_set('log_errors', true);

/** so no need to set encoding for each mb_strlen() call */
mb_internal_encoding("UTF-8");

define('APPLICATION_ROOT_DIRECTORY', realpath(__DIR__ . '/..'));
const AUTOLOAD_CLASSES_DIR = 'src';
require APPLICATION_ROOT_DIRECTORY . '/vendor/autoload.php';
spl_autoload_register(function ($class) {
    $file = APPLICATION_ROOT_DIRECTORY . DIRECTORY_SEPARATOR . AUTOLOAD_CLASSES_DIR . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $class).'.php';
    if (file_exists($file)) {
        require $file;
        return true;
    }
    return false;
});

/** ENV AND CONFIG */

/** default config in case not set in .env */

/** 
 *  whether or not to display errors on dev servers
 *  note, the server type (live or dev) is defined by the IS_LIVE env var
 */
const IS_ECHO_ERRORS_DEV_DEFAULT = true;

/** per error */
const MAX_ERROR_LOG_CHARS_DEFAULT = 7900;
const MIN_ERROR_LOG_CHARS = 200;

/** on fatal error without redirect, echo this */
const FATAL_ERROR_HTML_DEFAULT = '<br>Our apologies, an error has occurred. We will fix the problem asap.<br>';

/** parse .env */
const DOTENV_BOOLS = ['IS_LIVE'];
const DOTENV_BOOL_TRUE_VALUES = [1, "true", "yes", "on"];
$dotenv = Dotenv::createImmutable(APPLICATION_ROOT_DIRECTORY);
$dotenv->load();
$dotenv->required('IS_LIVE')->isBoolean();
$dotenv->required(['PHP_ERROR_LOG_PATH'])->notEmpty();
$dotenv->ifPresent('IS_ECHO_ERRORS_DEV')->isBoolean();
$dotenv->ifPresent('MAX_ERROR_LOG_CHARACTERS')->isInteger();
$dotenv->ifPresent('ERROR_PAGE')->notEmpty();
$dotenv->ifPresent('FATAL_ERROR_HTML')->notEmpty();

/** overwrites .env bools with a PHP boolean value */
(function($dotenvBools) {
    foreach ($dotenvBools as $bool) {
        $dotEnvVal = $_ENV[$bool];
        $boolValue = in_array(strtolower($dotEnvVal), DOTENV_BOOL_TRUE_VALUES);
        $_ENV[$bool] = $boolValue;
        $_SERVER[$bool] = $boolValue;
    }
})(DOTENV_BOOLS);

/** set variables for env vars with defaults */
$isEchoErrorsDev = $_ENV['IS_ECHO_ERRORS_DEV'] ?? IS_ECHO_ERRORS_DEV_DEFAULT;
$maxErrorLogChars = (int) $_ENV['MAX_ERROR_LOG_CHARS'] ?? MAX_ERROR_LOG_CHARS_DEFAULT;
$fatalErrorHtml = $_ENV['FATAL_ERROR_HTML'] ?? FATAL_ERROR_HTML_DEFAULT;

/** error handling */

/** 
 * even though the error handler logs errors, this ensures errors in the error handler itself or in this file after this point through error handler instantiantion will be logged.
 * any errors prior to this point will not be logged, unless configured in php.ini
 * ignore_repeated_errors only stops the same error from being logged in case it happens more than once in a row, on the same page load
 * see https://github.com/php/php-src/issues/19509
 */
ini_set('ignore_repeated_errors', 'On');
ini_set('error_log', $_ENV['PHP_ERROR_LOG_PATH']);

if (isset($_ENV['TIME_ZONE']) && mb_strlen($_ENV['TIME_ZONE']) > 0) {
    date_default_timezone_set($_ENV['TIME_ZONE']);
}

if ($maxErrorLogChars < MIN_ERROR_LOG_CHARS) {
    throw new Exception("MAX_ERROR_LOG_CHARACTERS .env value too low" . PHP_EOL . PHP_EOL);
}

/**
 * resources:
 * https://www.php.net/manual/en/class.errorexception.php
 * https://www.php.net/manual/en/function.set-error-handler.php
 * 
 */
$echoErrorsInBrowser = !$_ENV['IS_LIVE'] && $isEchoErrorsDev;

/** do not redirect to error page on test servers */
$fatalRedirectPage = ($_ENV['IS_LIVE'] && isset($_ENV['ERROR_PAGE'])) ? $_ENV['ERROR_PAGE'] : null;

$throwableHandler = new ThrowableHandler($_ENV['PHP_ERROR_LOG_PATH'], $maxErrorLogChars, $echoErrorsInBrowser, $fatalRedirectPage, $fatalErrorHtml);

set_error_handler(array($throwableHandler, 'onError'));
set_exception_handler(array($throwableHandler, 'onException'));

/** 
 * workaround for catching some fatal errors like parse errors.
 * note that parse errors will only be handled if they are in included or required files
 * https://stackoverflow.com/questions/29735022/how-to-handle-parse-error-using-register-shutdown-function-in-php#29735256
 * otherwise, they cause a fatal error, which will only be displayed if display_errors is on in php.ini
 * see answer in https://stackoverflow.com/questions/1053424/how-do-i-get-php-errors-to-display
 */
register_shutdown_function(array($throwableHandler, 'onShutdown'));
