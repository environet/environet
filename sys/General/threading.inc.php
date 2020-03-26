<?php
/*!
 * \file threading.inc.php
 *
 * \author Levente Peres - VIZITERV Environ Kft.
 * \date
 *
 * @package Environet
 */

/*!
 * \class EN_AsyncExecute
 * \brief Implement thread-safe multi-threaded function execution support in Environet
 *
 * This class depends on the PECL pthreads PHP library and requires that PHP be compiled in ZTS support mode.
 *
 * It attempts to start and manage threads based on user functions.
 *
 * If you need to call a simple function, put the name of the function into
 *
 * @public array $arrays
 *
 */
class EN_AsyncExecute extends Thread {

    public $arguments; //!< Parameters to be passed to the function contained in an array.

    public $funcname; //!< The name of the function or an array containing the object and the function

    public $data; //!< This will contain the return data of the function called.

    public function __construct($parameters) {
        $this->arguments = $parameters[1];
        $this->funcname = $parameters[0];
    }

    public function run() {
        if (($params = $this->arguments)) {
            $this->data = call_user_func($this->funcname, $this->arguments);
        }
        else {
            en_debug("Thread #%lu was not given an array of arguments\n", $this->getThreadId());
        }

    }
}
