<?php
namespace Skar\Skiframe;

class Helper {
    const COMMENT_NO_PARSING = 'NO_SKIFRAME';
    const TYPE_GMAP = 10;
    const TYPE_YT = 20;
    const TYPE_VIMEO = 30;
    const TYPE_TWITTER = 40;
    const TYPE_OTHERIFRAME = 100;
    const TYPE_OTHERSCRIPT = 200;

    const DEFAULT_WIDTH = 600;
    const DEFAULT_HEIGHT = 400;
    const MATCH_ERROR = 1000;

    public static function replaceIframes(?string $html, ?array $extensionSettings) : ?string {

        if (strpos($html, self::COMMENT_NO_PARSING) !== FALSE) {
            return $html;
        }

        if (!$extensionSettings) {
            return 'Skiframe extension got an empty configuration array';
        }

        // check if the markup contains an iframe tag.
        // in this case, replace it

        $replacementCount = 0;
        $iframeCount = 0;
        $scriptCount = 0;

        // check for empty field
        if (!trim($html)) {
            return $html;
        }
        $dom = new \DOMDocument();
        $dom->strictErrorChecking = TRUE;

        // disable php handling for warnings and errors from libxml. These will be handled internally by libxml
        $previousValue = libxml_use_internal_errors(true);

        // loadHTML converts all tags to lowercase. So I do not care about case
        libxml_clear_errors();
        // need to add html opening and closing tags as otherwise consecutive iframes are output nested
        $parseResult = $dom->loadHTML('<html><head><meta content="text/html; charset=utf-8" http-equiv="Content-Type"></head><body>'.$html.'</body></html>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $lastError = libxml_get_last_error();
        //debug('WHJOLE DOC 1 '.$dom->saveHTML());

        if (!$parseResult || $lastError) {
            return static::getErrorMarkup('Unable to parse HTML. '.($lastError?' Error: '.$lastError->message:''),$extensionSettings );
        }
        libxml_use_internal_errors($previousValue);

        $scriptTagDetectedAndShouldReplace = false;
        if (count($dom->getElementsByTagName('script')) ) {
            // if disallowscripttag constant is true, and there is a script tag somewhere in the markup, then replace the whole content
            if (($extensionSettings['disallowscripttag'] ?? false)) {
                // script tag not allowed
                return static::getErrorMarkup(
                    'Script tags are not allowed in HTML content elements. ',
                    $extensionSettings
                );
            }

            if (($extensionSettings['replacescripttag'] ?? false)) {
                $scriptTagDetectedAndShouldReplace = true;
            }
        }

        if (!$scriptTagDetectedAndShouldReplace) {
            $iframeList = [];
            // get list of iframes separately, as replacing them on the fly messes up the dom and iframes are lost
            foreach ($dom->getElementsByTagName('iframe') as $iframe) {
                $iframeList[] = $iframe;
            }
            foreach ($iframeList as $iframe) {
                $iframeCount++;
                $iframeSrc = $iframe->getAttribute('src');

                $sourceType = static::getIframeSourceType($iframeSrc, $extensionSettings);
                //debug("count $iframeCount / ".count($iframeList).", type:".$sourceType." for $iframeSrc");
                if ($sourceType == self::MATCH_ERROR) { // apparently php constants have no type, and thus === does not work
                    return static::getErrorMarkup(
                        'Unable to determine iframe src for '.$iframeSrc,
                        $extensionSettings
                    );
                }


                $doReplace = false;
                if ($sourceType == self::TYPE_YT && ($extensionSettings['processyoutube'] ?? false)) {
                    $replacementCount++;
                    $doReplace = true;
                }
                else if ($sourceType == self::TYPE_GMAP && ($extensionSettings['processgmap'] ?? false)) {
                    $replacementCount++;
                    $doReplace = true;
                } else if ($sourceType == self::TYPE_VIMEO && ($extensionSettings['processvimeo'] ?? false)) {
                    $replacementCount++;
                    $doReplace = true;
                } else if (($extensionSettings['processotheriframe'] ?? false)) {
                    $replacementCount++;
                    $doReplace = true;
                }

                if ($doReplace) {
                    $width = intval($iframe->getAttribute('width'));
                    $height = intval($iframe->getAttribute('height'));
                    if (!$width) {
                        $width = self::DEFAULT_WIDTH;
                    }
                    if (!$height) {
                        $height = self::DEFAULT_HEIGHT;
                    }

                    $replacementNode = static::getReplacement(
                        $dom,
                        $iframe,
                        $sourceType,
                        $width,
                        $height,
                        $extensionSettings
                    );
                    if (!$replacementNode) {
                        return static::getErrorMarkup(
                            'Unable to generate replacement content for HTML with iframe tag' . (htmlspecialchars(
                                $iframeSrc
                            )) . '.',
                            $extensionSettings
                        );
                    }
                    $iframe->parentNode->replaceChild($replacementNode, $iframe);
                }
            }
            if ($replacementCount) {
                // find the body tag that was inserted at the beginning and return only it's content
                return static::innerHTML($dom->getElementsByTagName('body')[0] ?? null);
            }
        }

        if ($scriptTagDetectedAndShouldReplace) {
            // We have to replace the whole content as there is a script tag present and replaceifscripttag constant is set to true
            // We do not have specific width and height in this case, so use some predefined values
            // We can't use innerHTML upon activation because scripts are not executed. So in the replacement, we add a placeholder and add the script tags as dom nodes in the placeholders.
            // We do not need to duplicate the whole error handling, as it was already done at the beginning (I hope!)

            $scriptList = [];
            foreach ($dom->getElementsByTagName('script') as $script) {
                $scriptList[] = $script;
            }
            $overallSourceType = null;
            foreach ($scriptList as $script) {
                $scriptCount++;
                $scriptSrc = $script->getAttribute('src');

                $sourceType = static::getScriptSourceType($scriptSrc, $extensionSettings);
                if ($sourceType == self::MATCH_ERROR) { // apparently php constants have no type, and thus === does not work
                    return static::getErrorMarkup(
                        'Unable to determine script src for '.$scriptSrc,
                        $extensionSettings
                    );
                }

                if (!$overallSourceType) {
                    $overallSourceType = $sourceType;
                }
                else if ($sourceType != $overallSourceType) {
                    // mixed source type. Set overall to TYPE_OTHERSCRIPT
                    $overallSourceType = self::TYPE_OTHERSCRIPT;
                }

                $replacementNode = static::getScriptReplacement(
                    $dom,
                    $script
                );

                $script->parentNode->replaceChild($replacementNode, $script);

            }
            if ($scriptCount) { // at this point, should always be > 0
                // the script tags in the dom have now all been replaced. Upon activation, these replacements can now be replaced with the script tag again.
                // As we do not have a specific node to replace, create a new DOMDocument, add a skmyspan to it, add the whole original content to the skmyspan, and replace the skmyspan
                // replace now the whole dom with the placeholder and return to the browser

                // find the body tag that was inserted at the beginning and return only it's content
                $wholeContentMarkup = static::innerHTML($dom->getElementsByTagName('body')[0]);
                $scriptDom = new \DOMDocument();
                libxml_use_internal_errors(true); // otherwise it produces an "tag invalid in Entity" warning for skmyspan tag
                $scriptDom->loadHTML('<html><head><meta content="text/html; charset=utf-8" http-equiv="Content-Type"></head><body><skmyspan>'.$wholeContentMarkup.'</skmyspan></body></html>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
                $skmyspanList = [];
                // get list of iframes separately, as replacing them on the fly messes up the dom and iframes are lost
                foreach ($scriptDom->getElementsByTagName('skmyspan') as $skmyspan) {
                    $skmyspanList[] = $skmyspan;
                }
                // we have only one skmyspan tag
                $skmyspan = $skmyspanList[0];
                $replacementNode = static::getReplacement(
                    $scriptDom,
                    $skmyspan,
                    $overallSourceType,
                    self::DEFAULT_WIDTH,
                    self::DEFAULT_HEIGHT,
                    $extensionSettings
                );
                if (!$replacementNode) {
                    return static::getErrorMarkup(
                        'Unable to generate replacement content for HTML with script tag' . (htmlspecialchars(
                            $wholeContentMarkup
                        )) . '.',
                        $extensionSettings
                    );
                }
                $skmyspan->parentNode->replaceChild($replacementNode, $skmyspan);

                return static::innerHTML($scriptDom->getElementsByTagName('body')[0] ?? null);
            }
        }

        return $html;
    }
    private static function getScriptReplacement(\DOMDocument $dom, \DOMNode $node) : ?\DOMNode {
        $originalMarkup = $node->ownerDocument->saveHTML($node);
        $scriptSrc = $node->getAttribute('src');
        $newNode = $dom->createElement('span');
        $newNode->setAttribute('class','tx_skiframe_script');

        $newNode->setAttribute('data-skiframeoriginal',$originalMarkup);
        $newNode->setAttribute('data-skiframesrc',$scriptSrc);

        return $newNode;
    }
    private static function getReplacement(\DOMDocument $dom, \DOMNode $node, $type, int $width, int $height, array $extensionSettings) : ?\DOMNode {
        $doNotHonorDimensionsWhenReplacing = false;
        if ($type == self::TYPE_OTHERSCRIPT || $type == self::TYPE_TWITTER) {
            // when the content is shown, we do not honor any width/height restrictions. So mark the container with special class, and replace the whole container with js
            $doNotHonorDimensionsWhenReplacing = true;
        }
        $originalMarkup = $node->ownerDocument->saveHTML($node);
        $newNode = $dom->createElement('div');
        $fullWidth = $extensionSettings['fullwidth']??false;
        $nodeContainingContent = null;
        if ($fullWidth) {
            $newNode->setAttribute('class','tx_skiframe_container '.$type.' fullwidth'.($doNotHonorDimensionsWhenReplacing?' replacecontainer':''));
            // https://css-tricks.com/aspect-ratio-boxes/
            $newNode->setAttribute('style','--aspect-ratio:'.$width.'/'.$height.';');

            // the content must be another node
            $nodeContainingContent = $dom->createElement('div');
            $nodeContainingContent->setAttribute('class','tx_skiframe_content');
            $newNode->appendChild($nodeContainingContent);
        }
        else {
            $newNode->setAttribute('class','tx_skiframe_container type_'.$type.' tx_skiframe_content'.($doNotHonorDimensionsWhenReplacing?' replacecontainer':''));
            $newNode->setAttribute('style','width:'.$width.'px;height:'.$height.'px;');
            $nodeContainingContent = $newNode;
        }

        $newNode->setAttribute('data-original',$originalMarkup);

        $messageMarkup = static::getMessageMarkup($type, $extensionSettings);
        if (!$messageMarkup) {
            return NULL;
        }
        static::appendHTML($nodeContainingContent, '<div class="messageContainer">'.$messageMarkup.'</div>');
        static::appendHTML($nodeContainingContent, '<div class="showButtonContainer"><span class="showButton">I understand. Show content.</span></div>');
        return $newNode;
    }
    private static function getMessageMarkup($type, $extensionSettings) {
        $markup = null;
        switch ($type) {
            case self::TYPE_YT:
                $markup = $extensionSettings['youtubemessage']??null;
                break;
            case self::TYPE_GMAP:
                $markup = $extensionSettings['gmapmessage']??null;
                break;
            case self::TYPE_VIMEO:
                $markup = $extensionSettings['vimeomessage']??null;
                break;
            case self::TYPE_TWITTER:
                $markup = $extensionSettings['twittermessage']??null;
                break;
            case self::TYPE_OTHERSCRIPT:
                $markup = $extensionSettings['otheriframemessage']??null;
                break;
        }
        if (!$markup) {
            $markup = $extensionSettings['otheriframemessage']??null;
        }
        if (!$markup) {
            return NULL;
        }

        return $markup;
    }
    private static function appendHTML(\DOMNode $parent, String $markup) {
        $tmpDoc = new \DOMDocument();
        $tmpDoc->loadHTML('<html><head><meta content="text/html; charset=utf-8" http-equiv="Content-Type"></head><body>'.$markup.'</body></html>');
        foreach ($tmpDoc->getElementsByTagName('body')->item(0)->childNodes as $node) {
            $node = $parent->ownerDocument->importNode($node, true);
            $parent->appendChild($node);
        }
    }
    private static function innerHTML(\DOMElement $element)
    {
        $doc = $element->ownerDocument;

        $html = '';

        foreach ($element->childNodes as $node) {
            $html .= $doc->saveHTML($node);
        }

        return $html;
    }
    private static function getErrorMarkup(string $msg, array $extensionSettings) : string {
        return '<div class="tx_skiframe error">'.htmlspecialchars($msg.
                                                                  (($extensionSettings['disallowskipprocessing']??false)?'':'Add a HTML comment <!-- ' . self::COMMENT_NO_PARSING . ' --> to prevent processing with skiframe extension.')
            ).'</div>';
    }

    private static function getScriptSourceType(?string $src, array $extensionSettings) : ?string
    {
        if (!$src) {
            return self::MATCH_ERROR;
        }
        try {
            $match = preg_match($extensionSettings['twitterembedregex']??'//', $src);
            if ($match === FALSE) {
                return self::MATCH_ERROR;
            }
            if ($match) {
                return self::TYPE_TWITTER;
            }


        } catch (\Exception $e) {
            //debug($e->getMessage());
            return self::MATCH_ERROR;
        }
        return self::TYPE_OTHERSCRIPT;
    }
    private static function getIframeSourceType(?string $src, array $extensionSettings) : ?string {
        if (!$src) {
            return self::MATCH_ERROR;
        }
        try {
            $match = preg_match($extensionSettings['gmapregex']??'//', $src);
            if ($match === FALSE) {
                return self::MATCH_ERROR;
            }
            if ($match) {
                return self::TYPE_GMAP;
            }

            $match = preg_match($extensionSettings['youtuberegex']??'//', $src);
            if ($match === FALSE) {
                return self::MATCH_ERROR;
            }
            if ($match) {
                return self::TYPE_YT;
            }

            $match = preg_match($extensionSettings['vimeoregex']??'//', $src);
            if ($match === FALSE) {
                return self::MATCH_ERROR;
            }
            if ($match) {
                return self::TYPE_VIMEO;
            }
        } catch (\Exception $e) {
            //debug($e->getMessage());
            return self::MATCH_ERROR;
        }
        return self::TYPE_OTHERIFRAME;
    }
}