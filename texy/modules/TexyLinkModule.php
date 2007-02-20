<?php

/**
 * Texy! universal text -> html converter
 * --------------------------------------
 *
 * This source file is subject to the GNU GPL license.
 *
 * @author     David Grudl aka -dgx- <dave@dgx.cz>
 * @link       http://texy.info/
 * @copyright  Copyright (c) 2004-2007 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE v2
 * @package    Texy
 * @category   Text
 * @version    $Revision$ $Date$
 */

// security - include texy.php, not this file
if (!defined('TEXY')) die();



/**
 * Links module
 */
class TexyLinkModule extends TexyModule
{
    protected $allow = array('Link.reference', 'Link.email', 'Link.URL', 'Link.quickLink', 'Link.definition');

    public $root            = '';                          // root of relative links
    public $emailOnClick    = NULL;                        // 'this.href="mailto:"+this.href.match(/./g).reverse().slice(0,-7).join("")';
    public $imageOnClick    = 'return !popupImage(this.href)';  // image popup event
    public $popupOnClick    = 'return !popup(this.href)';  // popup popup event
    public $forceNoFollow   = FALSE;                       // always use rel="nofollow" for absolute links




    public function init()
    {
        $this->texy->registerLinePattern(
            $this,
            'processLineQuick',
            '#(['.TEXY_CHAR.'0-9@\#$%&.,_-]+)(?=:\[)'.TEXY_LINK.'()#Uu',
            'Link.quickLink'
        );

        // [reference]
        $this->texy->registerLinePattern(
            $this,
            'processLineReference',
            '#('.TEXY_LINK_REF.')#U',
            'Link.reference'
        );

        // direct url and email
        $this->texy->registerLinePattern(
            $this,
            'processLineURL',
            '#(?<=\s|^|\(|\[|\<|:)(?:https?://|www\.|ftp://|ftp\.)[a-z0-9.-][/a-z\d+\.~%&?@=_:;\#,-]+[/\w\d+~%?@=_\#]#iu',
            'Link.URL'
        );

        $this->texy->registerLinePattern(
            $this,
            'processLineURL',
            '#(?<=\s|^|\(|\[|\<|:)'.TEXY_EMAIL.'#i',
            'Link.email'
        );
    }





    /**
     * Add new named image
     */
    public function addReference($name, $obj)
    {
        $this->texy->addReference($name, $obj);
    }




    /**
     * Receive new named link. If not exists, try
     * call user function to create one.
     */
    public function getReference($refName) {
        $el = $this->texy->getReference($refName);
        $query = '';

        if (!$el) {
            $queryPos = strpos($refName, '?');
            if ($queryPos === FALSE) $queryPos = strpos($refName, '#');
            if ($queryPos !== FALSE) { // try to extract ?... #... part
                $el = $this->texy->getReference(substr($refName, 0, $queryPos));
                $query = substr($refName, $queryPos);
            }
        }

        if (!($el instanceof TexyLinkReference)) return FALSE;

        $el->query = $query;
        return $el;
    }



    /**
     * Preprocessing
     */
    public function preProcess($text)
    {
        // [la trine]: http://www.dgx.cz/trine/ text odkazu .(title)[class]{style}
        if ($this->texy->allowed['Link.definition'])
            return preg_replace_callback(
                '#^\[([^\[\]\#\?\*\n]+)\]: +('.TEXY_LINK_IMAGE.'|(?!\[)\S+)(\ .+)?'.TEXY_MODIFIER.'?()$#mU',
                array($this, 'processReferenceDefinition'),
                $text
            );
        return $text;
    }




    /**
     * Callback function: [la trine]: http://www.dgx.cz/trine/ text odkazu .(title)[class]{style}
     * @return string
     */
    public function processReferenceDefinition($matches)
    {
        list(, $mRef, $mLink, $mLabel, $mMod1, $mMod2, $mMod3) = $matches;
        //    [1] => [ (reference) ]
        //    [2] => link
        //    [3] => ...
        //    [4] => (title)
        //    [5] => [class]
        //    [6] => {style}


        $elRef = new TexyLinkReference($this->texy, $mLink, $mLabel);
        $elRef->modifier->setProperties($mMod1, $mMod2, $mMod3);

        $this->addReference($mRef, $elRef);

        return '';
    }








    /**
     * Callback function: ....:LINK
     * @return string
     */
    public function processLineQuick($parser, $matches)
    {
        list(, $mContent, $mLink) = $matches;
        //    [1] => ...
        //    [2] => [ref]

        $mLink = substr($mLink, 1, -1);
        $elRef = $this->getReference($mLink);
        if ($elRef) {
            $loc = $elRef->URL . $elRef->query;
            $loc = str_replace('%s', urlencode(Texy::wash($mContent)), $loc);
            $link = new TexyLink($this->texy, $loc, TexyLink::REFERENCE);
            $el = $elRef->modifier->generate('a');
        } else {
            $el = TexyHtml::el('a');
            $link = new TexyLink($this->texy, $mLink, TexyLink::REFERENCE);
        }

        $this->texy->summary['links'][] = $el->href = $link->asURL();

        // rel="nofollow"
        if ($link->isAbsolute() && $this->forceNoFollow) $el->rel = 'nofollow'; // TODO: append, not replace

        // email on click
        if ($link->isEmail()) $el->onclick = $this->emailOnClick;

        $keyOpen  = $el->startMark($this->texy);
        $keyClose = $el->endMark($this->texy);
        return $keyOpen . $mContent . $keyClose;
    }




    static private $callstack;

    /**
     * Callback function: [ref]
     * @return string
     */
    public function processLineReference($parser, $matches)
    {
        list($match, $mRef) = $matches;
        //    [1] => [ref]

        $mRef = substr($mRef, 1, -1);

        // prevent cycling
        $lowName = strtolower($mRef); // pozor na UTF8 !
        if (isset(self::$callstack[$lowName])) return $match;

        $elRef = $this->getReference($mRef);
        if (!$elRef) return $match;

        $link = new TexyLink($this->texy, $elRef->URL . $elRef->query, TexyLink::REFERENCE);

        $modifier = $elRef->modifier;
        $el = $modifier->generate('a');
        $this->texy->summary['links'][] = $el->href = $link->asURL();

        // rel="nofollow"
        if ($link->isAbsolute() && $this->forceNoFollow) $el->rel = 'nofollow'; // TODO: append, not replace

        // email on click
        if ($link->isEmail()) $el->onclick = $this->emailOnClick;


        if ($elRef->label) {
            self::$callstack[$lowName] = TRUE;
            $label = new TexyTextualElement($this->texy);
            $label->parse($elRef->label);
            $content = $label->content;
            unset(self::$callstack[$lowName]);

        } else {
            $content = $link->asTextual();
        }

        $keyOpen  = $el->startMark($this->texy);
        $keyClose = $el->endMark($this->texy);
        return $keyOpen . $content . $keyClose;
    }




    /**
     * Callback function: http://www.dgx.cz
     * @return string
     */
    public function processLineURL($parser, $matches)
    {
        list($mURL) = $matches;
        //    [0] => URL

        $link = new TexyLink($this->texy, $mURL, TexyLink::DIRECT);
        $el = TexyHtml::el('a');
        $this->texy->summary['links'][] = $el->href = $link->asURL();

        // rel="nofollow"
        if ($link->isAbsolute() && $this->forceNoFollow) $el->rel = 'nofollow'; // TODO: append, not replace

        // email on click
        if ($link->isEmail()) $el->onclick = $this->emailOnClick;

        $keyOpen  = $el->startMark($this->texy);
        $keyClose = $el->endMark($this->texy);
        return $keyOpen . $link->asTextual() . $keyClose;
    }


/*
    public function factory($loc, $text='', $modifier)
    {
        $link = new TexyLink($this->texy);

        do {
            if (strlen($loc)>1 && $loc{0} === '[' && $loc{1} !== '*') {
                $elRef = $this->getReference( substr($loc, 1, -1) );

                if ($elRef) {
                    $modifier = $elRef->modifier;
                    $loc = $elRef->URL . $elRef->query;
                    $loc = str_replace('%s', urlencode(Texy::wash($text)), $loc);

                } else {
                    $link->set(substr($loc, 1, -1), $this->root);
                    break;
                }
            }

            if (strlen($loc)>1 && $loc{0} === '[' && $loc{1} === '*') {
                $elImage = new TexyImageElement($this->texy);
                $elImage->setImagesRaw(substr($loc, 2, -2));
                $elImage->requireLinkImage();
                $link->copyFrom($elImage->linkImage);
                break;
            }

            $link->set($loc, $this->root);
        } while (0);

        if ($link->asURL() == '') return TexyHtml::el();  // dest URL is required

        $el = TexyHtml::el('a');
        $modifier->decorate($el);
        $this->texy->summary['links'][] = $el->href = $link->asURL();

        // rel="nofollow"
        if ($link->isAbsolute() && $this->forceNoFollow) $el->rel = 'nofollow'; // TODO: append, not replace

        // email on click
        if ($link->isEmail()) $el->onclick = $this->emailOnClick;

        // image on click
        if ($link->isImage()) $el->onclick = $this->imageOnClick;

        return $el;
    }
*/
} // TexyLinkModule






class TexyLinkReference {
    public $URL;
    public $query;
    public $label;
    public $modifier;


    // constructor
    public function __construct($texy, $URL = NULL, $label = NULL)
    {
        $this->modifier = new TexyModifier($texy);

        if (strlen($URL) > 1)  if ($URL{0} === '\'' || $URL{0} === '"') $URL = substr($URL, 1, -1);
        $this->URL = trim($URL);
        $this->label = trim($label);
    }

}






/*
 // TexyLinkElement


    public function setLinkRaw($link, $text='')
    {
        if (strlen($link)>1 && $link{0} === '[' && $link{1} !== '*') {
            $elRef = $this->texy->linkModule->getReference( substr($link, 1, -1) );

            if ($elRef) {
                $this->modifier = clone $elRef->modifier;
                $link = $elRef->URL . $elRef->query;
                $link = str_replace('%s', urlencode(Texy::wash($text)), $link);

            } else {
                $this->setLink(substr($link, 1, -1));
                return;
            }
        }

        if (strlen($link)>1 && $link{0} === '[' && $link{1} === '*') {
            $elImage = new TexyImageElement($this->texy);
            $elImage->setImagesRaw(substr($link, 2, -2));
            $elImage->requireLinkImage();
            if ($elImage->linkImage) $this->link->copyFrom($elImage->linkImage);
            return;
        }

        $this->setLink($link);
    }




    protected function generateTags(&$tags)
    {
        if ($this->link->asURL() == '') return;  // dest URL is required

        $el = TexyHtml::el('a');
        $tags['a'] = $el;

        $this->modifier->decorate($el);
        $this->texy->summary['links'][] = $el->href = $this->link->asURL();

         // rel="nofollow"
        if ($this->link->isAbsolute() && $this->texy->linkModule->forceNoFollow) $el->rel = 'nofollow'; // TODO: append, not replace

        // email on click
        if ($this->link->isEmail()) $el->onclick = $this->texy->linkModule->emailOnClick;

        // image on click
        if ($this->link->isImage()) $el->onclick = $this->texy->linkModule->imageOnClick;

    }
*/








