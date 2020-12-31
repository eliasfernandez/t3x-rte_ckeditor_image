<?php

namespace Netresearch\RteCKEditorImage\Controller;

use \TYPO3\CMS\Core\Resource;
use \TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Controller to render the linked images in frontend
 *
 * PHP version 7
 *
 * @category   Netresearch
 * @package    RteCKEditor
 * @subpackage Controller
 * @author     Mathias Uhlmann <mathias.uhlmann@netresearch.de>
 * @license    http://www.gnu.de/documents/gpl-2.0.de.html GPL 2.0+
 * @link       http://www.netresearch.de
 */
class ImageLinkRenderingController extends \TYPO3\CMS\Frontend\Plugin\AbstractPlugin
{
    /**
     * Same as class name
     *
     * @var string
     */
    public $prefixId = 'ImageLinkRenderingController';

    /**
     * Path to this script relative to the extension dir
     *
     * @var string
     */
    public $scriptRelPath = 'Classes/Controller/ImageLinkRenderingController.php';

    /**
     * The extension key
     *
     * @var string
     */
    public $extKey = 'rte_ckeditor_image';

    /**
     * Configuration
     *
     * @var array
     */
    public $conf = [];

    /**
     * cObj object
     *
     * @var \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer
     */
    public $cObj;

    /**
     * Returns a processed image to be displayed on the Frontend.
     *
     * @param array $conf TypoScript configuration
     * @return string HTML output
     */
    public function renderImages($conf)
    {
        // Get link inner HTML
        $linkContent = $this->cObj->getCurrentVal();
        // Find all images with file-uid attribute
        $imgSearchPattern = '/<img(?=.*data-htmlarea-file-uid).*?\/>/';
        $attrSearchPattern = '/([a-zA-Z0-9-]+)=["]([^"]*)"|([a-zA-Z0-9-]+)=[\']([^\']*)\'/';
        $passedImages = [];
        $parsedImages = [];

        // Extract all TYPO3 images from link content
        preg_match_all($imgSearchPattern, $linkContent, $passedImages);
        $passedImages = $passedImages[0];

        if (count($passedImages) === 0) {
            return $linkContent;
        }

        foreach ($passedImages as $passedImage) {
            // Get image attributes
            preg_match_all($attrSearchPattern, $passedImage, $passedAttributes);
            $passedAttributes = array_combine($passedAttributes[1], $passedAttributes[2]);

            if (!empty($passedAttributes['data-htmlarea-file-uid'])) {
                try {
                    $systemImage = Resource\ResourceFactory::getInstance()->getFileObject($passedAttributes['data-htmlarea-file-uid']);

                    $imageConfiguration = [
                        'width' => ($passedAttributes['width']) ? $passedAttributes['width'] : $systemImage->getProperty('width'),
                        'height' => ($passedAttributes['height']) ? $passedAttributes['height'] : $systemImage->getProperty('height')
                    ];

                    // Title/Alttext: when override mode is enabled use the fields value even if it's empty
                    // When value is empty, attribute will be removed
                    $title = ($passedAttributes['title'] || (array_key_exists('title', $passedAttributes) && $passedAttributes['data-title-override'] === 'true')) ? $passedAttributes['title'] : $systemImage->getProperty('title');
                    $alt = ($passedAttributes['alt'] || (array_key_exists('alt', $passedAttributes) && $passedAttributes['data-alt-override'] === 'true')) ? $passedAttributes['alt'] : $systemImage->getProperty('alternative');

                    // Remove internal attributes
                    unset($passedAttributes['data-title-override']);
                    unset($passedAttributes['data-alt-override']);

                    $processedFile = $this->getMagicImageService()->createMagicImage($systemImage, $imageConfiguration);
                    $imageAttributes = [
                        'src' => $processedFile->getPublicUrl(),
                        'title' => $title,
                        'alt' => $alt,
                        'width' => ($passedAttributes['width']) ? $passedAttributes['width'] : $systemImage->getProperty('width'),
                        'height' => ($passedAttributes['height']) ? $passedAttributes['height'] : $systemImage->getProperty('height')
                    ];
                    // Add original attributes, if not already parsed
                    $imageAttributes = array_merge($imageAttributes, $passedAttributes);

                    // Cleanup attributes; disable zoom images within links
                    $unsetParams = [
                        'data-htmlarea-file-uid',
                        'data-htmlarea-file-table',
                        'data-htmlarea-zoom',
                        'data-htmlarea-clickenlarge' // Legacy zoom property
                    ];
                    $imageAttributes = array_diff_key($imageAttributes, array_flip($unsetParams));
                    // Image template; empty attributes are removed by 3rd param 'false'
                    $parsedImages[] = '<img ' . GeneralUtility::implodeAttributes($imageAttributes, true, false) . ' />';
                } catch (Resource\Exception\FileDoesNotExistException $fileDoesNotExistException) {
                    $parsedImages[] = $passedImage;
                    // Log in fact the file could not be retrieved.
                    $message = sprintf('I could not find file with uid "%s"', $passedAttributes['data-htmlarea-file-uid']);
                    $this->getLogger()->error($message);
                }
            } else {
                $parsedImages[] = $passedImage;
            }
        }
        // Replace original images with parsed
        $linkContent = str_replace($passedImages, $parsedImages, $linkContent);

        return $linkContent;
    }

    /**
     * Instantiates and prepares the Magic Image service.
     *
     * @return \TYPO3\CMS\Core\Resource\Service\MagicImageService
     */
    protected function getMagicImageService()
    {
        /** @var $magicImageService Resource\Service\MagicImageService */
        static $magicImageService;
        if (!$magicImageService) {
            $magicImageService = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Resource\Service\MagicImageService::class);
            // Get RTE configuration
            $pageTSConfig = $this->frontendController->getPagesTSconfig();
            if (is_array($pageTSConfig) && is_array($pageTSConfig['RTE.']['default.'])) {
                $magicImageService->setMagicImageMaximumDimensions($pageTSConfig['RTE.']['default.']);
            }
        }
        return $magicImageService;
    }

    /**
     * @return \TYPO3\CMS\Core\Log\Logger
     */
    protected function getLogger()
    {
        /** @var $logManager \TYPO3\CMS\Core\Log\LogManager */
        $logManager = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Log\LogManager::class);
        return $logManager->getLogger(get_class($this));
    }
}
