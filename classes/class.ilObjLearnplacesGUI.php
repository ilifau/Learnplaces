<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use SRAG\Learnplaces\container\PluginContainer;
use SRAG\Learnplaces\gui\helper\CommonControllerAction;
use SRAG\Learnplaces\service\publicapi\block\MapBlockService;
use SRAG\Learnplaces\service\publicapi\model\ILIASLinkBlockModel;

/**
 * Class ilObjLearnplacesGUI
 *
 * @author            Nicolas Schäfli <ns@studer-raimann.ch>
 *
 * @ilCtrl_isCalledBy ilObjLearnplacesGUI: ilRepositoryGUI, ilObjPluginDispatchGUI
 * @ilCtrl_isCalledBy ilObjLearnplacesGUI: ilAdministrationGUI
 * @ilCtrl_Calls      ilObjLearnplacesGUI: ilPermissionGUI, ilInfoScreenGUI, ilObjectCopyGUI
 * @ilCtrl_Calls      ilObjLearnplacesGUI: ilCommonActionDispatcherGUI
 * @ilCtrl_Calls      ilObjLearnplacesGUI: xsrlPictureUploadBlockGUI
 * @ilCtrl_Calls      ilObjLearnplacesGUI: xsrlPictureBlockGUI
 * @ilCtrl_Calls      ilObjLearnplacesGUI: xsrlContentGUI
 * @ilCtrl_Calls      ilObjLearnplacesGUI: xsrlRichTextBlockGUI
 * @ilCtrl_Calls      ilObjLearnplacesGUI: xsrlIliasLinkBlockGUI
 * @ilCtrl_Calls      ilObjLearnplacesGUI: xsrlMapBlockGUI
 * @ilCtrl_Calls      ilObjLearnplacesGUI: xsrlVideoBlockGUI
 * @ilCtrl_Calls      ilObjLearnplacesGUI: xsrlAccordionBlockGUI
 */
class ilObjLearnplacesGUI extends ilObjectPluginGUI {

	const DEFAULT_CMD = CommonControllerAction::CMD_INDEX;
	const TAB_ID_PERMISSION = 'id_permissions';
	/**
	 * @var MapBlockService $mapBlockService
	 */
	private $mapBlockService;
	/**
	 * @var int $objectId
	 */
	private $objectId;


	/**
	 * ilObjLearnplacesGUI constructor.
	 *
	 * @param int $a_ref_id
	 * @param int $a_id_type
	 * @param int $a_parent_node_id
	 *
	 * @see ilObjectPluginGUI for possible id types.
	 */
	public function __construct(int $a_ref_id = 0, int $a_id_type = self::REPOSITORY_NODE_ID, int $a_parent_node_id = 0) {
		parent::__construct($a_ref_id, $a_id_type, $a_parent_node_id);
		$this->mapBlockService = PluginContainer::resolve(MapBlockService::class);
		$this->objectId = intval(ilObject::_lookupObjectId($this->ref_id));
	}


	/**
	 * @inheritDoc
	 */
	public function getType() {
		return ilLearnplacesPlugin::PLUGIN_ID;
	}


	/**
	 * Main Triage to following GUI-Classes
	 */
	public function executeCommand() {
		$nextClass = $this->ctrl->getNextClass();
		$this->renderTabs();
		switch ($nextClass) {
			case "":
			case strtolower(static::class):
				parent::executeCommand();
				break;
			case strtolower(xsrlContentGUI::class):
				$this->ctrl->forwardCommand(PluginContainer::resolve(xsrlContentGUI::class));
				break;
			case strtolower(xsrlPictureUploadBlockGUI::class):
				$this->ctrl->forwardCommand(PluginContainer::resolve(xsrlPictureUploadBlockGUI::class));
				break;
			case strtolower(xsrlPictureBlockGUI::class):
				$this->ctrl->forwardCommand(PluginContainer::resolve(xsrlPictureBlockGUI::class));
				break;
			case strtolower(xsrlRichTextBlockGUI::class):
				$this->ctrl->forwardCommand(PluginContainer::resolve(xsrlRichTextBlockGUI::class));
				break;
			case strtolower(xsrlIliasLinkBlockGUI::class):
				$this->ctrl->forwardCommand(PluginContainer::resolve(xsrlIliasLinkBlockGUI::class));
				break;
			case strtolower(xsrlIliasLinkBlockEditFormViewGUI::class):
				//required for the ilLinkInputGUI ...
				$this->ctrl->forwardCommand(new xsrlIliasLinkBlockEditFormViewGUI(new ILIASLinkBlockModel()));
				break;
			case strtolower(xsrlMapBlockGUI::class):
				$this->ctrl->forwardCommand(PluginContainer::resolve(xsrlMapBlockGUI::class));
				break;
			case strtolower(xsrlVideoBlockGUI::class):
				$this->ctrl->forwardCommand(PluginContainer::resolve(xsrlVideoBlockGUI::class));
				break;
			case strtolower(xsrlAccordionBlockGUI::class):
				$this->ctrl->forwardCommand(PluginContainer::resolve(xsrlAccordionBlockGUI::class));
				break;
			case strtolower(ilPermissionGUI::class):
				$this->tabs->activateTab(self::TAB_ID_PERMISSION);
				$this->ctrl->forwardCommand(new ilPermissionGUI($this));
				break;
			default:
				$this->ctrl->redirectByClass(static::class);
				break;
		}
	}


	/**
	 * @param $cmd string of command which should be
	 */
	public function performCommand($cmd) {
		if (!$this->access->checkAccess('read', $cmd, $this->user->getId())) {
			$this->{$cmd}();
		}
	}


	/**
	 * This command will be executed after a new repository object was created.
	 *
	 * @return string
	 */
	public function getAfterCreationCmd() {
		return self::DEFAULT_CMD;
	}


	/**
	 * This command will be executed if no command was supplied.
	 *
	 * @return string
	 */
	public function getStandardCmd() {
		return self::DEFAULT_CMD;
	}


	public function index() {
		$this->ctrl->redirectByClass(xsrlContentGUI::class, self::DEFAULT_CMD);
	}

	private function renderTabs() {
		$this->tabs->addTab(xsrlContentGUI::TAB_ID, $this->plugin->txt('tabs_content'), $this->ctrl->getLinkTargetByClass(xsrlContentGUI::class, self::DEFAULT_CMD));
		if($this->hasMap())
			$this->tabs->addTab(xsrlMapBlockGUI::TAB_ID, $this->plugin->txt('tabs_map'), $this->ctrl->getLinkTargetByClass(xsrlMapBlockGUI::class, self::DEFAULT_CMD));
		if($this->access->checkAccess('write', '', $this->ref_id) === true) {
			$this->tabs->addTab(xsrlContentGUI::class, $this->plugin->txt('tabs_settings'), $this->ctrl->getLinkTargetByClass(xsrlContentGUI::class, self::DEFAULT_CMD));
		}
		parent::setTabs();
	}

	private function hasMap(): bool {
		try {
			$this->mapBlockService->findByObjectId($this->objectId);
			return true;
		}
		catch (InvalidArgumentException $ex) {
			return false;
		}
	}
}