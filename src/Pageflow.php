<?php
declare(strict_types=1);

namespace Pageflow;

use Dotenv\Dotenv;
use Exception;
use Pageflow\Infrastructure\Database\PostgresService;

use Pageflow\Infrastructure\Utilities\PHPMailerService;
use Pageflow\Infrastructure\Utilities\ThrowableHandler;

/** 
 * a Singleton.
 * The start method can be called at the top of every web page or cli script.
 */
class Pageflow
{
    const DOTENV_BOOLS = ['IS_LIVE', 'IS_EMAIL_ERRORS', 'IS_ECHO_ERRORS_DEV'];
    const DOTENV_BOOL_TRUE_VALUES = [1, "true", "yes", "on"];
    const ALLOWED_PHPMAILER_PROTOCOL_VALUES = ["smtp", "sendmail", "mail", "qmail"];

    /** default config in case not set in .env */

    /** 
     *  whether or not to display errors on dev servers
     *  note, the server type (live or dev) is defined by the IS_LIVE env var
     */
    const IS_ECHO_ERRORS_DEV_DEFAULT = true;

    /** per error */
    const MAX_ERROR_LOG_CHARACTERS_DEFAULT = 7900;
    const MIN_ERROR_LOG_CHARACTERS = 200;

    /** on fatal error without redirect, echo this */
    const FATAL_ERROR_HTML_DEFAULT = '<br>Our apologies, an error has occurred. We will fix the problem asap.<br>';

    const EMAIL_FAIL_MESSAGE_START = 'Email Send Failure';
    const SESSION_KEY_LAST_ACTIVITY = 'LAST_ACTIVITY';
    const SESSION_KEY_CREATED = 'CREATED';

    private $rootDir;
    private $dotEnv;
    private $emailer;
    private $postgres;
    private $pgConn;

    public static function getInstance()
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new Pageflow();
        }
        return $instance;
    }

    public function init(?string $rootDir = null)
    {
        $this->rootDir = $rootDir ?? __DIR__ . '/../../../../';

        /** all, including future types */
        error_reporting(-1); 

        /** note that until the .env var PHP_ERROR_LOG_PATH is set, errors will be logged to the default php.ini file or the web server error log */
        ini_set('log_errors', true);

        /** so no need to set encoding for each mb_strlen() call */
        mb_internal_encoding("UTF-8");

        /** ENV AND CONFIG */

        /** parse .env */
        $dotenv = Dotenv::createImmutable($this->rootDir);
        $dotenv->load();
        $dotenv->required(['IS_LIVE', 'IS_EMAIL_ERRORS'])->isBoolean();
        $dotenv->required('PHP_ERROR_LOG_PATH')->notEmpty();
        $dotenv->ifPresent('IS_ECHO_ERRORS_DEV')->isBoolean();
        $dotenv->ifPresent(['MAX_ERROR_LOG_CHARACTERS', 'SESSION_TTL_MINUTES'])->isInteger();
        $dotenv->ifPresent(['ERROR_PAGE', 
            'FATAL_ERROR_HTML', 
            'POSTGRES_CONNECTION_STRING',
            'SESSION_SAVE_PATH'
        ])->notEmpty();

        $this->convertDotEnvBoolValues(self::DOTENV_BOOLS);

        if ($_ENV['IS_EMAIL_ERRORS']) {
            $dotenv->required(['ERROR_EMAIL_LOG_PATH', 'WEBMASTER_EMAIL'])->notEmpty();
            $dotenv->required(['PHPMAILER_PROTOCOL'])->allowedValues(self::ALLOWED_PHPMAILER_PROTOCOL_VALUES);
            if ($_ENV['PHPMAILER_PROTOCOL'] === 'smtp') {
                $dotenv->required(['PHPMAILER_SMTP_HOST', 
                    'PHPMAILER_SMTP_USERNAME', 
                    'PHPMAILER_SMTP_PASSWORD'
                ])->notEmpty();
                $dotenv->required('PHPMAILER_SMTP_PORT')->isInteger();
                $smtpHost = $_ENV['PHPMAILER_SMTP_HOST'];
                $smtpPort = (int) $_ENV['PHPMAILER_SMTP_PORT'];
                $smtpUsername = $_ENV['PHPMAILER_SMTP_USERNAME'];
                $smtpPassword = $_ENV['PHPMAILER_SMTP_PASSWORD'];
            } else {
                $smtpHost = null;
                $smtpPort = null;
                $smtpUsername = null;
                $smtpPassword = null;
            }
            $errorEmailLogPath = $_ENV['ERROR_EMAIL_LOG_PATH'];
            $webmasterEmail = $_ENV['WEBMASTER_EMAIL'];
            $emailer = new PHPMailerService(
                $webmasterEmail,
                $webmasterEmail,
                'website',
                self::EMAIL_FAIL_MESSAGE_START,
                $_ENV['PHPMAILER_PROTOCOL'],
                $smtpHost,
                $smtpPort,
                $smtpUsername,
                $smtpPassword,
                $webmasterEmail
            );
        } else {
            $errorEmailLogPath = null;
            $webmasterEmail = null;
            $emailer = null;
        }

        $this->dotEnv = $dotenv;
        $this->emailer = $emailer;

        /** set variables for env vars with defaults */
        $isEchoErrorsDev = $_ENV['IS_ECHO_ERRORS_DEV'] ?? self::IS_ECHO_ERRORS_DEV_DEFAULT;
        $maxErrorLogCharacters = (isset($_ENV['MAX_ERROR_LOG_CHARACTERS'])) ? (int) $_ENV['MAX_ERROR_LOG_CHARACTERS'] : self::MAX_ERROR_LOG_CHARACTERS_DEFAULT;
        $fatalErrorHtml = $_ENV['FATAL_ERROR_HTML'] ?? self::FATAL_ERROR_HTML_DEFAULT;

        /** error handling */

        /** 
         * ignore_repeated_errors only stops the same error from being logged in case it happens more than once in a row, on the same page load
         * see https://github.com/php/php-src/issues/19509
         */
        ini_set('ignore_repeated_errors', 'On');

        ini_set('error_log', $_ENV['PHP_ERROR_LOG_PATH']);

        if (isset($_ENV['TIME_ZONE']) && mb_strlen($_ENV['TIME_ZONE']) > 0) {
            date_default_timezone_set($_ENV['TIME_ZONE']);
        }

        if ($maxErrorLogCharacters < self::MIN_ERROR_LOG_CHARACTERS) {
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

        $throwableHandler = new ThrowableHandler($_ENV['PHP_ERROR_LOG_PATH'], $maxErrorLogCharacters, $echoErrorsInBrowser, $fatalRedirectPage, $fatalErrorHtml, $emailer, $webmasterEmail, self::EMAIL_FAIL_MESSAGE_START, $errorEmailLogPath);

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

        /** connect to PostgreSQL */
        if (isset($_ENV['POSTGRES_CONNECTION_STRING'])) {
            $this->postgres = PostgresService::getInstance($_ENV['POSTGRES_CONNECTION_STRING']);
            $this->pgConn = $this->postgres->getConnection();
        }


        /** SESSION 
         * see 
         * https://www.php.net/manual/en/session.security.ini.php
         * https://stackoverflow.com/questions/520237/how-do-i-expire-a-php-session-after-30-minutes
         * https://stackoverflow.com/questions/10165424/how-secure-are-php-sessions
         */
        if (isset($_ENV['SESSION_TTL_MINUTES']) && php_sapi_name() !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {

            /** important security settings */
            ini_set('session.use_strict_mode', 1);
            ini_set('session.use_only_cookies', 1);
            ini_set('session.use_cookies', 1);
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', 1);
            ini_set('session.use_trans_sid', 0);
            ini_set('session.cache_limiter', 'nocache');

            $hashAlgos = hash_algos();
            $hashAlgoPriorities = ['whirlpool', 'sha512', 'sha256', 'sha1'];
            foreach ($hashAlgoPriorities as $hashAlgoPriority) {
                if (in_array($hashAlgoPriority, $hashAlgos)) {
                    $useHashAlgo = $hashAlgoPriority;
                    break;
                }
            }
            if (isset($useHashAlgo)) {
                ini_set('session.hash_function', $useHashAlgo);
            }

            if (isset($_ENV['SESSION_SAVE_PATH'])) {
                ini_set('session.save_path', $_ENV['SESSION_SAVE_PATH']);
            }

            /** PHP 7.3+ */
            ini_set('session.cookie_samesite', 'Strict');

            $sessionTtlSeconds = (int) $_ENV['SESSION_TTL_MINUTES'] * 60;
            ini_set('session.gc_maxlifetime', (string) $sessionTtlSeconds);
            ini_set('session.cookie_lifetime', (string) $sessionTtlSeconds);
            session_start();

            if (isset($_SESSION[self::SESSION_KEY_LAST_ACTIVITY]) && (time() - $_SESSION[self::SESSION_KEY_LAST_ACTIVITY] > $sessionTtlSeconds)) {
                /** last request was more than $sessionTtlSeconds ago */
                session_unset();     // unset $_SESSION variable for the run-time 
                session_destroy();   // destroy session data in storage
                session_start();
            }
            /** update last activity time stamp */
            $_SESSION[self::SESSION_KEY_LAST_ACTIVITY] = time();

            if (!isset($_SESSION[self::SESSION_KEY_CREATED])) {
                $_SESSION[self::SESSION_KEY_CREATED] = time();
            } else if (time() - $_SESSION[self::SESSION_KEY_CREATED] > $sessionTtlSeconds) {
                /** session started more than $sessionTtlSeconds ago */
                session_regenerate_id(true);    // change session ID for the current session and invalidate old session ID
                $_SESSION[self::SESSION_KEY_CREATED] = time();  // update creation time
            }

        }
    }

    public function isSessionStarted(): bool
    {
        if (php_sapi_name() !== 'cli') {
            if (version_compare(phpversion(), '5.4.0', '>=')) {
                return session_status() === PHP_SESSION_ACTIVE ? TRUE : FALSE;
            } else {
                return session_id() === '' ? FALSE : TRUE;
            }
        }
        return false;
    }

    /**
                 * overwrites specified .env bools with a PHP boolean value
                 */
    public function convertDotEnvBoolValues(array $dotEnvBoolVarnames)
    {
        foreach ($dotEnvBoolVarnames as $dotEnvBoolVarname) {
            if (isset($_ENV[$dotEnvBoolVarname])) {
                $dotEnvVal = $_ENV[$dotEnvBoolVarname];
                $boolValue = in_array(strtolower($dotEnvVal), self::DOTENV_BOOL_TRUE_VALUES);
                $_ENV[$dotEnvBoolVarname] = $boolValue;
                $_SERVER[$dotEnvBoolVarname] = $boolValue;
            }
        }
    }

    public function getRootDir(): ?string
    {
        return $this->rootDir;
    }

    public function getDotEnv(): ?Dotenv
    {
        return $this->dotEnv;
    }

    public function getEmailer(): ?PHPMailerService
    {
        return $this->emailer;
    }

    public function getPostgres(): ?PostgresService
    {
        return $this->postgres;
    }

    public function getPgConn()
    {
        return $this->pgConn;
    }
}
