<?php


namespace Environet\Sys\General\View;

use Environet\Sys\General\Exceptions\RenderException;
use Environet\Sys\General\Response;

/**
 * Class Renderer
 *
 * A simple phtml-based view renderer with separated scope, and template management
 *
 * @package Environet\Sys\General\View
 * @author  Ádám Bálint <adam.balint@srg.hu>
 */
class Renderer {

	/**
	 * @var array Root paths, where templates will be found
	 */
	protected static $rootPaths = [];

	/**
	 * Template path (absolute, or relative to a rootPath)
	 * @var string
	 */
	private $template;

	/**
	 * Array of variables of view
	 * @var array
	 */
	private $vars;


	/**
	 * Renderer constructor.
	 *
	 * Set template, variables, and add global variables
	 *
	 * @param string|null $template
	 * @param array|null  $vars
	 *
	 * @throws RenderException
	 */
	public function __construct(string $template = null, array $vars = null) {
		if (!is_null($template)) {
			//Set template path
			$this->setTemplate($template);
		}
		if (!is_null($vars)) {
			//Set variables
			$this->setVars($vars);
		}

		//Add request to all view
		global $request;
		$this->addVar('request', $request);
	}


	/**
	 * Add a template root path as a static property
	 *
	 * @param string $rootPath
	 */
	public static function addRootPath(string $rootPath) {
		self::$rootPaths[] = $rootPath;
		self::$rootPaths = array_unique(self::$rootPaths);
	}


	/**
	 * Get template path
	 *
	 * @return string
	 */
	public function getTemplate(): string {
		return $this->template;
	}


	/**
	 * Resolve, and set the template path. If it's an absolute path, and the file exists, it will be set directly as a path.
	 * It not, renderer will find the path under the root paths.
	 *
	 * @param string $template Absolute or root-relative path
	 *
	 * @return Renderer
	 * @throws RenderException
	 */
	public function setTemplate(string $template): Renderer {
		$foundTemplate = null;

		if (file_exists($template)) {
			//Absolute, existing path
			$foundTemplate = $template;
		} else {
			//Relative, or not existing path
			$template = ltrim($template, '/');

			//Check under each root paths
			foreach (self::$rootPaths as $rootPath) {
				if (file_exists($rootPath . '/' . $template)) {
					$foundTemplate = $rootPath . '/' . $template;
					break;
				}
			}
		}

		//A valid template could not be found
		if (!$foundTemplate) {
			throw new RenderException('Template \'' . $template . '\'not found');
		}

		$this->template = $foundTemplate;

		return $this;
	}


	/**
	 * Get template variables
	 *
	 * @return array
	 */
	public function getVars(): array {
		return $this->vars;
	}


	/**
	 * Set array of template variables (variablename => value)
	 *
	 * @param array $vars
	 *
	 * @return Renderer
	 */
	public function setVars(array $vars): Renderer {
		$this->vars = $vars;

		return $this;
	}


	/**
	 * Add a template variable by key and value
	 *
	 * @param string $key
	 * @param mixed  $value
	 *
	 * @return Renderer
	 */
	public function addVar(string $key, $value): Renderer {
		$this->vars[$key] = $value;

		return $this;
	}


	/**
	 * Render a template, and return the rendered contents.
	 * It throws and exception if the template is not found.
	 *
	 * @return Response
	 * @throws RenderException
	 */
	public function render(): Response {
		if (!$this->template) {
			throw new RenderException('Template not found');
		}

		//Echo the template to a bufffer
		ob_start();
		extract($this->vars);
		include $this->template;
		$contents = ob_get_contents();
		ob_end_clean();

		//Return the response with contents
		return new Response($contents);
	}


	/**
	 * With Invoke the render method is called
	 *
	 * @return Response
	 * @throws RenderException
	 */
	public function __invoke(): Response {
		return $this->render();
	}


}
