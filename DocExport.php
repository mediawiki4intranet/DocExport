<?php

/**
 * MediaWiki DocExport extension
 * Adds 3 new actions ->m$word, ->openoffice, purge to all Wiki pages
 * Version 1.3 compatible with MediaWiki 1.16 and Vector skin
 *
 * Copyright © 2008-2011 Stas Fomin, Vitaliy Filippov
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
$wgExtensionMessagesFiles['DocExport'] = dirname(__FILE__).'/DocExport.i18n.php';
$wgExtensionFunctions[] = 'DocExport::Setup';
$wgExtensionCredits['other'][] = array(
    'name'        => 'DocExport',
    'author'      => 'Stas Fomin',
    'version'     => DocExport::$version,
    'description' => 'Adds 3 new actions for pages: render as HTML for M$WORD / OpenOffice, purge article',
    'url'         => 'http://wiki.4intra.net/DocExport',
);

class DocExport
{
    static $version     = '1.3 (2011-02-09)';
    static $required_mw = '1.11';
    static $actions     = NULL;

    static function Setup()
    {
        // A current MW-Version is required so check for it...
        wfUseMW(self::$required_mw);
    }

    //// hooks ////

    // Hook for standard skins
    static function onSkinTemplateContentActions(&$content_actions)
    {
        self::fillActions();
        $content_actions = array_merge($content_actions, self::$actions);
        return true;
    }

    // Hook for Vector (MediaWiki 1.16+) skin
    static function onSkinTemplateNavigation(&$skin, &$links)
    {
        self::fillActions();
        $links['actions'] = array_merge($links['actions'], self::$actions);
        $links['views'][] = $links['actions']['purge'];
        unset($links['actions']['purge']);
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

    //// non-hooks ////

    // fills self::$actions for current title
    static function fillActions()
    {
        // Actions already filled?
        if (self::$actions !== NULL)
            return true;
        self::$actions = array();

        global $wgTitle, $wgRequest;

        $disallow_actions = array('edit', 'submit'); // disallowed actions
        $action = $wgRequest->getVal('action');
        $current_ns = $wgTitle->getNamespace();

        // Disable for special pages
        if ($current_ns < 0)
            return false;

        // Disable for edit/preview
        if (in_array($action, $disallow_actions))
            return false;

        wfLoadExtensionMessages('DocExport');

        self::$actions['export2word'] = array(
            'class' => false,
            'text'  => wfMsg('docexport-msword-export-link'),
            'href'  => $wgRequest->appendQuery('action=export2word')
        );
        self::$actions['export2oo'] = array(
            'class' => false,
            'text'  => wfMsg('docexport-oo-export-link'),
            'href'  => $wgTitle->getFullURL('action=export2oo')
        );
        self::$actions['purge'] = array(
            'class' => false,
            'text'  => wfMsg('docexport-purge-tab'),
            'href'  => $wgTitle->getFullURL('action=purge')
        );

        return true;
    }

    // Output HTML code with correct content-type for M$WORD / OO
    static function sendTo($article, $to)
    {
        global $egDocExportStyles;
        $html = self::getPureHTML($article);
        $title = $article->getTitle();

        $st = $egDocExportStyles[$to];
        if (!$st)
            $st = dirname(__FILE__) . "/styles-$to.css";

        $html =
            '<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML//EN"><html><head>' .
            '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' .
            '<style type="text/css"><!--' . "\n" .
            @file_get_contents($st) .
            "\n" . '/*-->*/</style></head><body>' .
            $html .
            '</body></html>';

        header('Content-type: '.($to == 'word' ? 'application/msword' : 'vnd.oasis.opendocument.text'));
        header('Content-Length: '.strlen($html));
        $filename = $title.($to == 'word' ? '.doc' : '.odp');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        echo $html;
    }

    static function getPureHTML($article)
    {
        global $wgOut, $wgUser, $wgParser;

        $title = $article->getTitle();
        if (method_exists($title, 'userCanReadEx') && !$title->userCanReadEx())
        {
            // Support HaloACL rights
            print '<html><body>DocExport: Permission Denied</body></html>';
            exit;
        }

        $wgOut->setPrintable();
        $wgOut->disable();
        $parserOptions = ParserOptions::newFromUser($wgUser);
        $parserOptions->setEditSection(false);
        $parserOptions->setTidy(true);
        $wgParser->mShowToc = false;
        $parserOutput = $wgParser->parse($article->preSaveTransform($article->getContent()) ."\n\n", $title, $parserOptions);

        $bhtml = $parserOutput->getText();
        $html = self::html2print($bhtml);
        return $html;
    }

    static function html2print($html)
    {
        global $wgScriptPath, $wgServer;
        $html = self::clearScreenOnly($html);
        $html = str_replace('[svg]</a>', '</a>', $html);
        $html = self::clearHrefs($html);
        $html = str_replace('src="'.$wgScriptPath, 'src="'.$wgServer.$wgScriptPath, $html);
        return $html;
    }

    static function clearScreenOnly($text)
    {
        return self::cutBlock($text, "/<\\s*div\\s*class=\"(screenonly|printfooter)\"/i","/<\\/\\s*div\\s*>/i");
    }

    static function clearHrefs($text)
    {
        global $wgScriptPath;
        $regexp = "/<a href=\"". str_replace("/","\/",$wgScriptPath) . "\/images[^\"]+\">/i";
        return self::stripTags($text, $regexp, "/<\\/\\s*a\\s*>/i");
    }

    static function stripTags($text, $startRegexp, $endRegexp)
    {
        $stripped = '';

        while ('' != $text)
        {
            $p = preg_split($startRegexp, $text, 2);
            $stripped .= $p[0];
            if ((count($p) < 2) || ('' == $p[1])) {
                $text = '';
            } else {
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
