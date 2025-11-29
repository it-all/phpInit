<?php
declare(strict_types=1);
namespace Infrastructure\Utilities;

use DateTimeImmutable;
use Infrastructure\Utilities\PHPMailerService;

class ThrowableHandler
{
    private static $logFilePath;
    private static $maxErrorLogChars;
    private $echoInBrowser;
    private $fatalRedirectPage;
    private $fatalHtml;
    private $emailer;
    private $webmasterEmail;
    private $emailFailMessage;
    private $emailLogPath;
    private $emailLogHandle;
    private $isEmailLogFileOpenError;
    private $isEmailLogFileWriteError;
    const MAX_EMAIL_PER_HOUR = 10;

    /**
     * email error if true
     * set true if 
     * - emailer not null and 
     * - webmasterEmail not null and 
     * - the error is not an email send error (avoid infinite loop) and
     * - <= than MAX_EMAIL_PER_HOUR have been sent in past hour
     */
    private $isSendEmail;

    /**
     * if not null, fatal message will be echoed upon fatal errors which do not redirect
     * email is not sent from command line
     */
    public function __construct(string $logFilePath, int $maxErrorLogChars, ?bool $echoInBrowser = false, ?string $fatalRedirectPage = null, ?string $fatalHtml = null, ?PHPMailerService $emailer = null, ?string $webmasterEmail = null, ?string $emailFailMessage = null, ?string $emailLogPath = null)
    {
        self::$logFilePath = $logFilePath;
        self::$maxErrorLogChars = $maxErrorLogChars;
        $this->echoInBrowser = $echoInBrowser;
        $this->fatalRedirectPage = $fatalRedirectPage;
        $this->fatalHtml = $fatalHtml;
        $this->emailer = $emailer;
        $this->webmasterEmail = $webmasterEmail;
        $this->emailFailMessage = $emailFailMessage;
        $this->emailLogPath = $emailLogPath;
        $this->emailLogHandle = null; // init
        $this->isEmailLogFileOpenError = false; // init
        $this->isEmailLogFileWriteError = false; // init
    }

    /**
     * used in set_error_handler()
     * @param int $errno
     * @param string $errstr
     * @param string|null $errfile
     * @param string|null $errline
     * called for script errors and trigger_error()
     */
    public function onError(int $errno, string $errstr, ?string $errfile = null, ?int $errline = null)
    {
        /**
         * This error code is not included in error_reporting, so let it fall
         * through to the standard PHP error handler
         */
        if (!(error_reporting() & $errno)) {
            return false;
        }

        $message = $this->generateMessageBody($errno, $errstr, $errfile, $errline) . PHP_EOL . "Stack Trace:". PHP_EOL . $this->getDebugBacktraceString();
        
        $this->setIsSendEmail($message);
        $this->handle($message);

        /** Don't execute PHP internal error handler */
        return true;
    }

    /** 
     * used in set_exception_handler()
     * @param \Throwable $e
     * catches both Errors and Exceptions
     * create error message and send to handleError
     */
    public function onException(\Throwable $e)
    {
        $message = $this->generateMessageBody($e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine());
        $message .= PHP_EOL . "Stack Trace:" . PHP_EOL . $this->jTraceEx($e);
        $die = ($e->getCode() === 0 || $e->getCode() == E_ERROR || $e->getCode() == E_USER_ERROR) ? true : false;
        $this->setIsSendEmail($e->getMessage());
        $this->handle($message, $die);
    }

    /**
     * used in register_shutdown_function() to see if a fatal error has occurred and handle it.
     * note, this does not occur often in php7+, as almost all errors are now exceptions and will be caught by the registered exception handler. fatal errors can still occur for conditions like out of memory
     * see also https://stackoverflow.com/questions/10331084/error-logging-in-a-smooth-way
     */
    public function onShutdown()
    {
        $error = error_get_last(); // note, stack trace is included in $error["message"]
        if (!isset($error)) {
            return;
        }
        $fatalErrorTypes = [E_USER_ERROR, E_ERROR, E_PARSE, E_CORE_ERROR, E_CORE_WARNING, E_COMPILE_ERROR, E_COMPILE_WARNING];
        if (!in_array($error["type"], $fatalErrorTypes)) {
            return;    
        }
        $message = $this->generateMessageBody($error["type"], $error["message"], $error["file"], $error["line"]);
        $this->setIsSendEmail($error["message"]);
        $this->handle($message, true);
    }

    /*
     * - log to file
     * - echo - always from cli, otherwise depends on echoInBrowser property
     * - email if necessary
     * - die or redirect if necessary
     */
    private function handle(string $messageBody, bool $isDie = false)
    {
        /** happens when an expression is prefixed with @ (meaning: ignore errors) */
        if (error_reporting() == 0) {
            return;
        }

        $errorMessage = self::generateMessage($messageBody);

        /** log to file */
        self::logError($errorMessage, false, $this->echoInBrowser);

        /**
         * echo
         * email
         * die / redirect
         */
        if (PHP_SAPI == 'cli') {
            echo $errorMessage;
            if ($isDie) {
                echo "\n";
                die("FATAL");
            }
        } else {
            if ($this->echoInBrowser) {
                echo nl2br(htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'), false);
                $dieMessage = '';
            } else {
                $dieMessage = $this->fatalHtml;
            }

            /** email if necessary */
            if ($this->isSendEmail) {
                if (!$this->emailer->send($_SERVER['HTTP_HOST'] . " Error Notification", $errorMessage, [$this->webmasterEmail])) {
                    self::logError("Error Notification Email Send Failure");
                }
            } elseif ($this->isEmailLogFileOpenError || $this->isEmailLogFileWriteError) {
                $word = ($this->isEmailLogFileOpenError) ? 'Open' : 'Write';
                self::logError("Error Notification Email Log File $word Error. Check file and permissions");
            }

            /** die or redirect */
            if ($isDie) {
                /** do not redirect if the redirect page is the current page with error */
                if ($this->fatalRedirectPage != null && (!isset($_SERVER['REQUEST_URI']) || !strstr($this->fatalRedirectPage, $_SERVER['REQUEST_URI'])) ) {
                    if (!(isset($_SERVER['REQUEST_URI']) && strstr($this->fatalRedirectPage, $_SERVER['REQUEST_URI']))) {
                        header("Location: $this->fatalRedirectPage");
                        exit();
                    }
                }
                die($dieMessage);
            }
        }
    }

    public static function logError(string $message, bool $generateMessageWrappers = true, bool $echoError = false) 
    {
        $errorMessage = (!$generateMessageWrappers) ? $message : self::generateMessage($message);
        if (mb_strlen($errorMessage) > self::$maxErrorLogChars) {
            $errorMessage = substr($errorMessage, 0, self::$maxErrorLogChars) . " | ERROR MESSAGE TRUNCATED AFTER " . self::$maxErrorLogChars . " CHARACTERS" . PHP_EOL . PHP_EOL;
        }
        /** if error is not suppressed, warning is emitted, even on live server */
        if (!@error_log($errorMessage, 3, self::$logFilePath)) {
            if ($echoError) {
                echo "<br>Warning: error_log() failure: " . self::$logFilePath;
            }
        }
    }

    /**
     * this function must be called by each registered handler prior to calling handle()
     * sets isSendEmail true iff
     * - emailer is set and
     * - webmaster email is set and
     * - this is not an email failure error
     * - email log file is readable and writable and
     * - less than MAX_EMAIL_PER_HOUR entries are in the log in the past hour
     * only writes to the log when sending an email, not when it's already full.
     * truncates file and writes back only the last (up to MAX_EMAIL_PER_HOUR - 1) entries, plus an entry for current time if sending an email, for a total of MAX_EMAIL_PER_HOUR or less
     * so once the log file "fills up" with MAX_EMAIL_PER_HOUR entries, no emails will be sent until an hour passes from first entry
     */
    private function setIsSendEmail(string $errorMessageBody)
    {
        if ($this->emailer === null || $this->webmasterEmail == null || $this->emailLogPath == null || strstr($errorMessageBody, $this->emailFailMessage)) {
            $this->isSendEmail = false;
            return;
        }

        if (!$this->emailLogHandle = fopen($this->emailLogPath, "r+")) {
            $this->isSendEmail = false;
            $this->isEmailLogFileOpenError = true;
            return;
        }

        /** assume true, set false if #$logLinesPastHour >= MAX_EMAIL_PER_HOUR */
        $this->isSendEmail = true; 

        $logLinesPastHour = [];
        while (!feof($this->emailLogHandle)) {
            if ($line = fgets($this->emailLogHandle)) {
                $lineDtString = substr($line, 0, mb_strlen($line) - mb_strlen(PHP_EOL));
                $lineDt = DateTimeImmutable::createFromFormat(self::getDateTimeFormat(), $lineDtString);
                $oneHourAgo = date(self::getDateTimeFormat(), strtotime('-1 hour'));
                $oneHourAgoDt = DateTimeImmutable::createFromFormat(self::getDateTimeFormat(), $oneHourAgo);
                if ($lineDt > $oneHourAgoDt) {
                    /** less than an hour ago */
                    $logLinesPastHour[] = $line;
                    if (count($logLinesPastHour) >= self::MAX_EMAIL_PER_HOUR) {
                        $this->isSendEmail = false;
                        break;
                    }
                }
            }
        }

        if ($this->isSendEmail) {
            $currentLogLine = self::getTimestamp() . PHP_EOL;
            $logLinesToWrite = array_merge([$currentLogLine], $logLinesPastHour);
            ftruncate($this->emailLogHandle, 0);
            rewind($this->emailLogHandle);
            if (fwrite($this->emailLogHandle, implode($logLinesToWrite)) === false) {
                $this->isEmailLogFileWriteError = true;
                $this->isSendEmail = false;
            }
        }
        fclose($this->emailLogHandle);
    }

    private static function getDateTimeFormat(): string 
    {
        return 'd-M-Y H:i:s e';
    }

    private static function getTimestamp(): string 
    {
        return date(self::getDateTimeFormat(), $_SERVER["REQUEST_TIME"]);
    }

    private static function generateMessage(string $messageBody): string
    {
        $message = "[" . self::getTimestamp() . "] ";
        if (PHP_SAPI == 'cli') {
            global $argv;
            $message .= "Command line: " . $argv[0];
        } else {
            /** note $_SERVER['REQUEST_URI'] includes the query string */
            $message .= $_SERVER['HTTP_HOST'] . " " . $_SERVER['REQUEST_METHOD'] . " " . $_SERVER['REQUEST_URI'];
        }
        $message .= PHP_EOL . $messageBody . PHP_EOL . PHP_EOL;
        return $message;
    }

    /**
     * @param int $errno
     * @param string $errstr
     * @param string|null $errfile
     * @param null $errline
     * @return string
     * errline seems to be passed in as a string or int depending on where it's coming from
     */
    private function generateMessageBody(int $errno, string $errstr, ?string $errfile = null, $errline = null): string
    {
        $message = $this->getErrorType($errno) . ": ";
        $message .= htmlspecialchars_decode($errstr) . PHP_EOL;
        if (!is_null($errfile)) {
            $message .= "$errfile";

            /** note it only makes sense to have line if we have file */
            if (!is_null($errline)) {
                $message .= " line: $errline";
            }
        }
        return $message;
    }

    private function getErrorType($errno)
    {
        switch ($errno) {
            case E_ERROR:
                return 'Fatal Error';
            case E_WARNING:
            case E_USER_WARNING:
                return 'Warning';
            case E_NOTICE:
            case E_USER_NOTICE:
                return 'Notice';
            case E_DEPRECATED:
            case E_USER_DEPRECATED:
                return 'Deprecated';
            case E_PARSE:
                return 'Parse Error';
            case E_CORE_ERROR:
                return 'Core Error';
            case E_CORE_WARNING:
                return 'Core Warning';
            case E_COMPILE_ERROR:
                return 'Compile Error';
            case E_COMPILE_WARNING:
                return 'Compile Warning';
            case E_RECOVERABLE_ERROR:
                return 'Recoverable Error';
            default:
                return 'Unknown error type';
        }
    }

    /**
     *  note, formats of various stack traces will differ, as this is not the only way a stack trace is generated. php automatically generates one for fatal errors that are caught in the shutdown function, and throwable exceptions generate one through their getTraceAsString method.
     */
    private function getDebugBacktraceString(): string
    {
        $out = "";
        $dbt = debug_backtrace(~DEBUG_BACKTRACE_PROVIDE_OBJECT & ~DEBUG_BACKTRACE_IGNORE_ARGS);

        /** skip the first 2 entries, because they're from this file */
        array_shift($dbt);
        array_shift($dbt);

        /**
         *  these could be in $config, but with the various format note above, that could lead to confusion.
         *  also, since the $e->getTraceAsString method shows the full file path, for consistency best to show it here
         */
        $showVendorCalls = true;
        $showFullFilePath = true;

        /** only applies if $showFullFilePath is false */
        $startFilePath = '/src';
        $showClassNamespace = false;

        foreach ($dbt as $index => $call) {
            $outLine = "#$index:";
            if (isset($call['file'])) {
                if (!$showVendorCalls && strstr($call['file'], '/vendor/')) {
                    break;
                }
                $outLine .= " ";
                if ($showFullFilePath) {
                    $outLine .= $call['file'];
                } else {
                    $fileParts = explode($startFilePath, $call['file']);
                    $outLine .= (isset($fileParts[1])) ? $fileParts[1] : $call['file'];
                }
            }
            if (isset($call['line'])) {
                $outLine .= " [".$call['line']."] ";
            }
            if (isset($call['class'])) {
                $classParts = explode("\\", $call['class']);
                $outLine .= " ";
                $outLine .= ($showClassNamespace) ? $call['class'] : $classParts[count($classParts) - 1];
            }
            if (isset($call['type'])) {
                $outLine .= $call['type'];
            }
            if (isset($call['function'])) {
                $outLine .= $call['function']."()";
            }
            if (isset($call['args'])) {
                $outLine .= " {".Functions::arrayWalkToStringRecursive($call['args'], 0, 1000, PHP_EOL)."}";
            }
            $out .= "$outLine" . PHP_EOL;
        }

        return $out;
    }

    /**
     * from: https://www.php.net/manual/en/exception.gettraceasstring.php
     * jTraceEx() - provide a Java style exception trace
     * @param $exception
     * @param $seen      - array passed to recursive calls to accumulate trace lines already seen
     *                     leave as NULL when calling this function
     * @return array of strings, one entry per trace line
     */
    public function jTraceEx($e, $seen=null)
    {
        $starter = ($seen) ? 'Caused by: ' : '';
        $result = array();
        if (!$seen) {
            $seen = array();
        } 
        $trace  = $e->getTrace();
        $prev   = $e->getPrevious();
        $result[] = sprintf('%s%s: %s', $starter, get_class($e), $e->getMessage());
        $file = $e->getFile();
        $line = $e->getLine();
        while (true) {
            $current = "$file:$line";
            if (is_array($seen) && in_array($current, $seen)) {
                $result[] = sprintf(' ... %d more', count($trace)+1);
                break;
            }
            $result[] = sprintf(' at %s%s%s(%s%s%s)',
                                        count($trace) && array_key_exists('class', $trace[0]) ? str_replace('\\', '.', $trace[0]['class']) : '',
                                        count($trace) && array_key_exists('class', $trace[0]) && array_key_exists('function', $trace[0]) ? '.' : '',
                                        count($trace) && array_key_exists('function', $trace[0]) ? str_replace('\\', '.', $trace[0]['function']) : '(main)',
                                        $line === null ? $file : basename($file),
                                        $line === null ? '' : ':',
                                        $line === null ? '' : $line);
            if (is_array($seen)) {
                $seen[] = "$file:$line";
            }
            if (!count($trace)) {
                break;
            }
            $file = array_key_exists('file', $trace[0]) ? $trace[0]['file'] : 'Unknown Source';
            $line = array_key_exists('file', $trace[0]) && array_key_exists('line', $trace[0]) && $trace[0]['line'] ? $trace[0]['line'] : null;
            array_shift($trace);
        }
        $result = join("\n", $result);
        if ($prev) {
            $result  .= "\n" . $this->jTraceEx($prev, $seen);
        }

        return $result;
    }
}
