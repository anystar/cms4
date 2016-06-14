<?php

class page {
	
	public static function render($f3, $params)
	{

		// '/' is index
		if ($params[0] == "/")
			$params['page']	= "index";
		
		if (page::hasInit())
		{
			// Does page file exist?
			if (page::exists($params['page'])) 
			{
				page::retreiveContent($f3, $params['page']);
			} else {
				$f3->error("404");
			}
		}

		echo Template::instance()->render($params['page'] . ".html");
	}

	public static function exists($page) 
	{
		if (file_exists($page . ".html")) 
			return true;
		else 
			return false;
	}

	public static function retreiveContent($f3, $page) {
		$db = $f3->get("DB");
		$blocksraw = $db->exec('SELECT * FROM contentBlocks WHERE page=? OR page="all"', $page);
		
		$bc = array(); // Blocks compiled
		$ck_instances = array();
		foreach ($blocksraw as $block) {
			if ($block["contentName"] != "")
			{
				// Wrap in content editable
				if (admin::$signed) {
					$block["content"] = "<div contenteditable='true' id='".$block["page"]."_".$block["id"]."'>" . $block["content"] . "</div>";
					$ck_instances[] = $block["page"]."_".$block["id"];
				}

				$f3->set($block["contentName"], $block["content"]);
			}
		}

		if (admin::$signed)
		{
			$f3->set("ck_instances", $ck_instances);

			$tmp = $f3->get("UI");
			$f3->set('UI', $f3->CMS."adminUI/");
			$inlinecode = Template::instance()->render("ckeditor_inline.js");
			$f3->set('UI', $tmp);

			$f3->concat("ckeditor", $inlinecode);
		}
	}

	public static function loadAll ($f3) {
		$db = $f3->get("DB");
		$result = $db->exec('SELECT * FROM contentBlocks');

		foreach ($result as $contentBlock)
		{
			$blocks[$contentBlock["page"]][] = $contentBlock;
		}
		
		$f3->set("pages", $blocks);
	}

	public static function save_inline($f3) 
	{
		$pageID = filter_var($f3->get("POST.editorID"), FILTER_SANITIZE_NUMBER_INT);
		$pageContent = $f3->get("POST.editabledata");

		$db = $f3->get("DB");

		$db->exec("UPDATE contentBlocks SET content=:content WHERE id=:id", array(
			":id"=>$pageID,
			":content"=>$pageContent
		));
	}

	public static function ckeditor($f3) 
	{
		echo Template::instance()->render("ckeditor_config.js", "text/javascript");
	}

	public static function hasInit() {
		$db = base::instance()->DB;

		$result = $db->exec("SELECT name FROM sqlite_master WHERE type='table' AND name='contentBlocks'");

		if ($result)
			return true;
		else
			return false;
	}

	public static function generate() {

		$db = base::instance()->DB;

		$db->exec("CREATE TABLE IF NOT EXISTS 'contentBlocks' ('id' INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, 'page' TEXT, 'content' TEXT, 'lastUpdated' DATETIME DEFAULT CURRENT_TIMESTAMP,'contentName' TEXT);");
	}
}