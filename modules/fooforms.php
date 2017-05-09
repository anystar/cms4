<?php
/**
 *  FooForms - a collection of Form related HTML-Tag handlers
 *
 *	The contents of this file are subject to the terms of the GNU General
 *	Public License Version 3.0. You may not use this file except in
 *	compliance with the license. Any of the license terms and conditions
 *	can be waived if you get permission from the copyright holder.
 *
 *	Copyright (c) 2015 ~ ikkez
 *	Christian Knuth <ikkez0n3@gmail.com>
 *
 *		@version: 0.2.0
 *		@date: 14.07.2015
 *
 **/

class fooforms extends Prefab {

	function __construct($namespace) {
		$f3 = base::instance();

		// Only process html routes
		if (mime_content_type2($f3->FILE) != "text/html")
			return;

		$tmpl = \Template::instance();
		$tmpl->extend('contactform','contactforma::render');
		$tmpl->extend('input','Input::render');
		$tmpl->extend('textarea','Textarea::render');
		$tmpl->extend('select','Select::render');
		$tmpl->extend('option','Option::render');

		// We have submitted the form
		if ($f3->exists("POST.contactform_submit")) {
			$id = $f3->get("POST.contactform_submit");

			// form settings exist 
			if ($settings = cache::instance()->get("contactforms[".$id."]")) {

				// We are on the correct route path to be submitting
				if (isroute($settings["postPath"])) {

					// We've used it, lets unset it.
					unset($f3->POST["contactform_submit"]);

					// email to send to, data, template
					$this->send_email($settings["sendto"], $f3->POST, $settings["template"]);
				}
			}
		}
	}


	function send_email ($sendto, $form, $template)
	{
		$f3 = base::instance();
		$db = $f3->get("DB");

		k($template);

		//$toName = setting("name");
		//$subject = setting("subject");

		//$fromAddress = $f3->get("fromAddress");


 		// if ($f3->exists("fromAddress"))
	 	// 	$fromName = $f3->get("fromName");
	 	// else
	 	// 	$fromName = "Website visitor";

		$smtp = new SMTP($this->smtp_server, $this->port, "", "", "");

		$smtp->set('To', '"'.$toName.'" <'.$toAddress.'>');
		$smtp->set('From', '"'.$fromName.'" <admin@webworksau.com>');
		$smtp->set('Reply-To', '"'.$fromName.'" <'.$fromAddress.'>');
		$smtp->set('Subject', $subject);
		$smtp->set('Content-Type', 'text/html');


		// if (file_exists(getcwd()."/".$this->email_template))

		// 	// Use custom email template from client directory
		// 	$body = Template::instance()->render($this->email_template);
		// else
		// {
		// 	// Temp hive to generate a html snippet
		// 	$temphive = [
		// 		"contact" => $f3->get($this->namespace),
		// 	];

		// 	// Use our generic email template
		// 	$body = Template::instance()->render("/contact/email_template/generic_email_template.html", null, $temphive);
		// }

		// foreach ($f3->get($this->namespace)["form"] as $key=>$field)
		// 	$contents[$key] = $field["value"];

		// $f3->DB->exec("INSERT INTO `{$this->namespace}_archived` (contents, `date`) VALUES (?, ?)", [json_encode($contents), time()]);

		$smtp->send("this is a test email.");

		return true;
	}

}


/**
 *	Abstract TagHandler for creating own Tag-Element-Renderer
 *
 *	The contents of this file are subject to the terms of the GNU General
 *	Public License Version 3.0. You may not use this file except in
 *	compliance with the license. Any of the license terms and conditions
 *	can be waived if you get permission from the copyright holder.
 *
 *	Copyright (c) 2015 ~ ikkez
 *	Christian Knuth <ikkez0n3@gmail.com>
 *
 *	@version: 0.3.0
 *	@date: 14.07.2015
 *
 **/

class contactforma extends TagHandler {

	function build ($attr, $content)
	{
		$f3 = base::instance();

		// Register contact form module
		if (array_key_exists("id", $attr))
			$id = $attr["id"];
		else
			$id = 0;


		if (array_key_exists("src", $attr))
			$settings["postPath"] = $attr["src"];

		// Always post to the same page the form is located on.
		$attr["src"] = $f3->SCHEME."://".$f3->HOST.$f3->URI;

		if (array_key_exists("sendto", $attr))
			{ $settings["successPage"] = $attr["success"]; unset($attr["success"]); }
		else
			{ $settings["successPage"] = "?success=true"; }

		if (array_key_exists("sendto", $attr))
			{ $settings["sendto"] = $attr["sendto"]; unset($attr["sendto"]); }
		else
			{ $settings["sendto"] = $f3->$SETTINGS["smtp"]["sendto"]; }

		$content = $this->tmpl->build($content);

		$attr["method"] = "POST";

		// resolve all other / unhandled tag attributes
		if ($attr!=null)
			$attr = $this->resolveParams($attr);

		$hiddenInput = '<input type="hidden" name="contactform_submit" value="'.$id.'">';

		cache::instance()->set("contactforms[".$id."]", $settings, 0);

		return '<form ' . $attr . '>' . $content . $hiddenInput . '</form>';
	}

}


abstract class TagHandler extends Prefab {

	/** @var \Template */
	protected $tmpl;

	function __construct() {
		$this->tmpl = \Template::instance();
	}

	/**
	 * build tag string
	 * @param $attr
	 * @param $content
	 * @return string
	 */
	abstract function build($attr,$content);

	/**
	 * incoming call to render the given node
	 * @param $node
	 * @return string
	 */
	static public function render($node) {
		$attr = $node['@attrib'];
		unset($node['@attrib']);
		/** @var TagHandler $handler */
		$handler = static::instance();
		$content = $handler->resolveContent($node, $attr);
		return $handler->build($attr,$content);
	}

	/**
	 * render the inner content
	 * @param array $node
	 * @param array $node
	 * @return string
	 */
	protected function resolveContent($node, $attr) {
		return (isset($node[0])) ? $this->tmpl->build($node) : '';
	}

	/**
	 * general bypass for unhandled tag attributes
	 * @param array $params
	 * @return string
	 */
	protected function resolveParams(array $params) {
		$out = '';
		foreach ($params as $key => $value) {
			// build dynamic tokens
			if (preg_match('/{{(.+?)}}/s', $value))
				$value = $this->tmpl->build($value);
			if (preg_match('/{{(.+?)}}/s', $key))
				$key = $this->tmpl->build($key);
			// inline token
			if (is_numeric($key))
				$out .= ' '.$value;
			// value-less parameter
			elseif ($value == NULL)
				$out .= ' '.$key;
			// key-value parameter
			else
				$out .= ' '.$key.'="'.$value.'"';
		}
		return $out;
	}

	/**
	 * export a stringified token variable
	 * to handle mixed attribute values correctly
	 * @param $val
	 * @return string
	 */
	protected function tokenExport($val) {
		$split = preg_split('/({{.+?}})/s', $val, -1,
			PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
		foreach ($split as &$part) {
			if (preg_match('/({{.+?}})/s', $part))
				$part = $this->tmpl->token($part);
			else
				$part = "'".$part."'";
			unset($part);
		}
		$val = implode('.', $split);
		return $val;
	}

	/**
	 * export resolved attribute values for further processing
	 * samples:
	 * value 			=> ['value']
	 * {{@foo}} 		=> [$foo]
	 * value-{{@foo}}	=> ['value-'.$foo]
	 * foo[bar][]		=> ['foo']['bar'][]
	 * foo[{{@bar}}][]	=> ['foo'][$bar][]
	 *
	 * @param $attr
	 * @return mixed|string
	 */
	protected function attrExport($attr) {
		$ar_split=preg_split('/\[(.+?)\]/s',$attr,-1,
			PREG_SPLIT_NO_EMPTY|PREG_SPLIT_DELIM_CAPTURE);
		if (count($ar_split)>1) {
			foreach ($ar_split as &$part) {
				if ($part=='[]')
					continue;
				$part='['.$this->tokenExport($part).']';
				unset($part);
			}
			$val = implode($ar_split);
		} else {
			$val = $this->tokenExport($attr);
			$ar_name = preg_replace('/\'*(\w+)(\[.*\])\'*/i','[\'$1\']$2', $val,-1,$i);
			$val = $i ? $ar_name : '['.$val.']';
		}
		return $val;
	}
}


class Input extends TagHandler {

	function __construct() {
		/** @var \Base $f3 */
		$f3 = \Base::instance();
		if (!$f3->exists('template.form.srcKey'))
			$f3->set('template.form.srcKey','POST');
		parent::__construct();
	}

	/**
	 * build tag string
	 * @param $attr
	 * @param $content
	 * @return string
	 */
	function build($attr, $content) {
		$srcKey = \Base::instance()->get('template.form.srcKey');
		if (isset($attr['type']) && isset($attr['name'])) {
			$name = $this->attrExport($attr['name']);

			if (($attr['type'] == 'checkbox') ||
				($attr['type'] == 'radio' && isset($attr['value']))
			) {
				$value = $this->tokenExport(isset($attr['value'])?$attr['value']:'on');
				// static array match
				if (preg_match('/\[\]$/s', $name)) {
					$name=substr($name,0,-2);
					$str='(isset(@'.$srcKey.$name.') && is_array(@'.$srcKey.$name.')'.
						' && in_array('.$value.',@'.$srcKey.$name.'))';
				} else {
					// basic match
					$str = '(isset(@'.$srcKey.$name.') && @'.$srcKey.$name.'=='.$value.')';
					// dynamic array match
					if (preg_match('/({{.+?}})/s', $attr['name'])) {
						$str.= ' || (isset(@'.$srcKey.$name.') && is_array(@'.$srcKey.$name.')'.
							' && in_array('.$value.',@'.$srcKey.$name.'))';
					}
				}
				$str = '{{'.$str.'?\'checked="checked"\':\'\'}}';
				$attr[] = $this->tmpl->build($str);

			} elseif($attr['type'] != 'password' && !array_key_exists('value',$attr)) {
				// all other types, except password fields
				$attr['value'] = $this->tmpl->build('{{ isset(@'.$srcKey.$name.')?@'.$srcKey.$name.':\'\'}}');
			}
		}
		// resolve all other / unhandled tag attributes
		if ($attr!=null)
			$attr = $this->resolveParams($attr);
		// create element and return
		return '<input'.$attr.' />';
	}
}


class Textarea extends TagHandler {

	function __construct() {
		/** @var \Base $f3 */
		$f3 = \Base::instance();
		if (!$f3->exists('template.form.srcKey'))
			$f3->set('template.form.srcKey','POST');
		parent::__construct();
	}

	/**
	 * build tag string
	 * @param $attr
	 * @param $content
	 * @return string
	 */
	function build($attr, $content) {

		$srcKey = \Base::instance()->get('template.form.srcKey');

		if (isset($attr['name'])) {
			$name = $this->attrExport($attr['name']);
			$content = $this->tmpl->build('{{ isset(@'.$srcKey.$name.')?@'.$srcKey.$name.':"'.$content.'"}}');
		}

		// resolve all other / unhandled tag attributes
		$attr = $this->resolveParams($attr);

		// create element and return
		return '<textarea'.$attr.'>'.$content.'</textarea>';
	}
}


class Select extends TagHandler {

	function __construct() {
		/** @var \Base $f3 */
		$f3 = \Base::instance();
		if (!$f3->exists('template.form.srcKey'))
			$f3->set('template.form.srcKey','POST');
		parent::__construct();
	}

	/**
	 * build tag string
	 * @param $attr
	 * @param $content
	 * @return string
	 */
	function build($attr, $content) {
		$srcKey = \Base::instance()->get('template.form.srcKey');
		if (array_key_exists("name", $attr))
			$name = $this->attrExport($attr['name']);
		if (array_key_exists('group', $attr)) {
			$attr['group'] = $this->tmpl->token($attr['group']);
			if (preg_match('/\[\]$/s', $name)) {
				$name=substr($name,0,-2);
				$cond = '(isset(@'.$srcKey.$name.') && is_array(@'.$srcKey.$name.')'.
					' && in_array(@key,@'.$srcKey.$name.'))';
			} else
				$cond = '(isset(@'.$srcKey.$name.') && @'.$srcKey.$name.'==@key)';

			$content .= '<?php foreach('.$attr['group'].' as $key => $val) {?>'.
				$this->tmpl->build('<option value="{{@key}}"'.
						'{{'.$cond.'?'.
						'\' selected="selected"\':\'\'}}>{{@val}}</option>').
						'<?php } ?>';
			unset($attr['group']);
		}

		if (array_key_exists("name", $attr))
		{
			$setValue = $this->tmpl->build('{{@tmpValue=@'.$srcKey.$name.'}}');
			$unsetValue = $this->tmpl->build('{{@tmpValue=null}}');
		}

		// resolve all other / unhandled tag attributes
		if ($attr!=null)
			$attr = $this->resolveParams($attr);
		// create element and return		
		return '<select'.$attr.'>'.$setValue.$content.$unsetValue.'</select>';
	}


}


class Option extends TagHandler {

	function __construct() {
		/** @var \Base $f3 */
		$f3 = \Base::instance();
		if (!$f3->exists('template.form.srcKey'))
			$f3->set('template.form.srcKey','POST');
		parent::__construct();
	}

	/**
	 * build tag string
	 * @param $attr
	 * @param $content
	 * @return string
	 */
	function build($attr, $content) {
		$srcKey = \Base::instance()->get('template.form.srcKey');
		
		if (array_key_exists("value", $attr))
			$isSelected = $this->tmpl->build("{{(@tmpValue=='".$attr['value']."') ? 'selected=\"selected\"' : ''}}");
		
		// resolve all other / unhandled tag attributes
		if ($attr!=null)
			$attr = $this->resolveParams($attr);
		// create element and return
		return '<option'.$attr.' '.$isSelected.'>'.$content.'</option>';
	}


}