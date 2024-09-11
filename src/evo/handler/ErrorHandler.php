<?php
namespace evo\handler;

use evo\pattern\singleton\SingletonInterface;
use evo\pattern\singleton\SingletonTrait;
use evo\exception as E;

/**
 * Convert all PHP errors to Exceptions
 * Catch all uncaught Exceptions
 * Catch errors that cause PHP shutdown and are not otherwise handled
 */
final class ErrorHandler implements SingletonInterface
{
    use SingletonTrait;

    /**
     * Track to avoid unnecessary callback sorting
     * @var bool
     */
    protected bool $sorted = false;

    /**
     * @var array  - collection of callback handlers
     */
    protected array $error_handlers = [];

    /**
     * called once upon instantiation from SingletonTrait
     * this is a good place to do any __construct type stuff
     * @return void
     */
    protected function init(): void
    {
        //convert PHP errors to ErrorExceptions
        set_error_handler([$this,"convertErrorException"]);
        //catch all uncaught Exceptions (this will also include our errors that were converted to exceptions, if they are not caught)
        set_exception_handler([$this, "catchException"]);
        //catch all shutdown errors  max_allowed_memory, max_execution_time etc...
        register_shutdown_function([$this,"catchShutdown"]);

        if(ini_get('display_errors')){
            $this->setErrorCallback(
                'display',
                [$this, 'displayError']
            );
        }
    }

    /**
     * @param string $identifier - the unique name of this callback
     * @param callable $callback - the callback function for errors that pass error_reporting() severity
     * @param int $priority - the order the callbacks should be executed in Highest first
     * @return void
     *
     *  callback should take this form
     *  callback(int $severity, string $message, ?string $source=null, ?string $file=null, ?int $line=null, ?string $trace=null) : bool
     *  return true if the error was handled or false if it wasn't - true will prevent other lower priority error handlers for executing
     */
    public function setErrorCallback(string $identifier, callable $callback, int $priority = 10, bool $sort = true): void
    {
        $this->error_handlers[$identifier] = ['priority'=>$priority, 'callback' => $callback];
        $this->sorted = false;
    }

    /**
     * @return array
     */
    public function getErrorCallbacks(): array
    {
        return $this->error_handlers;
    }

    /**
     * @param string $identifier
     * @return false|array
     */
    public function getErrorCallback(string $identifier): false|array
    {
        if(!$this->issetErrorCallback($identifier)) return false;

        return $this->error_handlers[$identifier];
    }

    /**
     * @param string $identifier
     * @return bool
     */
    public function issetErrorCallback(string $identifier): bool
    {
        return isset($this->error_handlers[$identifier]);
    }

    /**
     * @param string $identifier
     * @return void
     */
    public function unsetErrorCallback(string $identifier): void
    {
        //simply unsetting an item doesn't affect the general order of things
        unset($this->error_handlers[$identifier]);
    }

    /**
     * get a list of all the error handlers
     * @return array
     */
    public function getErrorCallbackIdentifiers(): array
    {
        return array_keys($this->error_handlers);
    }

    /**
     *
     * @param string $identifier
     * @param int $priority
     * @param bool $sort
     * @return $this
     */
    public function setCallbackPriority(string $identifier, int $priority = 10, bool $sort = true) : self
    {
        if($this->issetErrorCallback($identifier)){
            $this->error_handlers[$identifier]['priority'] = $priority;
        }
        $this->sorted = false;
        return $this;
    }

    /**
     * @param string $identifier
     * @return bool|int
     */
    public function getCallbackPriority(string $identifier) : bool|int
    {
        if(!$this->issetErrorCallback($identifier)) return false;

        return $this->error_handlers[$identifier]['priority'];
    }

    /**
     * Get a common "Name" for each error severity level
     *
     * @param int $severity - one of the E_* core PHP constants
     * @return string
     */
    public static function getSeverityStr(int $severity) : string
    {
        return match ($severity) {
            0 => 'None',
            E_USER_ERROR, E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE => 'Error',
            E_RECOVERABLE_ERROR, E_CORE_WARNING, E_WARNING, E_COMPILE_WARNING, E_USER_WARNING, E_STRICT => 'Warning',
            E_DEPRECATED, E_USER_DEPRECATED => 'Deprecated',
            E_NOTICE, E_USER_NOTICE => 'Notice',
            default => 'Unknown'
        };
    }

    /**
     * Handle all errors in a standardized way
     * errors are filtered depending on you error_level() settings and the $severity of the error
     *
     * @param int $severity - the error code one of the E_* global error constants
     * @param string $message - the error message as a string
     * @param string|null $file - the file the error occurred on
     * @param int|null $line - the line number the error occurred on
     * @param string|null $trace - a stacktrace of the application execution
     * @return bool - was the error handled
     */
    public function handleError(
        int $severity,
        string $message,
        #[\SensitiveParameter] ?string $source=null,
        #[\SensitiveParameter] ?string $file=null,
        #[\SensitiveParameter] ?int $line=null,
        #[\SensitiveParameter] ?string $trace=null
    ) : bool {
        if(!$severity) return false;

        if(!$this->sorted) $this->sortCallbacks();

        foreach ($this->error_handlers as $identifier => $handler){
            $callback = $handler['callback'];
            try{
                if($callback($severity, $message, $source, $file, $line, $trace)) return true;
            }catch(\Throwable $e){
                $this->displayError(
                    E_ERROR,
                    $e->getMessage(),
                    self::getSourceFromException($e),
                    $e->getFile(),
                    $e->getLine(),
                    $e->getTraceAsString()
                );
            }
        }
        return false;
    }
    //sort the callback list by priority highest first

    /**
     * @return void
     */
    protected function sortCallbacks() : void
    {
        uasort($this->error_handlers, fn($a, $b)=>$b['priority']<=>$a['priority']);
        $this->sorted = true;
    }


    /**
     * Default error handler callback - display
     *
     * @param int $severity
     * @param string $message
     * @param string|null $source
     * @param string|null $file
     * @param int|null $line
     * @param string|null $trace
     * @return void
     */
    public function displayError(
        int $severity,
        string $message,
        #[\SensitiveParameter] ?string $source=null,
        #[\SensitiveParameter] ?string $file=null,
        #[\SensitiveParameter] ?int $line=null,
        ?string $trace=null
    ) : void {
        $file = $file ?? 'unknown';
        $line = $line ?? 'unknown';

        $severity_str = self::getSeverityStr($severity);

        echo "\n{$severity_str} {$source} {$message} IN {$file}:{$line}\n";
        if($trace){
            echo "Stack trace:\n";
            echo $trace . "\n";
        }
    }

    /**
     * This method is intended to be protected but must be public by convention
     *
     * Convert all standard PHP errors to ErrorExceptions
     * Throwing exceptions for errors can break applications in unexpected ways, especially during updates.
     * Therefore, this is controlled using the error_reporting() setting.
     * Only errors allowed for reporting will be converted to ErrorExceptions
     * Deprecated errors will never be thrown as exceptions, these are not expected to break the application
     *
     * @param int $severity
     * @param string $message
     * @param string|null $file
     * @param int|null $line
     * @return bool
     * @throws E\EvoErrorException
     *
     * @see https://www.php.net/manual/en/function.set-error-handler.php
     */
    public function convertErrorException(
        int $severity,
        string $message,
        #[\SensitiveParameter] string $file=null,
        #[\SensitiveParameter] int $line=null
    ) : bool {
        //if error reporting is not on for this $severity or if it's a Deprecated notice don't throw exceptions
        if (!(error_reporting() & $severity) || $severity === E_DEPRECATED || $severity === E_USER_DEPRECATED) return false;

        //Other errors will be thrown as ErrorExceptions with {$severity} severity
        throw new E\EvoErrorException($message, 0, $severity, $file, $line);
    }

    /**
     * You are encouraged to send unrecoverable caught exceptions to this function for logging and uniform handling
     * or simply re-throw them  throw $object;
     *
     * @param \Throwable $Exception
     * @return void
     *
     * @see https://www.php.net/manual/en/function.set-exception-handler.php
     */
    public function catchException(#[\SensitiveParameter] \Throwable $Exception): void {
        $severity = method_exists($Exception, 'getSeverity') ? $Exception->getSeverity() : E_ERROR;
        $file = $Exception->getFile();
        $line = $Exception->getLine();
        $message = $Exception->getMessage();
        $source = self::getSourceFromException($Exception);

        $str_trace = !empty($Exception->getTrace()) ? $Exception->getTraceAsString() : "#0 {main}\n\tthrown in {$file} on {$line}";

        $this->handleError(
            $severity,
            $message,
            $source,
            $file,
            $line,
            $str_trace
        );
    }

    /**
     * Adds the Class::code combination to the exception
     * @param \Throwable $Exception
     * @return string
     */
    public static function getSourceFromException(#[\SensitiveParameter] \Throwable $Exception) : string
    {
        return  get_class($Exception).'::'.$Exception->getCode();
    }

    /**
     * This method is intended to be protected but must be public by convention
     *
     * handle shutdowns - php shutdown handler
     * @return bool - false to continue execution normal shutdown
     *
     * @see https://www.php.net/manual/en/function.register-shutdown-function.php
     */
    public function catchShutdown() : bool{
        $last_error = error_get_last();

        /*
         *	@example = error_get_last()
         *	[type] => 8
         *	[message] => Undefined variable: a
         *	[file] => C:\WWW\index.php
         *	[line] => 2
         */

        if(!is_null($last_error)){
            $severity = $last_error['type'];
            $message = $last_error['message'];
            $file = $last_error['file'] ?? 'unknown';
            $line = $last_error['line'] ?? 'unknown';
            $trace = $this->backTraceAsString();
            $source = 'Shutdown';
            $this->handleError($severity, $message, $source, $file, $line, $trace);
        }

        return false;
    }

    /**
     * convert the output of back_trace() to an Exception like stack trace string
     * this is useful when we don't have an exception we can easily get an accurate stacktrace from.
     *
     * @param int $offset - offset from where this method was called in the function stack
     * @param array|null $trace - not intended for external use ( recursion )
     * @return string
     */
    public function backTraceAsString(int $offset=1, #[\SensitiveParameter] ?array $trace=null) : string {
        $stack = '';
        if(!$trace) {
            $trace = debug_backtrace();
            unset($trace[0]); //Remove call to this function from the stack trace
        }

        $count = $offset;
        foreach($trace as $node) {

            $stack .= "#" . $count . " ";

            if(!isset($node['file'])){
                $stack .= "[internal function]: ";
            }else{
                $node['file'] = str_replace("\\", "/", $node['file']);
                $stack .= $node['file'] . "(" . $node['line'] . "): ";
            }

            ++$count;
            if(isset($node['class'])) {
                $stack .= $node['class'];
                if($node['type'] == '->'){
                    $stack .= '->';
                }else{
                    $stack .= '::';
                }
            }

            if(isset($node['function'])){
                $stack .= $node['function'];
            }

            if(isset($node['args'])){
                $args = array();
                foreach ($node['args'] as $arg){
                    if(is_array($arg)){
                        $args[] = 'Array';
                    }else if(is_object($arg)){
                        //check for nested exception classes in the stack
                        $args[] = get_class($arg);

                        if(is_a($arg, 'Throwable')){;
                            $stack .= PHP_EOL . $this->backTraceAsString($arg->getTrace()); //recurse
                        }
                    }else if(is_resource($arg)){
                        $args[] = 'Resource';
                    }else if($arg){
                        $args[] = $arg;
                    }
                }
                $stack .= "(".implode(",", $args).")";
            }
            $stack .= PHP_EOL;
        }
        return $stack;
    }
}