<?php


namespace Environet\Sys\Admin\Pages;

use Environet\Sys\Admin\AdminHandler;
use Environet\Sys\General\EventLogger;
use Environet\Sys\General\Exceptions\HttpBadRequestException;
use Environet\Sys\General\Response;
use Exception;

/**
 * Class Logout
 *
 * Handle logout page requests
 *
 * @package Environet\Sys\Admin\Pages
 * @author  Ádám Bálint <adam.balint@srg.hu>
 */
class Logout extends BasePage {


	/**
	 * Handle logout request.
	 *
	 * @return mixed|void
	 */
	public function handle(): ?Response {

		if ($this->request->isPost()) {
			if (!$this->checkCsrf()) {
				return new HttpBadRequestException();
			}

			try {
				// log logout event
				EventLogger::log(EventLogger::EVENT_TYPE_LOGOUT, null);

				//Remove auth session
				unset($_SESSION[AdminHandler::AUTH_SESSION_KEY]);

				//Redirect to admin main page
				return httpRedirect('/admin/login');
			} catch (Exception $e) {
				$this->addMessage('Error during logout. Please try again');
			}
		}
	}


}
