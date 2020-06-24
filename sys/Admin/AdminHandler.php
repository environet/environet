<?php

namespace Environet\Sys\Admin;

use Environet\Sys\Admin\Pages\BasePage;
use Environet\Sys\Admin\Pages\Dashboard;
use Environet\Sys\Admin\Pages\DataProvider\DataProviderCrud;
use Environet\Sys\Admin\Pages\DownloadTest;
use Environet\Sys\Admin\Pages\Group\GroupCrud;
use Environet\Sys\Admin\Pages\Hydro\RiverbankCrud;
use Environet\Sys\Admin\Pages\MeasurementAccessRule\MeasurementAccessRuleCrud;
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
use Environet\Sys\Admin\Pages\UploadData\MissingData;
use Environet\Sys\Admin\Pages\UploadData\ProcessedData;
use Environet\Sys\Admin\Pages\UploadTest;
use Environet\Sys\Admin\Pages\User\UserCrud;

use Environet\Sys\General\Exceptions\QueryException;
use Environet\Sys\General\HttpClient\BaseHandler;
use Environet\Sys\General\Identity;
use Environet\Sys\General\View\Renderer;

use Environet\Sys\General\Exceptions\PermissionException;
use Environet\Sys\General\Exceptions\HttpNotFoundException;
use Environet\Sys\General\Exceptions\HttpBadRequestException;
use Environet\Sys\General\Exceptions\RenderException;
use Exception;

/**
 * Class AdminHandler
 *
 * The core entry-point for administration area requests.
 * It is also a router, which forward the request to a page handler based on the url path
 *
 * @package Environet\Sys\Admin
 * @author  SRG Group <dev@srg.hu>
 */
class AdminHandler extends BaseHandler {

	/** @inheritDoc */
	const HANDLER_PERMISSION = 'admin.login';

	/**
	 * @var string Base path for templates
	 */
	public static $templatePath = SRC_PATH . '/sys/Admin/templates';


	/**
	 * @inheritDoc
	 */
	protected function getIdentity(): ?Identity {
		return $this->request->getIdentity();
	}


	/**
	 * @inheritDoc
	 *
	 * @throws QueryException
	 * @throws PermissionException
	 */
	protected function authorizeRequest(array $requiredPermissions = []) {
		$requiredPermissions = array_merge($requiredPermissions, [self::HANDLER_PERMISSION]);
		if (!$this->getIdentity()->hasPermissions($requiredPermissions)) {
			throw new PermissionException('You don\'t have the required permissions for this. (' . join(', ', $requiredPermissions) . ')');
		} else {
			$this->getIdentity()->setAuthorizedPermissions($requiredPermissions);
		}
	}


	/**
	 * Handle the admin request.
	 *
	 * It finds the page handler based on the admin path, and forward the request to the handler
	 *
	 * @throws RenderException
	 * @uses \httpRedirect()
	 * @uses \Environet\Sys\Admin\AdminHandler::getIdentity()
	 * @uses \Environet\Sys\Admin\AdminHandler::authorizeRequest()
	 * @uses \Environet\Sys\Admin\AdminHandler::getAdminPath()
	 */
	public function handleRequest() {

		try {
			// Add base admin-template path to renderer
			Renderer::addRootPath(self::$templatePath);

			// Get admin path (full path without /admin)
			$adminPath = $this->getAdminPath();

			// Iterate over pages and test the subpath with regex. If it has match, store it.
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
			$requiredPermissions = isset($foundRoute[2]) ? $foundRoute[2] : [];


			// Allow only login page without identity
			if ($routeHandlerClass !== Login::class) {
				if (!$this->getIdentity()) {
					return httpRedirect('/admin/login');
				}
				try {
					$this->authorizeRequest($requiredPermissions);
				} catch (PermissionException $exception) {
					// User doesn't have the "normal" permissions, check any alternative permissions
					if (isset($foundRoute[3])) {
						$alternativePermissions = $foundRoute[3];
						$this->authorizeRequest($alternativePermissions);
					} else {
						throw $exception;
					}
				}
			}

			// Page found, create the handler, and call the handle function, and return it's response
			return call_user_func([new $routeHandlerClass($this->request), $handlerMethodName]);
		} catch (PermissionException $e) {
			return (new Renderer('/error_403.phtml', ['exception' => $e]))();
		} catch (HttpNotFoundException $e) {
			return (new Renderer('/error_404.phtml', ['exception' => $e]))();
		} catch (HttpBadRequestException $e) {
			return (new Renderer('/error_400.phtml', ['exception' => $e]))();
		} catch (Exception $e) {
			return (new Renderer('/error_500.phtml', ['exception' => $e]))();
		}
	}


	/**
	 * Return the path inside admin "namespace"
	 *
	 * @return string
	 */
	protected function getAdminPath() {
		// Get the path parts
		$parts = $this->request->getPathParts();

		// Remove "admin"
		array_shift($parts);

		// Return route after '/admin'
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

		'^measurement-access-rules$'       => [MeasurementAccessRuleCrud::class, 'list', ['admin.measurementaccessrules.read'], ['admin.measurementaccessrules.readown']],
		'^measurement-access-rules\/show$' => [MeasurementAccessRuleCrud::class, 'show', ['admin.measurementaccessrules.read'], ['admin.measurementaccessrules.readown']],
		'^measurement-access-rules\/add$'  => [MeasurementAccessRuleCrud::class, 'add', ['admin.measurementaccessrules.create'], ['admin.measurementaccessrules.createown']],
		'^measurement-access-rules\/edit$' => [MeasurementAccessRuleCrud::class, 'edit', ['admin.measurementaccessrules.update'], ['admin.measurementaccessrules.updateown']],
		'^measurement-access-rules\/delete' => [MeasurementAccessRuleCrud::class, 'delete', ['admin.measurementaccessrules.delete'], ['admin.measurementaccessrules.deleteown']],

		'^users$'        => [UserCrud::class, 'list', ['admin.users.read']],
		'^users\/show$'  => [UserCrud::class, 'show', ['admin.users.read']],
		'^users\/add$'   => [UserCrud::class, 'add', ['admin.users.create']],
		'^users\/edit$'  => [UserCrud::class, 'edit', ['admin.users.update']],
		'^users\/delete' => [UserCrud::class, 'delete', ['admin.users.delete']],

		'^groups$'        => [GroupCrud::class, 'list', ['admin.groups.read']],
		'^groups\/edit$'  => [GroupCrud::class, 'edit', ['admin.groups.update']],
		'^groups\/add'    => [GroupCrud::class, 'add', ['admin.groups.create']],
		'^groups\/delete' => [GroupCrud::class, 'delete', ['admin.groups.delete']],

		'^data-providers$'       => [DataProviderCrud::class, 'list', ['admin.providers.read'], ['admin.providers.readown']],
		'^data-providers\/show$' => [DataProviderCrud::class, 'show', ['admin.providers.read'], ['admin.providers.readown']],
		'^data-providers\/add$'  => [DataProviderCrud::class, 'add', ['admin.providers.create']],
		'^data-providers\/edit$' => [DataProviderCrud::class, 'edit', ['admin.providers.update'], ['admin.providers.updateown']],

		'^hydro\/observed-properties$'       => [HydroObservedPropertyCrud::class, 'list', ['admin.hydro.observedproperties.read']],
		'^hydro\/observed-properties\/show$' => [HydroObservedPropertyCrud::class, 'show', ['admin.hydro.observedproperties.read']],
		'^hydro\/observed-properties\/add$'  => [HydroObservedPropertyCrud::class, 'add', ['admin.hydro.observedproperties.create']],
		'^hydro\/observed-properties\/edit$' => [HydroObservedPropertyCrud::class, 'edit', ['admin.hydro.observedproperties.update']],
		'^hydro\/observed-properties\/delete' => [HydroObservedPropertyCrud::class, 'delete', ['admin.hydro.observedproperties.delete']],

		'^hydro\/waterbodies$'       => [WaterbodyCrud::class, 'list', ['admin.hydro.waterbodies.read']],
		'^hydro\/waterbodies\/show$' => [WaterbodyCrud::class, 'show', ['admin.hydro.waterbodies.read']],
		'^hydro\/waterbodies\/add$'  => [WaterbodyCrud::class, 'add', ['admin.hydro.waterbodies.create']],
		'^hydro\/waterbodies\/edit$' => [WaterbodyCrud::class, 'edit', ['admin.hydro.waterbodies.update']],
		'^hydro\/waterbodies\/delete' => [WaterbodyCrud::class, 'delete', ['admin.hydro.waterbodies.delete']],

		'^hydro\/riverbanks$'       => [RiverbankCrud::class, 'list', ['admin.hydro.riverbanks.read']],
		'^hydro\/riverbanks\/show$' => [RiverbankCrud::class, 'show', ['admin.hydro.riverbanks.read']],
		'^hydro\/riverbanks\/add$'  => [RiverbankCrud::class, 'add', ['admin.hydro.riverbanks.create']],
		'^hydro\/riverbanks\/edit$' => [RiverbankCrud::class, 'edit', ['admin.hydro.riverbanks.update']],
		'^hydro\/riverbanks\/delete' => [RiverbankCrud::class, 'delete', ['admin.hydro.riverbanks.delete']],

		'^hydro\/station-classifications$'       => [HydroStationClassificationCrud::class, 'list', ['admin.hydro.classifications.read']],
		'^hydro\/station-classifications\/show$' => [HydroStationClassificationCrud::class, 'show', ['admin.hydro.classifications.read']],
		'^hydro\/station-classifications\/add$'  => [HydroStationClassificationCrud::class, 'add', ['admin.hydro.classifications.create']],
		'^hydro\/station-classifications\/edit$' => [HydroStationClassificationCrud::class, 'edit', ['admin.hydro.classifications.update']],
		'^hydro\/station-classifications\/delete$' => [HydroStationClassificationCrud::class, 'delete', ['admin.hydro.classifications.delete']],

		'^hydro\/monitoring-points$'            => [HydroMonitoringPointCrud::class, 'list', ['admin.hydro.monitoringpoints.read'], ['admin.hydro.monitoringpoints.readown']],
		'^hydro\/monitoring-points\/show$'      => [HydroMonitoringPointCrud::class, 'show', ['admin.hydro.monitoringpoints.read'], ['admin.hydro.monitoringpoints.readown']],
		'^hydro\/monitoring-points\/add$'       => [HydroMonitoringPointCrud::class, 'add', ['admin.hydro.monitoringpoints.create'], ['admin.hydro.monitoringpoints.createown']],
		'^hydro\/monitoring-points\/edit$'      => [HydroMonitoringPointCrud::class, 'edit', ['admin.hydro.monitoringpoints.update'], ['admin.hydro.monitoringpoints.updateown']],
		'^hydro\/monitoring-points\/delete'      => [HydroMonitoringPointCrud::class, 'delete', ['admin.hydro.monitoringpoints.delete'], ['admin.hydro.monitoringpoints.deleteown']],
		'^hydro\/monitoring-points\/csv-upload' => [HydroMonitoringPointCrud::class, 'csvUpload', ['admin.hydro.monitoringpoints.create', 'admin.hydro.monitoringpoints.update'], ['admin.hydro.monitoringpoints.createown', 'admin.hydro.monitoringpoints.updateown']],

		'^meteo\/station-classifications$'       => [MeteoStationClassificationCrud::class, 'list', ['admin.meteo.classifications.read']],
		'^meteo\/station-classifications\/show$' => [MeteoStationClassificationCrud::class, 'show', ['admin.meteo.classifications.read']],
		'^meteo\/station-classifications\/add$'  => [MeteoStationClassificationCrud::class, 'add', ['admin.meteo.classifications.create']],
		'^meteo\/station-classifications\/edit$' => [MeteoStationClassificationCrud::class, 'edit', ['admin.meteo.classifications.update']],
		'^meteo\/station-classifications\/delete$' => [MeteoStationClassificationCrud::class, 'delete', ['admin.meteo.classifications.delete']],

		'^meteo\/observed-properties$'       => [MeteoObservedPropertyCrud::class, 'list', ['admin.meteo.observedproperties.read']],
		'^meteo\/observed-properties\/show$' => [MeteoObservedPropertyCrud::class, 'show', ['admin.meteo.observedproperties.read']],
		'^meteo\/observed-properties\/add$'  => [MeteoObservedPropertyCrud::class, 'add', ['admin.meteo.observedproperties.create']],
		'^meteo\/observed-properties\/edit$' => [MeteoObservedPropertyCrud::class, 'edit', ['admin.meteo.observedproperties.update']],
		'^meteo\/observed-properties\/delete' => [MeteoObservedPropertyCrud::class, 'delete', ['admin.meteo.observedproperties.delete']],

		'^meteo\/monitoring-points$'            => [MeteoMonitoringPointCrud::class, 'list', ['admin.meteo.monitoringpoints.read'], ['admin.meteo.monitoringpoints.readown']],
		'^meteo\/monitoring-points\/show$'      => [MeteoMonitoringPointCrud::class, 'show', ['admin.meteo.monitoringpoints.read'], ['admin.meteo.monitoringpoints.readown']],
		'^meteo\/monitoring-points\/add$'       => [MeteoMonitoringPointCrud::class, 'add', ['admin.meteo.monitoringpoints.create'], ['admin.meteo.monitoringpoints.createown']],
		'^meteo\/monitoring-points\/edit$'      => [MeteoMonitoringPointCrud::class, 'edit', ['admin.meteo.monitoringpoints.update'], ['admin.meteo.monitoringpoints.updateown']],
		'^meteo\/monitoring-points\/delete'      => [MeteoMonitoringPointCrud::class, 'delete', ['admin.meteo.monitoringpoints.delete'], ['admin.meteo.monitoringpoints.deleteown']],
		'^meteo\/monitoring-points\/csv-upload' => [MeteoMonitoringPointCrud::class, 'csvUpload', ['admin.meteo.monitoringpoints.create', 'admin.meteo.monitoringpoints.update'], ['admin.meteo.monitoringpoints.createown', 'admin.meteo.monitoringpoints.updateown']],

		'^hydro\/results$' => [HydroResultsCrud::class, 'list', ['admin.hydro.results.read']],
		'^meteo\/results$' => [MeteoResultsCrud::class, 'list', ['admin.meteo.results.read']],

		'^missing-data$'   => [MissingData::class, 'handle', ['admin.missingData.upload'], ['admin.missingData.uploadown']],
		'^processed-data$'   => [ProcessedData::class, 'handle', ['admin.processedData.upload'], ['admin.processedData.uploadown']],

		'^upload-test$'   => [UploadTest::class, 'handle', ['api.upload']],
		'^download-test$' => [DownloadTest::class, 'handle', ['api.download']],
	];
}
