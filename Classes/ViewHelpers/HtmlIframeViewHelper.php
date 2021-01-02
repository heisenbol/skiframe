<?php
namespace Skar\Skiframe\ViewHelpers;

use TYPO3\CMS\Core\Page\PageRenderer;
use Skar\Skiframe\Helper;
use \TYPO3\CMS\Core\Utility\GeneralUtility;
use \TYPO3\CMS\Extbase\Object\ObjectManager;
use \TYPO3\CMS\Extbase\Configuration\ConfigurationManager;
use \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
//use \TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class HtmlIframeViewHelper extends \TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper {

    /**
     * Initialize additional argument
     */
    public function initializeArguments()
    {
        parent::initializeArguments();
        $this->registerArgument('bodytext', 'string', 'The HTML Markup', TRUE);
    }

    /**
     * @return string
     */
    public function render() {
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        $configurationManager = $objectManager->get(ConfigurationManager::class);
        $extensionSettings = $configurationManager->getConfiguration(ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS,'skiframe','tx_skiframe');
        $replacement = Helper::replaceIframes($this->arguments['bodytext'], $extensionSettings);
        if ($replacement !== $this->arguments['bodytext']) {
            $jsPath = 'EXT:skiframe/Resources/Public/Js/scripts.js';
            $cssPath = 'EXT:skiframe/Resources/Public/Css/styles.css';
            // if content was replaced, add css and js file if not already added
            $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
            if (empty($GLOBALS['EXT']['skiframe']['scriptsAdded'])) {
                $GLOBALS['EXT']['skiframe']['scriptsAdded'] = false;
            }
            if (!$GLOBALS['EXT']['skiframe']['scriptsAdded']) {
                $pageRenderer->addJsFooterFile($jsPath, null, true, false, '', true);
                if (!$extensionSettings['nocss']) {
                    $pageRenderer->addCssFile($cssPath, 'stylesheet', 'all', '', true, false, '', true);
                }
                $GLOBALS['EXT']['skiframe']['scriptsAdded'] = true;
            }
        }
        return $replacement;
    }

}
