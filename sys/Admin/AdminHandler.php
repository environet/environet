<?php

namespace Environet\Sys\Admin;

use Environet\Sys\Admin\Pages\BasePage;
use Environet\Sys\Admin\Pages\Dashboard;
use Environet\Sys\Admin\Pages\DataProvider\DataProviderCrud;
use Environet\Sys\Admin\Pages\DownloadTest;
use Environet\Sys\Admin\Pages\Group\GroupCrud;
use Environet\Sys\Admin\Pages\Hydro\ObservedProperty\ObservedPropertyCrud as HydroObservedPropertyCrud;
use Environet\Sys\Admin\Pages\Hydro\ResultsCrud as HydroResultsCrud;
use Environet\Sys\Admin\Pages\Meteo\ResultsCrud as MeteoResultsCrud;
use Environet\Sys\Admin\Pages\Meteo\ObservedPropertyCrud as MeteoObservedPropertyCrud;

use Environet\Sys\Admin\Pages\Hydro\Waterbody\WaterbodyCrud;

use Environet\Sys\Admin\Pages\Hydro\StationClassificationCrud as HydroStationClassificationCrud;
use Environet\Sys\Admin\Pages\Meteo\StationClassificationCrud as MeteoStationClassificationCrud;

use Environet\Sys\Admin\Pages\Hydro\MonitoringPointCrud as HydroMonitoringPointCrud;
use Environet\Sys\Admin\Pages\Meteo\MonitoringPointCrud as MeteoMonitoringPointCrud;

use Environet\Sys\Admin\Pages\Login;
use Environet\Sys\Admin\Pages\Logout;
use Environet\Sys\Admin\Pages\UploadTest;
use Environet\Sys\Admin\Pages\User\UserCrud;

use Environet\Sys\General\HttpClient\BaseHandler;
use Environet\Sys\General\Identity;
use Environet\Sys\General\View\Renderer;

use Environet\Sys\General\Exceptions\PermissionException;
use Environet\Sys\General\Exceptions\HttpNotFoundException;
use Environet\Sys\General\Exceptions\HttpBadRequestException;
use Environet\Sys\General\Exceptions\RenderException;

/**
 * Class AdminHandler
 *
 * The core entry-point for administration area requests.
 * It is also a router, which forward the request to a page handler based on the url path
 *
 * @package Environet\Sys\Admin
 * @author  Ádám Bálint <adam.balint@srg.hu>
 */
class AdminHandler extends BaseHandler {

	/** @inheritDoc */
	const HANDLER_PERMISSION = 'admin.all';

	//Session key of admin auth
	const AUTH_SESSION_KEY = 'adminauth';

	/**
	 * @var string Base path for templates
	 */
	public static $templatePath = SRC_PATH . '/sys/Admin/templates';


	/**
	 * @inheritDoc
	 */
	protected function getIdentity(): ?Identity {
		if (!$this->request->getIdentity()) {
			//Set identity for request if has the session value, and if user found based on this id
			if (!empty($_SESSION[self::AUTH_SESSION_KEY])) {
				$this->request->setIdentity(Identity::createFromUser($_SESSION[self::AUTH_SESSION_KEY]));
			}
		}

		return $this->request->getIdentity();
	}


	/**
	 * @inheritDoc
	 *
	 * @return mixed|void
	 * @throws PermissionException
	 * @throws \Environet\Sys\General\Exceptions\QueryException
	 */
	protected function authorizeRequest() {
		if (!in_array(self::HANDLER_PERMISSION, $this->getIdentity()->getPermissions())) {
			throw new PermissionException('You don\'t have the required permission for this.');
		}
	}


	/**
	 * Handle the admin request. It finds the page handler based on the admin path, and forward the request to the handler
	 * @throws RenderException
	 */
	public function handleRequest() {

		try {
			//Add base admin-template path to renderer
			Renderer::addRootPath(self::$templatePath);

			//Get admin path (full path without /admin)
			$adminPath = $this->getAdminPath();

			//Iterate over pages and test the subpath with regex. If it has match, store it.
			$foundRoute = null;
			foreach (self::$pages as $pathPattern => $route) {
				if (preg_match('/' . $pathPattern . '/', $adminPath, $match)) {
					$foundRoute = $route;
					break;
				}
			}

			if (!$foundRoute) {
				throw new HttpNotFoundException('The route \'' . $adminPath . '\' didn\'t match any of the registered patterns');
			}


			/** @var BasePage $routeHandlerClass */
			$routeHandlerClass = $foundRoute[0];
			$handlerMethodName = $foundRoute[1];


			//Allow only login page without identity
			if ($routeHandlerClass !== Login::class) {
				if (!$this->getIdentity()) {
					return httpRedirect('/admin/login');
				}
				$this->authorizeRequest();
			}

			//Page found, create the handler, and call the handle function, and return it's response
			return call_user_func([new $routeHandlerClass($this->request), $handlerMethodName]);
		} catch (PermissionException $e) {
			return (new Renderer('/error_403.phtml', ['exception' => $e]))();
		} catch (HttpNotFoundException $e) {
			return (new Renderer('/error_404.phtml', ['exception' => $e]))();
		} catch (HttpBadRequestException $e) {
			return (new Renderer('/error_400.phtml', ['exception' => $e]))();
		} catch (\Exception $e) {
			return (new Renderer('/error_500.phtml', ['exception' => $e]))();
		}
	}


	/**
	 * Return the path inside admin "namespace"
	 *
	 * @return string
	 */
	protected function getAdminPath() {
		//Get the path parts
		$parts = $this->request->getPathParts();

		//Remove "admin"
		array_shift($parts);

		//Return route after '/admin'
		return implode('/', $parts);
	}


	/**
	 * Configuration array of admin pages and the handlers
	 * @var array
	 */
	protected static $pages = [
		'^login$'  => [Login::class, 'handle'],
		'^logout$' => [Logout::class, 'handle'],
		'^$'       => [Dashboard::class, 'handle'],

		'^users$'        => [UserCrud::class, 'list'],
		'^users\/show$'  => [UserCrud::class, 'show'],
		'^users\/add$'   => [UserCrud::class, 'add'],
		'^users\/edit$'  => [UserCrud::class, 'edit'],
		'^users\/delete' => [UserCrud::class, 'delete'],

		'^groups$'        => [GroupCrud::class, 'list'],
		'^groups\/edit$'  => [GroupCrud::class, 'edit'],
		'^groups\/add'    => [GroupCrud::class, 'add'],
		'^groups\/delete' => [GroupCrud::class, 'delete'],

		'^data-providers$'       => [DataProviderCrud::class, 'list'],
		'^data-providers\/show$' => [DataProviderCrud::class, 'show'],
		'^data-providers\/add$'  => [DataProviderCrud::class, 'add'],
		'^data-providers\/edit$' => [DataProviderCrud::class, 'edit'],

		'^hydro\/observed-properties$'       => [HydroObservedPropertyCrud::class, 'list'],
		'^hydro\/observed-properties\/show$' => [HydroObservedPropertyCrud::class, 'show'],
		'^hydro\/observed-properties\/add$'  => [HydroObservedPropertyCrud::class, 'add'],
		'^hydro\/observed-properties\/edit$' => [HydroObservedPropertyCrud::class, 'edit'],

		'^hydro\/waterbodies$'       => [WaterbodyCrud::class, 'list'],
		'^hydro\/waterbodies\/show$' => [WaterbodyCrud::class, 'show'],
		'^hydro\/waterbodies\/add$'  => [WaterbodyCrud::class, 'add'],
		'^hydro\/waterbodies\/edit$' => [WaterbodyCrud::class, 'edit'],

		'^hydro\/station-classifications$'       => [HydroStationClassificationCrud::class, 'list'],
		'^hydro\/station-classifications\/show$' => [HydroStationClassificationCrud::class, 'show'],
		'^hydro\/station-classifications\/add$'  => [HydroStationClassificationCrud::class, 'add'],
		'^hydro\/station-classifications\/edit$' => [HydroStationClassificationCrud::class, 'edit'],

		'^hydro\/monitoring-points$'       => [HydroMonitoringPointCrud::class, 'list'],
		'^hydro\/monitoring-points\/show$' => [HydroMonitoringPointCrud::class, 'show'],
		'^hydro\/monitoring-points\/add$'  => [HydroMonitoringPointCrud::class, 'add'],
		'^hydro\/monitoring-points\/edit$' => [HydroMonitoringPointCrud::class, 'edit'],
		'^hydro\/monitoring-points\/csv-upload'  => [HydroMonitoringPointCrud::class, 'csvUpload'],


		'^meteo\/station-classifications$'       => [MeteoStationClassificationCrud::class, 'list'],
		'^meteo\/station-classifications\/show$' => [MeteoStationClassificationCrud::class, 'show'],
		'^meteo\/station-classifications\/add$'  => [MeteoStationClassificationCrud::class, 'add'],
		'^meteo\/station-classifications\/edit$' => [MeteoStationClassificationCrud::class, 'edit'],

		'^meteo\/observed-properties$'       => [MeteoObservedPropertyCrud::class, 'list'],
		'^meteo\/observed-properties\/show$' => [MeteoObservedPropertyCrud::class, 'show'],
		'^meteo\/observed-properties\/add$'  => [MeteoObservedPropertyCrud::class, 'add'],
		'^meteo\/observed-properties\/edit$' => [MeteoObservedPropertyCrud::class, 'edit'],

		'^meteo\/monitoring-points$'       => [MeteoMonitoringPointCrud::class, 'list'],
		'^meteo\/monitoring-points\/show$' => [MeteoMonitoringPointCrud::class, 'show'],
		'^meteo\/monitoring-points\/add$'  => [MeteoMonitoringPointCrud::class, 'add'],
		'^meteo\/monitoring-points\/edit$' => [MeteoMonitoringPointCrud::class, 'edit'],
		'^meteo\/monitoring-points\/csv-upload'  => [MeteoMonitoringPointCrud::class, 'csvUpload'],

		'^hydro\/results$' => [HydroResultsCrud::class, 'list'],
		'^meteo\/results$' => [MeteoResultsCrud::class, 'list'],

		'^upload-test$' => [UploadTest::class, 'handle'],
		'^download-test$' => [DownloadTest::class, 'handle'],
	];
}
