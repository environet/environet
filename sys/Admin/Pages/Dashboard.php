<?php


namespace Environet\Sys\Admin\Pages;

use Environet\Sys\General\Exceptions\RenderException;
use Environet\Sys\General\Response;
use Environet\Sys\General\View\Renderer;

/**
 * Class Dashboard
 *
 * Display dasboard page
 *
 * @package Environet\Sys\Admin\Pages
 * @author  Ádám Bálint <adam.balint@srg.hu>
 */
class Dashboard extends BasePage {


	/**
	 * Render dashboard page
	 *
	 * @return Response
	 * @throws RenderException
	 */
	public function handle(): Response {
		return $this->render('/dashboard.phtml');
	}


}
