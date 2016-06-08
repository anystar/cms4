<?php

admin::init();
class admin {

	static public $signed = false;

	static public function init ()
	{	
		$f3 = f3::instance();

		if ($f3->exists("COOKIE.PHPSESSID"))
			admin::$signed = true;
	}

	static public function dashboard_render ($f3)
	{
		page::loadAll($f3);

		echo Template::instance()->render("dashboard.html");
	}

	static public function pages_admin_render($f3)
	{		
		page::loadAll($f3);

		echo Template::instance()->render("contentBlocks/pages.html");
	}

	static public function page_edit_render($f3, $params)
	{
		page::loadAll($f3);

		foreach ($f3->get("pages") as $pagename=>$page) {
			if ($pagename == $params["page"])
				$editable = $page;
		}

		$f3->set("editable", $editable);

		echo Template::instance()->render("contentBlocks/page_edit.html");
	}

	static public function login_render ()
	{
		echo Template::instance()->render("login.html");
	}

	static public function login ($f3) {
		$post = $f3->get("POST");

		if ($post["user"] == $f3->get("client.email") && $post["pass"] == $f3->get("client.pass"))
		{
			new Session();
			$f3->set("SESSION.user", $post["user"]);

			$f3->set('UI', $f3->CMS."adminUI/");

			if ($f3->get("POST.redirecttolive"))
				$f3->reroute("/");
			else
				admin::dashboard_render($f3);
		}
		else
			admin::login_render();
	}

	static public function logout ($f3) {
		
		$f3->clear("SESSION");

		if (isset($_SERVER['HTTP_COOKIE'])) {
		    $cookies = explode(';', $_SERVER['HTTP_COOKIE']);
		    foreach($cookies as $cookie) {
		        $parts = explode('=', $cookie);
		        $name = trim($parts[0]);
		        setcookie($name, '', time()-1000);
		        setcookie($name, '', time()-1000, '/');
		    }
		}

		echo Template::instance()->render("login.html");
	}

	static public function help($f3) {
		echo Template::instance()->render("help.html");
	}

	static public function settings($f3) {
		echo Template::instance()->render("settings.html");
	}

	static public function contact ($f3) {
		if (contact::exists())
			echo Template::instance()->render("contactForm/contact.html");
		else
			echo Template::instance()->render("contactForm/nocontact.html");
	}

	static public function theme($f3) {

		$tmp = $f3->UI;
		$f3->UI = $f3->CMS . "adminUI/";
		echo Template::instance()->render("css/adminstyle.css", "text/css");
		$f3->UI = $tmp;
	}
}
