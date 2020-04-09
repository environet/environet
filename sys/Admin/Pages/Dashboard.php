<?php


namespace Environet\Sys\Admin\Pages;

use Environet\Sys\General\Exceptions\RenderException;
use Environet\Sys\General\Response;

/**
 * Class Dashboard
 *
 * Displays the dashboard page
 *
 * @package Environet\Sys\Admin\Pages
 * @author  SRG Group <dev@srg.hu>
 */
class Dashboard extends BasePage {


	/**
	 * Render the dashboard page
	 *
	 * @return Response
	 * @throws RenderException
	 */
	public function handle(): Response {
		return $this->render('/dashboard.phtml');
	}


}
