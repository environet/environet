<?php


namespace Environet\Sys\General\HttpClient;

use Environet\Sys\General\Exceptions\PermissionException;
use Environet\Sys\General\Identity;
use Environet\Sys\General\Request;

/**
 * Class BaseHandler
 *
 * Base class for handler inheritance
 *
 * @package Environet\Sys\General\HttpClient
 * @author  SRG Group <dev@srg.hu>
 */
abstract class BaseHandler implements RequestHandlerInterface {

	/**
	 * Permission type for this handler, which will be checked against the current identity.
	 */
	protected const HANDLER_PERMISSION = '';

	/**
	 * The request instance, which represents the current request.
	 * @var Request
	 */
	protected $request;


	/**
	 * BaseHandler constructor.
	 *
	 * Sets the internal request property.
	 *
	 * @param Request $request
	 */
	public function __construct(Request $request) {
		$this->request = $request;
	}


	/**
	 * Base method to get the Identity object, which contains the current user's information.
	 *
	 * @return Identity|null
	 */
	abstract protected function getIdentity(): ?Identity;


	/**
	 * Base method for checking user and group permissions against the current action.
	 *
	 * @param array $requiredPermissions
	 */
	abstract protected function authorizeRequest(array $requiredPermissions = []);


}
