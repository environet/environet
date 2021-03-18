<?php


namespace Environet\Sys\Admin\Pages;

use Environet\Sys\Admin\AdminHandler;
use Environet\Sys\General\Db\Query\Query;
use Environet\Sys\General\Db\Query\Select;
use Environet\Sys\General\Db\Query\Update;
use Environet\Sys\General\EventLogger;
use Environet\Sys\General\Exceptions\HttpBadRequestException;
use Environet\Sys\General\Exceptions\QueryException;
use Environet\Sys\General\Exceptions\RenderException;
use Environet\Sys\General\Identity;
use Environet\Sys\General\Request;
use Environet\Sys\General\Response;
use Exception;

/**
 * Class Login
 *
 * Handle login page requests
 * For GET requests it creates the login page with the form.
 * If the request is POST, it checks the username, password, and store logged in state in session
 *
 * @package Environet\Sys\Admin\Pages
 * @author  SRG Group <dev@srg.hu>
 */
class Login extends BasePage {


	/**
	 * Handle the login request.
	 *
	 * Updates last login time and creates log entry if the authentication is successful.
	 * Renders the login page otherwise.
	 *
	 * @return mixed|void
	 * @throws RenderException
	 * @throws HttpBadRequestException
	 * @throws QueryException
	 * @uses \Environet\Sys\Admin\Pages\Login::checkCsrf()
	 * @uses \Environet\Sys\Admin\Pages\Login::handleLogin()
	 * @uses \Environet\Sys\Admin\Pages\Login::logLoginDetails()
	 * @uses \Environet\Sys\Admin\Pages\Login::messagesToSession()
	 * @uses \Environet\Sys\Admin\Pages\Login::render()
	 * @uses \httpRedirect()
	 */
	public function handle(): ?Response {
		if($this->request->getIdentity()) {
			return $this->redirect('/admin');
		}

		if ($this->request->isPost()) {
			//Posted form, check if login credentials are valid
			if (!$this->checkCsrf()) {
				throw new HttpBadRequestException();
			}
			if (($userId = $this->handleLogin())) {
				try {
					//Update the loggedin_at date of user
					(new Update())
						->table('users')
						->where('id = :userId')
						->addSet('loggedin_at', ':loggedInAt')
						->setParameters([
							':userId'     => $userId,
							':loggedInAt' => date('Y-m-d H:i:s')
						])
						->run();

					//Store user id in session
					$_SESSION[Request::AUTH_SESSION_KEY] = $userId;

					// log event if login was success
					$this->logLoginDetails(EventLogger::EVENT_TYPE_LOGIN);

					//Redirect to admin main page
					return httpRedirect('/admin');
				} catch (Exception $e) {
					$this->addMessage('Error during login. Please try again');
				}
			} else {
				// log event if login was failed
				$this->logLoginDetails(EventLogger::EVENT_TYPE_LOGIN_ATTEMPT);
			}
			$this->messagesToSession();

			//Redirect back to login page
			return httpRedirect('/admin/login');
		}

		return $this->render('/login.phtml');
	}


	/**
	 * Handle login POST request
	 *
	 * Check validity of form. It checks the credentials (username, password)
	 * If everything is valid, it returns the user id. If not, the response is false.
	 *
	 * @return bool|int User id, or false
	 */
	protected function handleLogin() {
		if (empty($_POST['username']) || empty($_POST['password'])) {
			$this->addMessage('Username and password are required!', self::MESSAGE_ERROR);

			return false;
		}

		try {
			$user = (new Select())
				->from('users')
				->where('username = :username')
				->where('deleted_at IS NULL')
				->addParameter('username', $_POST['username'])
				->limit(1)
				->run(Query::FETCH_FIRST);
		} catch (Exception $e) {
			$this->addMessage('Can\'t validate user credentials', self::MESSAGE_ERROR);

			return false;
		}

		if (!$user) {
			$this->addMessage('Credentials are invalid', self::MESSAGE_ERROR);

			return false;
		}

		if (!password_verify($_POST['password'], ($user['password'] ?? null))) {
			$this->addMessage('Credentials are invalid', self::MESSAGE_ERROR);

			return false;
		}

		$identity = new Identity($user['id'], $user);
		if (!$identity->hasPermissions(['admin.login'])) {
			$this->addMessage('Login disabled', self::MESSAGE_ERROR);

			return false;
		}

		return $user['id'];
	}


	/**
	 * Log login event data.
	 *
	 * @param string $status
	 *
	 * @throws QueryException
	 * @uses \Environet\Sys\General\EventLogger::log()
	 */
	protected function logLoginDetails(string $status) {
		EventLogger::log($status, [
			'username' => $_POST['username']
		]);
	}


}
