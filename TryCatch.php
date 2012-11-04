<?
/**
 * You can use this code for whatever you want as far as you do not misrespresent its author.
 * If you modify the code you should also note that the code was modified by yourself (or whoever)
 * and include a link to the original code and, if possible, include the original code too.
 * In short don't claim you invented it, keep the link to my site and my email
 * and don't put my name under your shitty code.
 * You can use the code in your commercial projects but you can't sell the code by itself.
 *
 * author: bobef
 * email: bobef@bobef.net, borislav.asdf@gmail.com
 * url: http://bobef.net
 */

/**
 * Provides advanced error handling for PHP.
 * This function also simulates a finally statement which is missing in the PHP language.
 * The function guarantees that the code in the try block will not crash the application
 * and will convert any PHP errors to exceptions and pass them to the catch callback.
 * This function is considered a hack around PHP's hacks,
 * which are officialy called "error handling" in this bug of a language.
 * For this reason you must be aware of some peculiarities:
 * <ul>
 * <li>Normal exeptions that resulted from throw inside the _try
 * won't terminate the script. The program flow will continue
 * after the _try() call, even if the exceptions are not handled. Buf
 * if you rethrow the exception from inside _try() it will bubble out
 * of the _try() block and may crash you script when it gets out.</li>
 * <li>If the code inside _try() includes a script with wrong syntax
 * this will crash the your app without calling catch/finally.</li>
 * <li>In case of php errors the catch/finally blocks will be
 * executed, but the script will terminate after that and
 * the program flow won't be resumed as with normal exceptions.</li>
 * <li>_try() calls may be nested with some exceptions. If there
 * are more than one fatal errors, the second one will crash
 * the script and will prevent further catch/fanally blocks from executing.</li>
 * <li>Using 'exit' inside _try() will behave similar to fatal errors.
 * One exit will trigger the finally blocks. Seconds exit will exit
 * the script without calling the rest of the finally blocks. Also
 * using exit after a fatal error will behave like second exit or fatal error.
 * exit will not trigger the catch blocks.</li>
 * <li>If you nest _try()-s and throw several exceptions, for example,
 * one from the catch block and one from the finally block,
 * only the last one will be caught by the outer catch. It is possible
 * to collect all thrown exceptions, so if you find a use case for it
 * please let me know.</li>
 * <li>I suspect that some php functions will cause an error that is
 * even more fatal than fatal and will terminate the script
 * without calling the shutdown callbacks or error handlers,
 * so catch/finally won't be executed, but I don't have a proof so far,
 * if you find one please let me know.</li>
 * </ul>
 *
 * <b>This functionality is not time tested, please report problems and use cases.
 * Once it is, other error handling functionalities will be removed from the SDK.</b>
 *
 * @param callback a piece of code that will be the try block
 * @param callback a piece of code that will be the catch block. This one can be null, then errors and exceptions will be ignored silently.
 * @param callback a piece of code to be executed after the try/catch blocks no matter if there is error or not. Can be null.
 * @return mixed the result of the last block that was executed
 * <code>
 * _try(function() {
 * 	//try code
 * 	//something that may produce an error
 * 	//but shouldn't crash the script
 * 	$a = 5 / 0;
 * }, function($exception) {
 * 	//catch code. will catch all exceptions and php errors, except for syntax errors
 * 	echo $exception->getMessage(), "\n";
 * 	if($exception instanceof ErrorException) {
 * 		//a PHP error
 * 		//if we encounter one our script
 * 		//will terminate after the finally block
 * 	}
 * 	else if($exception instanceof InvalidArgumentException) {
 * 		//what we want to handle
 * 	}
 * 	else {
 * 		//we can re-throw the exception if we want
 * 		//our finally block will execute before
 * 		//the exception leaves our _try()
 * 		throw $exception;
 * 	}
 * }, function() {
 * 	//finally code
 * 	//do clean up here
 * });
 * </code>
 * @author bobef <borislav.asdf@gmail.com>
 */
function _try($try, $catch = null, $finally = null) {
	return _TryCatchFinally::nest($try, $catch, $finally)->enter();
}

/**
 * @access private
 */
class _TryCatchFinally {

	private static $tail = null;
	
	static function nest($try, $catch, $finally) {
	
		if(!is_callable($try)) throw new Exception('try block not callable');
	
		$level = new _TryCatchFinally($try, $catch, $finally);
		if(self::$tail === null) self::$tail = $level;
		else {
			$level->prev = self::$tail;
			$level->level = $level->prev->level + 1;
			self::$tail->next = $level;
			self::$tail = $level;
		}
		return self::$tail;
	}
	
	public $prev = null;
	public $next = null;
	public $level = 0;
	
	private $_try = null;
	private $_catch = null;
	private $_finally = null;
	
	public $_caught = false;
	public $_finalized = false;

	private $_shutdownId = null;
	
	private $_ret = null;
	private $_rethrow = null;
	
	private function __construct($try, $catch, $finally) {
		$this->_try = $try;
		$this->_catch = $catch;
		$this->_finally = $finally;
	}
	
	/*
	function debug() {
		$args = func_get_args();
		if($this->level > 0) echo str_repeat('  ', $this->level);
		foreach($args as $arg) echo $arg;
		echo ' #', $this->_shutdownId , "\n";
	}
	//*/
	
	function enter() {
		
		$_this = $this;
	
		$onError = function($errno, $errstr, $errfile, $errline) use($_this) {
			if($_this->_caught) {
				//it is possible to have error inside the the catch block
				//in this case we are ignoring it so we can continue to our finally block
				return;
			}
			//$_this->debug('entering error handler');

			$e = new ErrorException($errstr, 0, $errno, $errfile, $errline);
			//$_this->debug('error: ', $e->getMessage(), ': ', $e->getFile(), '@', $e->getLine());
			$_this->catchFinally(false, $e);
			
			exit;
		};
		
		$onFatalError = function() use($_this) {
			if($_this->_finalized) {
				trigger_error('The flow should never get here. Please file a bug. Debug: 2');
				exit;
			}
			//$_this->debug('entering shutdown callback');
			
			$caught = null;
			$err = error_get_last();
			if($err !== null && $err['type'] != E_ERROR) {
				trigger_error('The flow should never get here. Please file a bug. Debug: ' . json_encode($err));
				exit;
			}
			
			$e = $err ? new ErrorException($err['message'], 0, $err['type'], $err['file'], $err['line']) : null;
			//$_this->debug('error: ', $e->getMessage(), ': ', $e->getFile(), '@', $e->getLine());
			$_this->catchFinally(false, $e);
		};

		set_error_handler($onError);
		$this->_shutdownId = ShutdownCallback::add($onFatalError);
		
		$caught = null;
		try {
			//$this->debug('entering try');
			$this->_ret = call_user_func($this->_try);
		}
		catch(Exception $e) {
			$caught = $e;
		}
		
		restore_error_handler();
		return $this->catchFinally(true, $caught);
	}
	
	function catchFinally($throw, $caught) {
	
		//perform the catch block once, if there is an error
		if($caught) {
			if(!$this->_caught) {
				$this->_caught = true;
				if(is_callable($this->_catch)) {
					try {
						//$this->debug('entering catch');
						$this->_ret = call_user_func($this->_catch, $caught);
					}
					catch(Exception $e) {
						$this->_rethrow = $e;
					}
				}
				//else {if we don't have a catch block just ignore the error silently}
			}
			else $this->_rethrow = $caught;
		}
		else {
			//don't use the catcher anymore
			$this->_caught = true;
		}
		
		//perform the finally block once
		if(!$this->_finalized) {
			$this->_finalized = true;
			ShutdownCallback::remove($this->_shutdownId);
			if(is_callable($this->_finally)) {
				//$this->debug('entering finally');
				$this->_ret = call_user_func($this->_finally);
			}
		}
		
		return $this->bubble($throw, $this->_rethrow);
	}
	
	//recursively call outer catch/finally blocks
	//rethrow only in case this is not called from error handler or shutdown callback
	//i.e. only in case the program flow will continue
	private function bubble($throw, $caught) {
		$tail = self::$tail = $this->prev;
		if($tail) $tail->next = null;
		$this->prev = $this->next = null;
	
		//if we are not comming from one of the error handlers
		//and if we have nothing to throw, then we should exit the
		//_try() block normally without bubbling all the way to the top
		if($throw && $this->_rethrow === null) return $this->_ret;
		
		if($tail) {
			return $tail->catchFinally($throw, $this->_rethrow);
		}
		else {
			if($throw && $this->_rethrow) {
				throw $this->_rethrow;
			}
			else {
				return $this->_ret;
			}
		}
	}
	
}

?>