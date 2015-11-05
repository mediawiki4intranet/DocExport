<?php

/**
 * MediaWiki DocExport extension
 * Version 1.5 compatible with MediaWiki 1.19-1.26
 *
 * Copyright © 2008-2015 Stas Fomin, Vitaliy Filippov
 * http://wiki.4intra.net/DocExport
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */

/**
 * INSTALLATION:
 * 1) Put require_once("$IP/extensions/DocExport/DocExport.php"); into your LocalSettings.php
 * 2) DocExport-templates.xml file contains some useful M$Word voodoo magic templates.
 *    Import it into your Wiki via Special:Import to use them.
 * 3) Install cleanmonobook skin from mediawiki4intranet if you want to use "Clean HTML"
 *
 * FEATURES:
 * 1) Content-action tab "purge" is added - click it to clear article cache
 * 2) "->m$word", "->openoffice" links are added to toolbox - click to open article in m$word/OO.o
 * 3) "Clean HTML" link is also added - the intent is to allow display of "clean" page version, without
 *    any toolboxes and navigation, but WITH normal page styles. Link leads to &useskin=cleanmonobook
 *    by default ("cleanmonobook" skin is required). To change it, set the following variable:
 *    $egDocexportCleanHtmlParams = "useskin=cleanmonobook";
 *    To disable:
 *    $egDocexportCleanHtmlParams = false;
 */

if (!defined('MEDIAWIKI'))
{
    ?>
<p>This is the DocExport extension. To enable it, put </p>
<pre>require_once("$IP/extensions/DocExport/DocExport.php");</pre>
<p>at the bottom of your LocalSettings.php.</p>
    <?php
    exit(1);
}

$wgHooks['SkinTemplateContentActions'][] = 'DocExport::onSkinTemplateContentActions';
$wgHooks['UnknownAction'][]              = 'DocExport::onUnknownAction';
$wgHooks['SkinTemplateNavigation'][]     = 'DocExport::onSkinTemplateNavigation';
$wgHooks['SkinTemplateToolboxEnd'][]     = 'DocExport::SkinTemplateToolboxEnd';
$wgHooks['MagicWordwgVariableIDs'][]     = 'DocExport::MagicWordwgVariableIDs';
$wgHooks['ParserGetVariableValueSwitch'][] = 'DocExport::ParserGetVariableValueSwitch';
$wgHooks['ParserFirstCallInit'][]        = 'DocExport::ParserFirstCallInit';

$wgExtensionMessagesFiles['DocExport'] = dirname(__FILE__).'/DocExport.i18n.php';
$wgExtensionFunctions[] = 'DocExport::Setup';
$wgExtensionCredits['other'][] = array(
    'name'        => 'DocExport',
    'author'      => 'Stas Fomin',
    'version'     => DocExport::$version,
    'description' => 'Adds 3 new actions for pages: render as HTML for M$WORD / OpenOffice, purge article',
    'url'         => 'http://wiki.4intra.net/DocExport',
);

$egDocexportCleanHtmlParams = "useskin=cleanmonobook";

class DocExport
{
    static $version     = '1.5 (2015-10-20)';
    static $required_mw = '1.19';
    static $actions     = NULL;
    static $css         = '';
    static $expandingUrls = false;

    static function Setup()
    {
        // A current MW-Version is required so check for it...
        wfUseMW(self::$required_mw);
    }

    //// hooks ////

    // Hook that creates {{DOCEXPORT}} magic word
    static function MagicWordwgVariableIDs(&$mVariablesIDs)
    {
        $mVariablesIDs[] = 'docexport';
        return true;
    }

    // Hook that evaluates {{DOCEXPORT}} magic word
    static function ParserGetVariableValueSwitch(&$parser, &$varCache, &$index, &$ret)
    {
        if ($index == 'docexport')
            $ret = !empty($parser->extIsDocExport) ? '1' : '';
        return true;
    }

    // Parser function used to add custom css for export
    static function docexportcss($parser, $args)
    {
        self::$css .= trim($args)."\n";
        return '';
    }

    // Sets function hook to parser
    static function ParserFirstCallInit($parser)
    {
        $parser->setFunctionHook('docexportcss', 'DocExport::docexportcss');
        return true;
    }

    // Hook used to display a tab in standard skins
    static function onSkinTemplateContentActions(&$content_actions)
    {
        self::fillActions();
        if (!empty(self::$actions['purge']))
            $content_actions['purge'] = self::$actions['purge'];
        return true;
    }

    // Hook used to display a tab in Vector (MediaWiki 1.16+) skin
    // Also standard skins in MediaWiki 1.18
    static function onSkinTemplateNavigation(&$skin, &$links)
    {
        self::fillActions();
        if (!empty(self::$actions['purge']))
            $links[get_class($skin) == 'SkinVector' ? 'views' : 'actions']['purge'] = self::$actions['purge'];
        return true;
    }

    // Hook for handling DocExport actions
    static function onUnknownAction($action, $article)
    {
        $action = strtolower($action);
        if ($action == 'export2word' || $action == 'export2oo')
        {
            self::sendTo($article, substr($action, 7));
            return false;
        }
        return true;
    }

    // Output our TOOLBOX links
    static function SkinTemplateToolboxEnd($tpl)
    {
        self::fillActions();
        foreach (array('cleanmonobook', 'export2word', 'export2oo') as $link)
            if (!empty(self::$actions[$link]))
                print '<li id="t-'.$link.'" title="'.
                    htmlspecialchars(self::$actions[$link]['tooltip']).
                    '"><a href="'.self::$actions[$link]['href'].'">'.
                    htmlspecialchars(self::$actions[$link]['text']).
                    '</a></li>';
        return true;
    }

    //// non-hooks ////

    // fills self::$actions for current title
    static function fillActions()
    {
        // Actions already filled?
        if (self::$actions !== NULL)
            return true;
        self::$actions = array();

        global $wgTitle, $wgRequest, $egDocexportCleanHtmlParams;
        if (!$wgTitle)
            return false;

        $disallow_actions = array('edit', 'submit'); // disallowed actions
        $action = $wgRequest->getVal('action');
        $current_ns = $wgTitle->getNamespace();

        // Disable for special pages
        if ($current_ns < 0)
            return false;

        // Disable for edit/preview
        if (in_array($action, $disallow_actions))
            return false;

        self::$actions['export2word'] = array(
            'text' => wfMsg('docexport-msword-export-link'),
            'tooltip' => wfMsg('tooltip-ca-export2word'),
            'href' => $wgTitle->getFullURL('action=export2word'),
            'class' => '',
        );
        self::$actions['export2oo'] = array(
            'text' => wfMsg('docexport-oo-export-link'),
            'tooltip' => wfMsg('tooltip-ca-export2oo'),
            'href' => $wgTitle->getFullURL('action=export2oo'),
            'class' => '',
        );
        self::$actions['purge'] = array(
            'text' => wfMsg('docexport-purge-tab'),
            'tooltip' => wfMsg('tooltip-ca-purge'),
            'href' => $wgTitle->getFullURL('action=purge'),
            'class' => '',
        );
        if ($egDocexportCleanHtmlParams)
        {
            self::$actions['cleanmonobook'] = array(
                'text' => wfMsg('link-cleanmonobook'),
                'tooltip' => wfMsg('tooltip-link-cleanmonobook'),
                'href' => $wgTitle->getLocalURL($egDocexportCleanHtmlParams),
                'class' => '',
            );
        }

        return true;
    }

    // Output HTML code with correct content-type for M$WORD / OO
    static function sendTo($article, $to)
    {
        global $wgParser, $wgOut;
        $html = self::getPureHTML($article);
        $title = $article->getTitle();

        // Fetch styles from MediaWiki:docexport-$to.css, expand templates
        $st = wfMsgNoTrans("docexport-$to.css");
        $st = $wgParser->preprocess($st, Title::makeTitleSafe(NS_MEDIAWIKI, "docexport-$to.css"), new ParserOptions());
        if ($to == 'word')
        {
            // Add styles for HTML list numbering
            $html = self::multinumLists($html, $st);
            // Enable page numbering
            $html = "<div class=\"SectionNumbered\">$html</div>";
        }
        if (!empty(self::$css))
        {
            if (preg_match('/mso-(even|first|)-?(header|footer)/is', self::$css))
            {
                // Remove headers/footers when page is using custom ones
                $st = preg_replace('/mso-(even|first|)-?(header|footer)\s*:[^;]*;\s*/is', '', $st);
            }
            $st = trim($st)."\n".self::$css;
        }

        $html =
            '<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML//EN"><html><head>' .
            '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' .
            ($to == 'word' ? '<meta name=ProgId content=Word.Document>' : '') .
            '<style type="text/css"><!--' . "\n" .
            $st .
            '/*-->*/</style></head><body>' .
            $html .
            '</body></html>';

        $wgOut->setPrintable();
        $wgOut->disable();
        wfResetOutputBuffers();
        header('Content-Type: '.($to == 'word' ? 'application/msword' : 'vnd.oasis.opendocument.text'));
        header('Content-Length: '.strlen($html));
        $filename = $title.($to == 'word' ? '.doc' : '.odp');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        echo $html;
    }

    /* Load HTML content into a DOMDocument */
    static function loadDOM($html)
    {
        $dom = new DOMDocument();
        $oe = error_reporting();
        error_reporting($oe & ~E_WARNING);
        $dom->loadHTML("<?xml version='1.0' encoding='UTF-8'?>".mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        error_reporting($oe);
        return $dom;
    }

    /* Export children of $element to an HTML string */
    static function saveChildren($element, $trim = false)
    {
        $xml = $element->ownerDocument->saveXML($element, LIBXML_NOEMPTYTAG);
        $xml = preg_replace('/^\s*<[^>]*>(.*?)<\/[^\>]*>\s*$/uis', '\1', $xml);
        $xml = preg_replace('#(<(br|input)(\s+[^<>]*[^/])?)></\2>#', '\1 />', $xml);
        return $xml;
    }

    /* Make HTML ordered lists with class=multinum or inside an element with class=multinum
       numbered hierarchically */
    static function multinumLists($html, &$css)
    {
        if (!preg_match('/<([a-z0-9-:]+)[^<>]*class="[^<>\"\'\s]*multinum[^<>]*>/is', $html))
            return $html;
        $maxlevel = array();
        $dom = self::loadDOM($html);
        $stack = array(array($dom->documentElement, 0, false, 0));
        $maxlist = 0;
        while ($stack)
        {
            list($p, $i, $multi, $listindex) = $stack[0];
            if ($i >= $p->childNodes->length)
            {
                array_shift($stack);
                continue;
            }
            $stack[0][1]++;
            $e = $p->childNodes->item($i);
            if ($e->nodeType == XML_ELEMENT_NODE)
            {
                if (!$multi && preg_match('/\bmultinum\b/s', $e->getAttribute('class')))
                {
                    // Begin multinumbered list
                    $stack[0][2] = $multi = 1;
                }
                if ($multi && $e->nodeName == 'li')
                {
                    // Add M$Word pseudo-style
                    $level = $multi-1;
                    $style = "mso-list: l$listindex level$level lfo$level";
                    if (!($a = $e->getAttribute('style')))
                        $e->setAttribute('style', $style);
                    else
                        $a->value = rtrim($a->value, "; \t\r\n") . '; ' . $style;
                }
                elseif ($multi && $e->nodeName == 'ol')
                {
                    if ($multi < 2)
                        $listindex = ++$maxlist;
                    $maxlevel[$multi][$listindex] = true;
                    $multi++;
                }
                if ($e->childNodes->length)
                    array_unshift($stack, array($e, 0, $multi, $listindex));
            }
        }
        // Append CSS classes to $st
        $st = '';
        for ($i = 1; $maxlevel[$i]; $i++)
        {
            $st .= '%'.$i.'\.';
            $k = array_keys($maxlevel[$i]);
            foreach ($k as &$list)
                $list = "@list l$list:level$i";
            $css .= implode(", ", $k) . " { mso-level-text:\"$st\"; }\n";
        }
        return self::saveChildren($dom->documentElement->childNodes->item(0));
    }

    static function getPureHTML($article)
    {
        global $wgUser, $wgParser;

        $title = $article->getTitle();
        while ($title->isRedirect())
        {
            if (!$title->userCan('read'))
                break;
            $title = $article->getRedirectTarget();
            $article = new Article($title);
        }
        // Check read permission
        if (!$title->userCan('read'))
        {
            print '<html><body>DocExport: Permission Denied</body></html>';
            exit;
        }

        self::expandLocalUrls(true);
        $parserOptions = ParserOptions::newFromUser($wgUser);
        $parserOptions->setEditSection(false);
        $parserOptions->setTidy(true);
        $wgParser->mShowToc = false;
        $wgParser->extIsDocExport = true;
        $parserOutput = $wgParser->parse($article->preSaveTransform($article->getContent())."\n", $title, $parserOptions);
        $wgParser->extIsDocExport = false;
        $html = self::html2print($parserOutput->getText(), $title);
        self::expandLocalUrls(false);

        return $html;
    }

    static function html2print($html, $title = NULL)
    {
        $html = self::clearScreenOnly($html);
        // Remove [svg] graphviz links
        $html = str_replace('[svg]</a>', '</a>', $html);
        // Remove hyperlinks to images on the server
        $html = self::clearHrefs($html);
        // Remove enclosing <object type="image/svg+xml"> for SVG+PNG images
        $html = preg_replace('#<object[^<>]*type=[\"\']?image/svg\+xml[^<>]*>(.*?)</object\s*>#is', '\1', $html);
        // Replace links to anchors within self to just anchors
        if ($title)
            $html = str_replace('href="'.$title->getLocalUrl().'#', 'href="#', $html);
        return $html;
    }

    static function expandLocalUrls($enable = true)
    {
        global $wgArticlePath, $wgScriptPath, $wgUploadPath, $wgStylePath, $wgMathPath, $wgLocalFileRepo;
        static $originalPaths = null;

        $prev = self::$expandingUrls;
        if ($enable)
        {
            if (!self::$expandingUrls)
            {
                self::$expandingUrls = true;

                // Save original values.
                $originalPaths = array(
                    $wgArticlePath, $wgScriptPath, $wgUploadPath, $wgStylePath, $wgMathPath, $wgLocalFileRepo['url']
                );

                // Expand paths.
                $wgArticlePath = wfExpandUrl($wgArticlePath);
                $wgScriptPath = wfExpandUrl($wgScriptPath);
                $wgUploadPath = wfExpandUrl($wgUploadPath);
                $wgStylePath = wfExpandUrl($wgStylePath);
                $wgMathPath = wfExpandUrl($wgMathPath);
                $wgLocalFileRepo['url'] = wfExpandUrl($wgLocalFileRepo['url']);

                // Destroy existing RepoGroup, if any.
                RepoGroup::destroySingleton();
            }
        }
        else
        {
            if (self::$expandingUrls)
            {
                self::$expandingUrls = false;

                // Restore original values.
                list($wgArticlePath, $wgScriptPath, $wgUploadPath, $wgStylePath, $wgMathPath, $wgLocalFileRepo['url']) = $originalPaths;

                // Destroy existing RepoGroup, if any.
                RepoGroup::destroySingleton();
            }
        }

        return $prev;
    }

    static function clearScreenOnly($text)
    {
        return self::cutBlock($text, "/<\\s*div\\s*class=\"(screenonly|printfooter)\"/i","/<\\/\\s*div\\s*>/i");
    }

    static function clearHrefs($text)
    {
        global $wgScriptPath;
        $regexp = "/<a[^<>]*href=[\"\']?" . str_replace("/", "\/", $wgScriptPath) . "\/images[^<>]*>/i";
        return self::stripTags($text, $regexp, '#</\s*a\s*>#i');
    }

    static function stripTags($text, $startRegexp, $endRegexp)
    {
        $stripped = '';
        while ('' != $text)
        {
            $p = preg_split($startRegexp, $text, 2);
            $stripped .= $p[0];
            if ((count($p) < 2) || ('' == $p[1]))
                $text = '';
            else
            {
                $q = preg_split($endRegexp, $p[1], 2);
                $stripped .= $q[0];
                $text = $q[1];
            }
        }
        return $stripped;
    }

    static function cutBlock($text, $startRegexp, $endRegexp)
    {
        $stripped = '';
        while ('' != $text)
        {
            $p = preg_split($startRegexp, $text, 2);
            $stripped .= $p[0];
            if ((count($p) < 2) || ('' == $p[1]))
                $text = '';
            else
            {
                $q = preg_split($endRegexp, $p[1], 2);
                $text = $q[1];
            }
        }
        return $stripped;
    }
}
