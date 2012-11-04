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
 * This class encapsulates PHP's {@link http://php.net/manual/en/function.register-shutdown-function.php register_shutdown_function()} functionality adding the ability to disable callbacks.
 * You should be aware of the peculiarities of the shutdown functions, for example they change the working directory.
 * The list of registered callbacks will be executed in reverse order when the script terminates.
 * <code>
 * ShutdownCallback::add(function() {
 * 	//do something at exit
 * });
 * </code>
 * @author bobef <borislav.asdf@gmail.com>
 */
class ShutdownCallback {
	
	private static $callbacks = array();
	private static $args = array();
	
	/**
	 * @access private
	 */
	static function onShutDown() {
		$i = count(self::$callbacks) - 1;
		for(; $i >= 0; --$i) {
			$fn = self::$callbacks[$i];
			if($fn) {
				$args = self::$args[$i];
				if($args !== null) call_user_func_array($fn, $args);
				else call_user_func($fn);
			}
		}
	}
	
	/**
	 * Removes a registered callback.
	 * @param int the id returned by {@link add()}
	 * @return void
	 */
	static function remove($id) {
		self::$callbacks[$id] = null;
		self::$args[$id] = null;
	}

	/**
	 * Registers a callback with the shutdown scheduler.
	 * This function accepts variable number of arguments.
	 * All arguments after the first one will be passed
	 * to the callback when it is called.
	 * @param callback
	 * @return int id of the newly registered callback. Pass this to {@link remove()} to remove the callback.
	 */
	static function add($fn) {
		$id = count(self::$callbacks);
		self::$callbacks[] = $fn;
		if(func_get_args() > 1) {
			$args = func_get_args();
			self::$args[] = array_slice($args, 1);
		}
		else self::$args[] = null;
		if($id === 0) register_shutdown_function('ShutdownCallback::onShutDown');
		return $id;
	}
}

?>