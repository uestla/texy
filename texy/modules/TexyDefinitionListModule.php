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
 * Definition list module
 */
class TexyDefinitionListModule extends TexyListModule
{
    protected $allow = array('List.definition');

    public $bullets = array(
        '*' => TRUE,
        '-' => TRUE,
        '+' => TRUE,
    );

    private $translate = array(    //  rexexp  class
        '*' => array('\*'),
        '-' => array('[\x{2013}-]'),
        '+' => array('\+'),
    );



    public function init()
    {
        $bullets = array();
        foreach ($this->bullets as $bullet => $allowed)
            if ($allowed) $bullets[] = $this->translate[$bullet][0];

        $this->texy->registerBlockPattern(
            $this,
            'processBlock',
            '#^(?:'.TEXY_MODIFIER_H.'\n)?'                    // .{color:red}
          . '(\S.*)\:\ *'.TEXY_MODIFIER_H.'?\n'               // Term:
          . '(\ +)('.implode('|', $bullets).')\ +\S.*$#mUu',  //    - description
            'List.definition'
        );
    }



    /**
     * Callback function (for blocks)
     *
     *            Term: .(title)[class]{style}>
     *              - description 1
     *              - description 2
     *              - description 3
     *
     */
    public function processBlock($parser, $matches)
    {
        list(, $mMod1, $mMod2, $mMod3, $mMod4,
                                 $mContentTerm, $mModTerm1, $mModTerm2, $mModTerm3, $mModTerm4,
                                 $mSpaces, $mBullet) = $matches;
        //    [1] => (title)
        //    [2] => [class]
        //    [3] => {style}
        //    [4] => >

        //    [5] => ...
        //    [6] => (title)
        //    [7] => [class]
        //    [8] => {style}
        //    [9] => >

        //   [10] => space
        //   [11] => - * +

        $texy =  $this->texy;
        $el = new TexyBlockElement($texy);

        $bullet = '';
        foreach ($this->translate as $type)
            if (preg_match('#'.$type[0].'#Au', $mBullet)) {
                $bullet = $type[0];
                break;
            }

        if ($mMod1 || $mMod2 || $mMod3 || $mMod4) {
            $mod = new TexyModifier($this->texy);
            $mod->setProperties($mMod1, $mMod2, $mMod3, $mMod4);
            $el->tags[0] = $mod->generate('dl');
        } else {
            $el->tags[0] = TexyHtml::el('dl');
        }

        $parser->moveBackward(2);

        $patternTerm = '#^\n?(\S.*)\:\ *'.TEXY_MODIFIER_H.'?()$#mUA';
        $bullet = preg_quote($mBullet);

        while (TRUE) {
            if ($elItem = $this->processItem($parser, preg_quote($mBullet), TRUE, 'dd')) {
                $el->children[] = $elItem;
                continue;
            }

            if ($parser->receiveNext($patternTerm, $matches)) {
                list(, $mContent, $mMod1, $mMod2, $mMod3, $mMod4) = $matches;
                //    [1] => ...
                //    [2] => (title)
                //    [3] => [class]
                //    [4] => {style}
                //    [5] => >
                $elItem = new TexyTextualElement($texy);

                if ($mMod1 || $mMod2 || $mMod3 || $mMod4) {
                    $mod = new TexyModifier($this->texy);
                    $mod->setProperties($mMod1, $mMod2, $mMod3, $mMod4);
                    $elItem->tags[0] = $mod->generate('dt');
                } else {
                    $elItem->tags[0] = TexyHtml::el('dt');
                }

                $elItem->parse($mContent);
                $el->children[] = $elItem;
                continue;
            }

            break;
        }

        $parser->element->children[] = $el;
    }

} // TexyDefinitionListModule
