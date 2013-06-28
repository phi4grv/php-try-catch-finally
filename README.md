Advanced error handling in PHP
==============================
PHP has several methods of handling errors and it makes distinction between error handling and exception handling. Additionally its error handling mechanism doesn't provide a way of handling "fatal errors", which will always crash the script. I'm presenting a convenient solution to handle all types of errors, including fatal ones, and exceptions with only few lines of code.<br />
Here is how it works. Instead of using try/catch and setting an error handler we do it all at once. Oh, and by the way, the trick in catching fatal errors is by using shutdown callbacks. The latter execute even after fatal errors. So nornally in PHP if we want to handle all of these we need a try/catch block, an error handler and a shutdown callback. And if we want to nest these the mess becomes even bigger. Check the code bellow.

In short all we need to do is this - a few lines of code. This is PHP 5.3, it will work with PHP 5.2 but may need some tweaks and you can't use closures there.
```php
_try(
    //some piece of code that will be our try block
    function() {
    },
     
    //some (optional) piece of code that will be our catch block
    function($exception) {
    },
     
    //some (optional) piece of code that will be our finally block
    function() {
    }
);
```

Lets see how to really use it.
```php
//you can merge these two if you don't want to include two files
require_once 'ShutdownCallback.php';
require_once 'TryCatch.php';
 
//we are displaying text in our example
header('Content-Type: text/plain');
 
//disable php eror display
ini_set('display_errors', 'Off');
 
//just some custom exception
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
 
echo 'continuing script exectuion after the exception...', "\n";
echo 'we can use the return value of the last executed block - in this case the finally block - ', $ret, "\n";
 
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
```

The above example will output this.
<pre>
trying some code that throws
caught error: My very own exception
finally 1...
continuing script exectuion after the exception...
we can use the return value of the last executed block - in this case the finally block - returnvalue
trying division by zero
caught error: Division by zero
oops, a PHP error, check your code. the script will terminate after the finally block
causing a fatal error...
great, we are catching fatal errors
finally 2...
finally 3...
</pre>
