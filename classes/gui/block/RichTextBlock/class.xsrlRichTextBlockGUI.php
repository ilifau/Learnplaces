<?php
declare(strict_types=1);

use Psr\Http\Message\ServerRequestInterface;
use SRAG\Learnplaces\gui\block\RichTextBlock\RichTextBlockEditFormView;
use SRAG\Learnplaces\gui\block\util\AccordionAware;
use SRAG\Learnplaces\gui\block\util\BlockIdReferenceValidationAware;
use SRAG\Learnplaces\gui\block\util\InsertPositionAware;
use SRAG\Learnplaces\gui\block\util\ReferenceIdAware;
use SRAG\Learnplaces\gui\component\PlusView;
use SRAG\Learnplaces\gui\exception\ValidationException;
use SRAG\Learnplaces\gui\helper\CommonControllerAction;
use SRAG\Learnplaces\service\publicapi\block\AccordionBlockService;
use SRAG\Learnplaces\service\publicapi\block\ConfigurationService;
use SRAG\Learnplaces\service\publicapi\block\LearnplaceService;
use SRAG\Learnplaces\service\publicapi\block\RichTextBlockService;
use SRAG\Learnplaces\service\publicapi\model\RichTextBlockModel;
use SRAG\Learnplaces\service\security\AccessGuard;

/**
 * Class xsrlRichTextBlock
 *
 * @package SRAG\Learnplaces\gui\block\RichTextBlock
 *
 * @author  Nicolas Schäfli <ns@studer-raimann.ch>
 */
final class xsrlRichTextBlockGUI
{

    use InsertPositionAware;
    use AccordionAware;
    use BlockIdReferenceValidationAware;
    use ReferenceIdAware;

    const TAB_ID = 'Content';
    const BLOCK_ID_QUERY_KEY = 'block';

    /**
     * @var ilTabsGUI $tabs
     */
    private $tabs;
    /**
     * @var ilGlobalPageTemplate | ilTemplate $template
     */
    private $template;
    /**
     * @var ilCtrl $controlFlow
     */
    private $controlFlow;
    /**
     * @var ilLearnplacesPlugin $plugin
     */
    private $plugin;
    /**
     * @var RichTextBlockService $richTextBlockService
     */
    private $richTextBlockService;
    /**
     * @var LearnplaceService $learnplaceService
     */
    private $learnplaceService;
    /**
     * @var ConfigurationService $configService
     */
    private $configService;
    /**
     * @var AccordionBlockService $accordionService
     */
    private $accordionService;
    /**
     * @var ServerRequestInterface $request
     */
    private $request;
    /**
     * @var AccessGuard $blockAccessGuard
     */
    private $blockAccessGuard;


    /**
     * xsrlRichTextBlockGUI constructor.
     *
     * @param ilTabsGUI $tabs
     * @param ilGlobalPageTemplate | ilTemplate $template
     * @param ilCtrl $controlFlow
     * @param ilLearnplacesPlugin $plugin
     * @param RichTextBlockService $richTextBlockService
     * @param LearnplaceService $learnplaceService
     * @param ConfigurationService $configService
     * @param AccordionBlockService $accordionService
     * @param ServerRequestInterface $request
     * @param AccessGuard $blockAccessGuard
     */
    public function __construct(ilTabsGUI $tabs, $template, ilCtrl $controlFlow, ilLearnplacesPlugin $plugin, RichTextBlockService $richTextBlockService, LearnplaceService $learnplaceService, ConfigurationService $configService, AccordionBlockService $accordionService, ServerRequestInterface $request, AccessGuard $blockAccessGuard)
    {
        $this->tabs = $tabs;
        $this->template = $template;
        $this->controlFlow = $controlFlow;
        $this->plugin = $plugin;
        $this->richTextBlockService = $richTextBlockService;
        $this->learnplaceService = $learnplaceService;
        $this->configService = $configService;
        $this->accordionService = $accordionService;
        $this->request = $request;
        $this->blockAccessGuard = $blockAccessGuard;
    }


    public function executeCommand(): bool
    {
        $cmd = $this->controlFlow->getCmd(CommonControllerAction::CMD_INDEX);
        $this->tabs->activateTab(self::TAB_ID);

        switch ($cmd) {
            case CommonControllerAction::CMD_ADD:
            case CommonControllerAction::CMD_CANCEL:
            case CommonControllerAction::CMD_CONFIRM:
            case CommonControllerAction::CMD_CREATE:
            case CommonControllerAction::CMD_DELETE:
            case CommonControllerAction::CMD_EDIT:
            case CommonControllerAction::CMD_UPDATE:
                if ($this->blockAccessGuard->hasWritePermission()) {
                    $this->{$cmd}();
                    if ($this->template instanceof ilGlobalPageTemplate) {
                        $this->template->printToStdout();
                    } else {
                        $this->template->show();
                    }
                    return true;
                }
                break;
        }
        ilUtil::sendFailure($this->plugin->txt('common_access_denied'), true);
        $this->controlFlow->redirectByClass(ilRepositoryGUI::class);

        return false;
    }

    private function add(): void
    {
        $this->controlFlow->saveParameter($this, PlusView::POSITION_QUERY_PARAM);
        $this->controlFlow->saveParameter($this, PlusView::ACCORDION_QUERY_PARAM);

        $config = $this->configService->findByObjectId(ilObject::_lookupObjectId($this->getCurrentRefId()));
        $block = new RichTextBlockModel();

        $block->setVisibility($config->getDefaultVisibility());
        $form = new RichTextBlockEditFormView($block);
        $form->fillForm();
        $this->template->setContent($form->getHTML());
    }

    private function create(): void
    {

        $form = new RichTextBlockEditFormView(new RichTextBlockModel());
        try {
            $queries = $this->request->getQueryParams();

            //store block
            /**
             * @var RichTextBlockModel $block
             */
            $block = $form->getBlockModel();
            $block->setId(0); //mitigate block id injection
            $accordionId = $this->getCurrentAccordionId($queries);
            if ($accordionId > 0)
                $this->redirectInvalidRequests($accordionId);

            $block = $this->richTextBlockService->store($block);

            $anchor = xsrlContentGUI::ANCHOR_TEMPLATE;
            if ($accordionId > 0) {
                $accordion = $this->accordionService->find($accordionId);
                $blocks = $accordion->getBlocks();
                array_splice($blocks, $this->getInsertPosition($queries), 0, [$block]);
                $accordion->setBlocks($blocks);
                $this->accordionService->store($accordion);
                $anchor .= $accordion->getSequence();
            } else {
                //fetch learnplace
                $learnplace = $this->learnplaceService->findByObjectId(ilObject::_lookupObjectId($this->getCurrentRefId()));

                //store relation learnplace <-> block
                $blocks = $learnplace->getBlocks();
                array_splice($blocks, $this->getInsertPosition($queries), 0, [$block]);
                $learnplace->setBlocks($blocks);
                $this->learnplaceService->store($learnplace);
                $anchor .= $block->getSequence();
            }

            ilUtil::sendSuccess($this->plugin->txt('message_changes_save_success'), true);
            $this->controlFlow->redirectByClass(xsrlContentGUI::class, CommonControllerAction::CMD_INDEX, $anchor);
        } catch (ValidationException $ex) {
            $form->setValuesByPost();
            $this->template->setContent($form->getHTML());
        } catch (LogicException $ex) {
            $form->setValuesByPost();
            $this->template->setContent($form->getHTML());
        }
    }

    private function edit(): void
    {
        $block = $this->richTextBlockService->find($this->getBlockId());
        $form = new RichTextBlockEditFormView($block);
        $form->fillForm();
        $this->template->setContent($form->getHTML());
    }

    private function update(): void
    {

        $form = new RichTextBlockEditFormView(new RichTextBlockModel());
        try {
            //store block
            /**
             * @var RichTextBlockModel $block
             */
            $block = $form->getBlockModel();
            $this->redirectInvalidRequests($block->getId());
            $oldBlock = $this->richTextBlockService->find($block->getId());
            $block->setSequence($oldBlock->getSequence());
            $this->richTextBlockService->store($block);

            $anchor = xsrlContentGUI::ANCHOR_TEMPLATE . $block->getSequence();
            ilUtil::sendSuccess($this->plugin->txt('message_changes_save_success'), true);
            $this->controlFlow->redirectByClass(xsrlContentGUI::class, CommonControllerAction::CMD_INDEX, $anchor);
        } catch (ValidationException $ex) {
            $form->setValuesByPost();
            $this->template->setContent($form->getHTML());
        }
    }


    private function delete(): void
    {
        $queries = $this->request->getQueryParams();
        $blockId = intval($queries[self::BLOCK_ID_QUERY_KEY]);
        $this->redirectInvalidRequests($blockId);
        $this->richTextBlockService->delete($blockId);
        $this->regenerateSequence();
        ilUtil::sendSuccess($this->plugin->txt('message_delete_success'), true);
        $this->controlFlow->redirectByClass(xsrlContentGUI::class, CommonControllerAction::CMD_INDEX);
    }

    private function confirm(): void
    {
        $queries = $this->request->getQueryParams();

        $confirm = new ilConfirmationGUI();
        $confirm->setHeaderText($this->plugin->txt('confirm_delete_header'));
        $confirm->setFormAction(
            $this->controlFlow->getFormAction($this) .
            '&' .
            self::BLOCK_ID_QUERY_KEY .
            '=' .
            $queries[self::BLOCK_ID_QUERY_KEY]
        );
        $confirm->setConfirm($this->plugin->txt('common_delete'), CommonControllerAction::CMD_DELETE);
        $confirm->setCancel($this->plugin->txt('common_cancel'), CommonControllerAction::CMD_CANCEL);
        $this->template->setContent($confirm->getHTML());
    }

    private function cancel(): void
    {
        $this->controlFlow->redirectByClass(xsrlContentGUI::class, CommonControllerAction::CMD_INDEX);
    }

    private function getBlockId(): int
    {
        $queries = $this->request->getQueryParams();
        return intval($queries[self::BLOCK_ID_QUERY_KEY]);
    }

    private function regenerateSequence(): void
    {
        $learnplace = $this->learnplaceService->findByObjectId(ilObject::_lookupObjectId($this->getCurrentRefId()));
        $this->learnplaceService->store($learnplace);
    }

}