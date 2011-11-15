<?php
/* Internationalization file for the DocExport Extension */

$messages = array();

$messages['en'] = array(
    'docexport-msword-export-link' => '→M$WORD',
    'docexport-oo-export-link'     => '→OOffice',
    'docexport-purge-tab'          => 'purge',
    'tooltip-ca-export2word'       => 'Export to MS Word',
    'tooltip-ca-export2oo'         => 'Export to Open Office',
    'tooltip-ca-purge'             => 'Purge/refresh article, clear cache…',
    'link-cleanmonobook'           => 'Clean page',
    'tooltip-link-cleanmonobook'   => 'Show clean page version, without any toolboxes/navigation, but with screen styles - useful for saving in HTM/MHT formats.',

    // CSS styles for OpenOffice export
    'docexport-oo.css'             => '{{MediaWiki:docexport-oo-orig.css}}',
    'docexport-oo-orig.css'        =>
'<!-- Do not edit this page. Edit MediaWiki:docexport-oo.css instead.
These are the original styles for wiki article export to OpenOffice. -->
td, th { vertical-align: top; }
p, li { text-align: justify; }
body { font-size: 12pt; }
.maximg img { width: 17cm; height: auto !important; }
',

    // CSS styles for M$Word export
    'docexport-word.css'           => '{{MediaWiki:docexport-word-orig.css}}',
    'docexport-word-orig.css'      =>
'<!-- Do not edit this page. Edit MediaWiki:docexport-word.css instead.
These are the original styles for wiki article export to M$ Word. -->
p, table, li, dt, dl, h1, h2, h3, h4, h5, h6 { font-family: Arial; }
td, th { vertical-align: top; }
dt { font-weight: bold; }
p, li { text-align: justify; }
body { font-size: 12pt; }
ul li { list-style-type: square; }
img { max-width: 17cm; height: auto !important; }
.maximg img { width: 642px; height: auto !important; }
@page SectionNumbered {
	mso-even-header:url("{{SERVER}}{{SCRIPTPATH}}/extensions/DocExport/header.htm") eh1;
	mso-header:url("{{SERVER}}{{SCRIPTPATH}}/extensions/DocExport/header.htm") h1;
	mso-even-footer:url("{{SERVER}}{{SCRIPTPATH}}/extensions/DocExport/header.htm") ef1;
	mso-footer:url("{{SERVER}}{{SCRIPTPATH}}/extensions/DocExport/header.htm") f1;
	mso-first-header:url("{{SERVER}}{{SCRIPTPATH}}/extensions/DocExport/header.htm") fh1;
	mso-first-footer:url("{{SERVER}}{{SCRIPTPATH}}/extensions/DocExport/header.htm") ff1;
}
div.SectionNumbered { page: SectionNumbered; }
@page SectionLandscape {
	mso-page-orientation: landscape;
	size: 297mm 210mm;
	mso-even-header:url("{{SERVER}}{{SCRIPTPATH}}/extensions/DocExport/header.htm") eh1;
	mso-header:url("{{SERVER}}{{SCRIPTPATH}}/extensions/DocExport/header.htm") h1;
	mso-even-footer:url("{{SERVER}}{{SCRIPTPATH}}/extensions/DocExport/header.htm") ef1;
	mso-footer:url("{{SERVER}}{{SCRIPTPATH}}/extensions/DocExport/header.htm") f1;
	mso-first-header:url("{{SERVER}}{{SCRIPTPATH}}/extensions/DocExport/header.htm") fh1;
	mso-first-footer:url("{{SERVER}}{{SCRIPTPATH}}/extensions/DocExport/header.htm") ff1;
}
div.SectionLandscape { page: SectionLandscape; }
div.SectionLandscape .maximg img { width: 25cm; }
'
);

$messages['ru'] = array(
    'docexport-msword-export-link' => '→M$WORD',
    'docexport-oo-export-link'     => '→OOffice',
    'docexport-purge-tab'          => 'Обновить',
    'tooltip-ca-export2word'       => 'Экспорт в MS Word',
    'tooltip-ca-export2oo'         => 'Экспорт в Open Office',
    'tooltip-ca-purge'             => 'Обновить статью, сбросить кеш…',
    'link-cleanmonobook'           => 'Чистый HTML',
    'tooltip-link-cleanmonobook'   => 'Показать версию страницы без навигации, но с экранными стилями - удобно для сохранения в HTM/MHT-форматы.',
);

$magicWords = array();

$magicWords['en'] = array(
    'docexport' => array('1', 'DOCEXPORT'),
    'docexportcss' => array('1', 'docexportcss'),
);
