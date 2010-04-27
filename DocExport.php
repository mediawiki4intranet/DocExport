<?php

if (!defined('MEDIAWIKI'))
{
    ?>
<p>This is the DocExport extension. To enable it, put </p>
<pre>require_once("$IP/extensions/DocExport/DocExport.php");</pre>
<p>at the bottom of your LocalSettings.php.</p>
    <?php
    exit(1);
}

class DocExport
{
    var $title;
    var $article;
    var $html;
    var $parserOptions;
    var $bhtml;

    var $messagesLoaded = false;
    var $version     = '1.2 (2008-04-01)';
    var $required_mw = '1.11';
    var $actions = array('export2word','export2oo');

    function __construct()
    {
    }

    public function Setup()
    {
        global $wgExtensionCredits;

        // A current MW-Version is required so check for it...
        wfUseMW($this->required_mw);

        if ($this->messagesLoaded == false)
            $this->onLoadAllMessages();

        $wgExtensionCredits['other'][] = array(
            'name'        => 'DocExport',
            'author'      => 'Stas Fomin',
            'version'     => $this->version,
            'description' => 'Renders an article/page as HTML for M$WORD',
            'url'         => '',
        );

        if ($this->config['debug'] == true)
            error_reporting(E_ALL);
    }

    public function onSkinTemplateContentActions(&$content_actions)
    {
        global $wgTitle,$wgRequest;

        $disallow_actions = array('edit', 'submit'); // disallowed actions
        $values = new webRequest();
        $action = $values->getVal('action');
        $current_ns = $wgTitle->getNamespace();

        if ($current_ns < 0)
            return true;
        if (in_array($action, $disallow_actions))
            return true;

        $docexport_action['export2word'] = array(
            'class' => false,
            'text'  => wfMsg('docexport-msword-export-link'),
            'href'  => $wgRequest->appendQuery('action=export2word')
        );
        $docexport_action['export2oo'] = array(
            'class' => false,
            'text'  => wfMsg('docexport-oo-export-link'),
            'href'  => $wgTitle->getFullURL('action=export2oo')
        );
        $docexport_action['purge'] = array(
            'class' => false,
            'text'  => wfMsg('docexport-purge-tab'),
            'href'  => $wgTitle->getFullURL('action=purge')
        );
        $content_actions = array_merge($content_actions, $docexport_action);
        return true;
    }

    function getPureHTML($article)
    {
        global $wgRequest;
        global $wgOut;
        global $wgUser;
        global $wgParser;
        global $wgScriptPath;
        global $wgServer;

        $title = $article->getTitle();
        if (method_exists($title, 'userCanReadEx') && !$title->userCanReadEx())
        {
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
        $html = $this->html2print($bhtml);
        return $html;
    }

    function sendTo($article, $to)
    {
        global $egDocExportStyles;
        $html = $this->getPureHTML($article);
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

    public function onUnknownAction($action, $article)
    {
        // Here comes all the stuff to show the form and parse it...
        global $wgTitle, $wgOut, $wgUser;

        // Check the requested action
        // return if not for w2l
        $action = strtolower($action);
        if (!in_array($action, $this->actions))
        {
            // Not our action, so return!
            return true;
        }

        if ($action == 'export2word' || $action == 'export2oo')
            $this->sendTo($article, substr($action, 7));
        return false;
    }

    public function onLoadAllMessages()
    {
        global $wgMessageCache;
        if ($this->messagesLoaded)
            return true;
        $this->messagesLoaded = true;
        require(dirname(__FILE__) . '/DocExport.i18n.php');
        foreach ($messages as $lang => $langMessages)
            $wgMessageCache->addMessages($langMessages, $lang);
        return true;
    }

    private function html2print($html)
    {
        global $wgScriptPath, $wgServer, $wgScript;

        $html = $this->clearScreenOnly($html);
        $html = str_replace('[svg]</a>','</a>',$html);
        $html = $this->clearHrefs($html);
        $html = str_replace('src="'.$wgScriptPath,'src="'.$wgServer.$wgScriptPath,$html);
        return $html;
    }

    private function clearScreenOnly($text)
    {
        return $this->cutBlock($text,"/<\\s*div\\s*class=\"(screenonly|printfooter)\"/i","/<\\/\\s*div\\s*>/i");
    }

    private function clearHrefs($text)
    {
        global $wgScriptPath;
        $regexp = "/<a href=\"". str_replace("/","\/",$wgScriptPath) . "\/images[^\"]+\">/i";
        return $this->stripTags($text,$regexp,"/<\\/\\s*a\\s*>/i");
    }

    private function stripTags($text, $startRegexp, $endRegexp)
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

    private function cutBlock($text, $startRegexp, $endRegexp)
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

function wfSpecialDocExport()
{
    global $IP, $wgMessageCache;
    wfLoadExtensionMessages('DocExport');
    $wgMessageCache->addMessages(
        array(
            'specialpagename' => 'DocExport',
            'docexport'       => 'DocExport',
        )
    );
}

$DocExport = new DocExport();

$wgHooks['LoadAllMessages'][]            = &$DocExport;
$wgHooks['SkinTemplateContentActions'][] = &$DocExport;
$wgHooks['UnknownAction'][]              = &$DocExport;

$wgExtensionFunctions[] = array(&$DocExport, 'Setup');
