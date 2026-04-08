<?php

namespace Environet\Sys\Admin\Pages;

use Environet\Sys\General\Exceptions\RenderException;
use Environet\Sys\General\Request;
use Environet\Sys\General\Response;
use Environet\Sys\General\View\Renderer;

/**
 * Class BasePage
 *
 * Abstract class for admin area page handlers
 *
 * @package Environet\Sys\Admin\Pages
 * @author  SRG Group <dev@srg.hu>
 */
class BasePage {


	public const string MESSAGE_ERROR   = 'error';
	public const string MESSAGE_WARNING = 'warning';
	public const string MESSAGE_INFO    = 'info';
	public const string MESSAGE_SUCCESS = 'success';

	public const int PAGE_SIZE = 10;

	/**
	 * The request instance which represents the current request
	 * @var Request
	 */
	protected $request;

	/**
	 * Page message containers
	 * @var array
	 */
	protected $messages = [
		self::MESSAGE_ERROR   => [],
		self::MESSAGE_WARNING => [],
		self::MESSAGE_INFO    => [],
		self::MESSAGE_SUCCESS => []
	];

	/**
	 * Page message containers
	 * @var array
	 */
	protected $fieldMessages = [];


	/**
	 * AbstractPage constructor.
	 *
	 * Request is a required parameter for page handlers
	 *
	 *
	 * @uses BasePage::readMessagesFromSession
	 */
	public function __construct(Request $request) {
		$this->request = $request;
		$this->readMessagesFromSession();
	}


	/**
	 * Render page.
	 *
	 * Render template with global variables extended by extra supplied ones
	 *
	 *
	 * @throws RenderException
	 * @uses Request::getIdentity
	 * @uses \Environet\Sys\General\Identity::getData()
	 * @uses BasePage::generateCsrf
	 */
	public function render(string $template, array $vars = []): Response {
		$vars['messages'] = $this->messages;
		$vars['fieldMessages'] = $this->fieldMessages;
		$vars['identity'] = !$this->request->getIdentity() ?: $this->request->getIdentity()->getData();
		$vars['csrf'] = $this->generateCsrf();

		return new Renderer($template, $vars)();
	}


	/**
	 * Redirect user to a specified uri.
	 *
	 *
	 * @uses BasePage::messagesToSession
	 * @uses \httpRedirect()
	 */
	protected function redirect(string $uri): Response {
		$this->messagesToSession();

		return httpRedirect($uri);
	}


	/**
	 * Redirect user back
	 *
	 *
	 * @return Response
	 * @uses Request::getReferer
	 * @uses BasePage::redirect
	 */
	protected function redirectBack(string $defaultUri) {
		$redirectUrl = $this->request->getReferer();
		if (is_null($redirectUrl)) {
			$redirectUrl = $defaultUri;
		}

		return $this->redirect($redirectUrl);
	}


	/**
	 * Pull messages from session and fill the local messages variable.
	 */
	private function readMessagesFromSession() {
		if (!empty($_SESSION['messages'])) {
			$this->messages = array_merge($this->messages, $_SESSION['messages']);

			//Messages should stay in session only for 1 request, so remove it from the session
			unset($_SESSION['messages']);
		}
		if (!empty($_SESSION['fieldMessages'])) {
			$this->fieldMessages = array_merge($this->fieldMessages, $_SESSION['fieldMessages']);

			//Messages should stay in session only for 1 request, so remove it from the session
			unset($_SESSION['fieldMessages']);
		}
	}


	/**
	 * Generate a CSRF token and store it in session
	 *
	 * @uses \NCSRandStr()
	 */
	protected function generateCsrf(): string {
		$csrf = NCSRandStr();
		$_SESSION['__csrf'] = $csrf;

		return $csrf;
	}


	/**
	 * Check CSRF token in post body. Request is valid only if the value in the body is the same as in session
	 */
	protected function checkCsrf(): bool {
		return (
			!empty($_POST['__csrf']) &&
			!empty($_SESSION['__csrf']) &&
			$_SESSION['__csrf'] === $_POST['__csrf']
		);
	}


	/**
	 * Add an error, warning, info or success message.
	 *
	 * @param string $message Message string
	 * @param string $type    Type of the message
	 */
	protected function addMessage(string $message, $type = self::MESSAGE_INFO) {
		$this->messages[$type][] = $message;
	}


	/**
	 * Add an error, warning, info or success message for a form field
	 *
	 * @param string $message Message string
	 * @param string $type    Type of the message
	 */
	protected function addFieldMessage(string $field, string $message, $type = self::MESSAGE_INFO) {
		if (!isset($this->fieldMessages[$field][$type])) {
			$this->fieldMessages[$field][$type] = [];
		}
		$this->fieldMessages[$field][$type][] = $message;
	}


	/**
	 * Add flash messages to session.
	 *
	 * With this we can display messages after a redirect.
	 * After redirect an other page handler will read, and clear it.
	 */
	protected function messagesToSession() {
		$_SESSION['messages'] = $this->messages;
		$_SESSION['fieldMessages'] = $this->fieldMessages;
	}


}
