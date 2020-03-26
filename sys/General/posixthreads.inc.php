<?php
/*!
 * \file posixthreads.inc.php
 *
 * \author Levente Peres - VIZITERV Environ Kft.
 * \date
 *
 * POSIX threading support library
 *
 * @package Environet
 */

/*!
 * \class ENThread
 * \brief Class for running user functions as forked instances and monitors their state, reports on their outputs.
 *
 * @todo Error handling
 *
 */
class ENThread extends en_connection
{
    const FUNCTION_NOT_CALLABLE = 10;
    const COULD_NOT_FORK        = 15;

    public $uniqueID;

    public $initdone=false; //!< As long as this is false, the initialization has not been completed.

    /**
     * We define some common error causes
     *
     * @var array
     */
    private $_errors = array(
        ENThread::FUNCTION_NOT_CALLABLE => 'A valid function name is required.',
        ENThread::COULD_NOT_FORK        => 'pcntl_fork() returned with error.'
    );

    /**
     * Callback for the function that should run as a separate thread
     *
     * @var callback
     */
    protected $runnable;


    /**
     * Holds the current process id
     *
     * @var integer
     */
    private $_pid;


    /**
     * Exits with error
     *
     * @return void
    */

    private function fatalError($errorCode){
        throw new Exception( $this->getError($errorCode) );
    }

    /**
     * Checks if threading is supported by the current PHP configuration
     *
     * @return boolean
     */
    public static function isAvailable()
    {
        $required_functions = array(
            'pcntl_fork',
        );

        foreach ( $required_functions as $function ) {
            if ( !function_exists($function) ) {
                return false;
            }
        }

        return true;
    }


    /**
     * Class destructor
     *
     * {@inheritDoc}
     * @see en_connection::__destruct()
     */
    function __destruct() {
        if (!$this->con instanceof en_connection) $this->connect();

        if (!empty($this->_pid)) {
            $query='DELETE FROM en_threadstate WHERE thread=?;';
            $prep_delete = $this->con->prepare($query);

            en_debug("PosixThreads [PID# ".$this->_pid."] Destroying SQL entry with ID - ".$this->uniqueID);

            $prep_delete->execute(array($this->uniqueID));
        }

        //! @todo Implement error handling

        parent::__destruct();
    }

    /**
     * Class constructor - you can pass
     * the callback function as an argument
     *
     * @param callback $runnable Callback reference
     */
    public function __construct( $runnable = null )
    {
        parent::__construct();

        if(!ENThread::isAvailable() )throw new Exception("Threads not supported");
        if ( $runnable !== null ) {
            $this->setRunnable($runnable);

            $this->uniqueID=NCSRandStr();

            $query='INSERT INTO en_threadstate VALUES ( ?, ?);';
            $prep_insert = $this->con->prepare($query);

            en_debug("PosixThreads [PID# ".$this->_pid."] Creating nwe SQL entry for thread state communication with ID - ".$this->uniqueID);

            //! @todo Implement error handling
            $prep_insert->execute(array($this->uniqueID,"-1"));

            $this->initdone=true; //!< We flag the init complete signal.
            }
    }

    /**
     * Sets the callback
     *
     * @param callback $runnable Callback reference
     *
     * @return callback
     */
    public function setRunnable( $runnable )
    {
        if ( self::isRunnableOk($runnable) ) {
            $this->runnable = $runnable;
        } else {
            $this->fatalError(ENThread::FUNCTION_NOT_CALLABLE);
        }
    }

    /**
     * Gets the callback
     *
     * @return callback
     */
    public function getRunnable()
    {
        return $this->runnable;
    }

    /**
     * Checks if the callback is ok (the function/method
     * is runnable from the current context)
     *
     * can be called statically
     *
     * @param callback $runnable Callback
     *
     * @return boolean
     */
    public static function isRunnableOk( $runnable )
    {
        return ( is_callable($runnable) );
    }

    /**
     * Returns the process id (pid) of the simulated thread
     *
     * @return int
     */
    public function getPid()
    {
        return $this->_pid;
    }

    /**
     * Checks if the child thread is alive
     *
     * @return boolean
     */
    public function isAlive()
    {
        $pid = pcntl_waitpid($this->_pid, $status, WNOHANG);
        return ( $pid === 0 );

    }

    /**
     * Returns the return value of the finished thread function.
     *
     * @return mixed|string
     */
    public function getResult() {

        if (!$this->con instanceof en_connection) $this->connect();

        $query='SELECT * FROM en_threadstate WHERE thread=?;';
        $prep_search = $this->con->prepare($query);
        $prep_search->execute(array($this->uniqueID));

        en_debug("PosixThreads [PID# ".$this->_pid."] returning current result from SQL with ID - ".$this->uniqueID);

        if ($prep_search->rowCount() > 0) {
            $row=$prep_search->fetchAll();
            if ($row[0]['state']!=="-1") {
                return unserialize(base64_decode($row[0]['state']));
            }
            else {
                return "-1"; //!< Return -1 if we don't yet have a result
            }
        }
        else {
            return "-2"; //!< We have a problem. No such entry???
        }

    }

    /**
     * Starts the thread, all the parameters are
     * passed to the callback function
     *
     * @return void
     */
    public function start()
    {
        $pid = @pcntl_fork();
        if ( $pid == -1 ) {
                $this->fatalError(ENThread::COULD_NOT_FORK);
        }
        if ( $pid ) {
            // parent
            $this->_pid = $pid;
        } else {
            // child
            pcntl_signal(SIGTERM, array( $this, 'handleSignal' ));
            $arguments = func_get_args();
            $retdata="";
            if ( !empty($arguments) ) {
                $retdata=call_user_func_array($this->runnable, $arguments);
            } else {
                $retdata=call_user_func($this->runnable);
            }

            $this->connect();

            $query="UPDATE en_threadstate SET state=? WHERE thread=?;";
            $prep_update = $this->con->prepare($query);
            $prep_update->execute(array(base64_encode(serialize($retdata)),$this->uniqueID));

            en_debug("PosixThreads [PID# ".$this->_pid."] Updating SQL entry for thread state communication with ID - ".$this->uniqueID);

            //! @todo Implement error handling

            exit( 0 );
        }
    }

    /**
     * Attempts to stop the thread
     * returns true on success and false otherwise
     *
     * @param integer $signal SIGKILL or SIGTERM
     * @param boolean $wait   Wait until child has exited
     *
     * @return void
     */
    public function stop( $signal = SIGKILL, $wait = false )
    {
        if ( $this->isAlive() ) {
            posix_kill($this->_pid, $signal);
            if ( $wait ) {
                pcntl_waitpid($this->_pid, $status = 0);
            }
        }
    }

    /**
     * Alias of stop();
     *
     * @param integer $signal SIGKILL or SIGTERM
     * @param boolean $wait   Wait until child has exited
     *
     * @return void
     */
    public function kill( $signal = SIGKILL, $wait = false )
    {
        return $this->stop($signal, $wait);
    }

    /**
     * Gets the error's message based on its id
     *
     * @param integer $code The error code
     *
     * @return string
     */
    public function getError( $code )
    {
        if ( isset( $this->_errors[$code] ) ) {
            return $this->_errors[$code];
        } else {
            return "No such error code $code ! Quit inventing errors!!!";
        }
    }

    /**
     * Signal handler
     *
     * @param integer $signal Signal
     *
     * @return void
     */
    protected function handleSignal( $signal )
    {
        switch( $signal ) {
        case SIGTERM:
            exit( 0 );
            break;
        }
    }
}
