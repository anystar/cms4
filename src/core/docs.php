<?php

class docs {

    function __construct($settings) {
        GLOBAL $ROOTDIR;

        $f3 = base::instance();

        Template::instance()->extend('editor', 'editor::render');

        if ($f3->exists("GET.docs") || $f3->exists("GET.doc") || $f3->exists("GET.help"))
        {
            // Organise all the snippts
            foreach ($settings["scripts"] as $key=>$script) {

                if (file_exists($ROOTDIR."/cms/coreUI/docs/".$script["class"].".html")) {

                    $f3->SETTINGS["scripts"][$key]["docs"] = Template::instance()->render("docs/".$script["class"].".html", null, $script);
                }
            }
    
            ob_start('ob_gzhandler') OR ob_start();
    
            $f3->doc_snippets = $doc_snippets;
    
            echo Template::instance()->render("/docs/index.html");
            $f3->abort();
        }
    }

}

class editor extends \Template\TagHandler {
	function build ($attr, $innerhtml)
	{
        $string = '<?php $id=uniqid(); ?>';
        $string .= '<div style="font-size:16px;" id="editor_<?= ($id) ?>">';
        $string .= htmlentities(trim($innerhtml));
        $string .= '</div>';

        $string .= '
        <script>
            var editor = ace.edit("editor_<?= ($id) ?>");
            editor.setTheme("ace/theme/textmate");
            editor.session.setMode("ace/mode/'.$attr["mode"].'");
            editor.setOptions({
                maxLines: 25
            });
        </script>
        ';

		// if ($attr!=null)
		// 	$attr = $this->resolveParams($attr);

		return $string;
	}
}