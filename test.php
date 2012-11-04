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

require_once 'ShutdownCallback.php';
require_once 'TryCatch.php';

header('Content-Type: text/plain');
ini_set('display_errors', 'Off');

class MyException extends Exception {
}

//catching custom exceptions
//the catch and finally blocks are optional, we can use _try just to silince the error
$ret = _try(

	//try code
	function() {
		echo 'trying some code that throws', "\n";
		throw new MyException('My very own exception');
	},

	//catch code. will catch all exceptions and php errors, except for syntax errors
	function($exception) {
		echo 'caught error: ', $exception->getMessage(), "\n";
		if($exception instanceof MyException) {
			//if we want to handle specific type of exception
			//we need to check the class of the exception
		}
		else {
			//we can re-throw the exception if we want
			//our finally block will execute before
			//the exception leaves our _try()
			throw $exception;
		}
	},

	//finally code
	function() {
		//do clean up here
		echo 'finally 1...', "\n";
		return 'returnvalue';
	}
);

echo 'continuing script exectuion after the exception... we can use the return value of the last executed block - in this case the finally block - ', $ret, "\n";

//catching non fatal errors
_try(

	//try code
	function() {
		//something that may produce an error
		//but shouldn't crash the script
		echo 'trying division by zero', "\n";
		$a = 5 / 0;
	},

	//catch code. will catch all exceptions and php errors, except for syntax errors
	function($exception) {
		echo 'caught error: ', $exception->getMessage(), "\n";
		if($exception instanceof ErrorException) {
			//a PHP error
			//if we encounter one our script
			//will terminate after the finally block
			echo 'oops, a PHP error, check your code. the script will terminate after the finally block', "\n";
			
			//we can even nest _try blocks. lets cause a fatal error on purpose
			echo 'causing a fatal error...', "\n";
			_try(function() {
				$a = new stdClass();
				$a->method();
			}, function($exception) {
				if($exception instanceof ErrorException && $exception->getSeverity() == E_ERROR) {
					echo 'great, we are catching fatal errors', "\n";
				}
			}, function() {
				echo 'finally 2...', "\n";
			});
		}
	},

	//finally code
	function() {
		//do clean up here
		echo 'finally 3...', "\n";
	}
);

echo 'this code won\'t execute because of the php errors, but the finally blocks will execute';

?>