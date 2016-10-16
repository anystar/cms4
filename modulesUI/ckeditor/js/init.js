CKEDITOR.plugins.addExternal("cmssave", "{{@BASE}}/admin/ckeditor/cms_save.js");
CKEDITOR.plugins.addExternal("imagebrowser", "{{@BASE}}/admin/ckeditor/imagebrowser.js");

function getConfig (type) {
	var config = {
		filebrowserUploadUrl : '{{@BASE}}/admin/ckeditor/upload_image?upload_path='+upload_path,
		fontSize_sizes : '8/8px;10/10px;12/12px;14/14px;16/16px;18/18px;20/20px;22/22px;24/24px;26/26px;28/28px;30/30px;32/32px;34/34px;36/36px;38/38px;40/40px;42/42px;44/44px;46/46px;48/48px;50/50px;52/52px;54/54px;56/56px;58/58px;60/60px;62/62px;64/64px;68/68px;70/70px;',
		extraPlugins : "imagebrowser,cmssave",
		removeButtons : 'Inlinesave,Save,NewPage,Preview,Print,Templates,Cut,Copy,Paste,PasteText,PasteFromWord,Find,Replace,SelectAll,Form,Checkbox,Radio,TextField,Textarea,Select,Button,ImageButton,HiddenField,Strike,Subscript,Superscript,Outdent,Indent,Blockquote,CreateDiv,BidiLtr,BidiRtl,Language,Anchor,Flash,HorizontalRule,SpecialChar,Smiley,PageBreak,Iframe,Styles,Format,Maximize,ShowBlocks,About',
		removePlugins : 'ckeditor-gwf-plugin,inlinesave',
		skin : ckeditor_skin,
		title : '',
		enterMode : CKEDITOR.ENTER_BR,
		allowedContent : true,
		imageBrowser_listUrl : '{{@SCHEME.'://'.@HOST.@BASE}}/admin/ckeditor/imagebrowser?path='+upload_path,
		toolbarGroups : 
		[
			{ name: 'clipboard', groups: [ 'clipboard', 'undo' ] },
			{ name: 'forms', groups: [ 'forms' ] },
			{ name: 'links', groups: [ 'links' ] },
			{ name: 'insert', groups: [ 'insert' ] },
			{ name: 'paragraph', groups: [ 'list', 'indent', 'blocks', 'align', 'bidi', 'paragraph' ] },
			{ name: 'editing', groups: [ 'find', 'selection', 'spellchecker', 'editing', 'others', 'tools' ] },
			'/',
			{ name: 'basicstyles', groups: [ 'basicstyles', 'cleanup' ] },
			{ name: 'styles', groups: [ 'styles' ] },
			{ name: 'colors', groups: [ 'colors' ] },
			{ name: 'document', groups: [ 'mode', 'document', 'doctools' ] },
		]
	};

	if (type == "image")
	{
		config.toolbarGroups = [
			{ name: 'insert', groups: [ 'insert' ] },
			{ name: 'document', groups: [ 'mode', 'document', 'doctools' ] },
		];
		config.removeButtons += ",Table";
	}

	if (type == "header")
	{
		config.toolbarGroups = [
			{ name: 'basicstyles', groups: [ 'basicstyles', 'cleanup' ] },
			{ name: 'paragraph', groups: [ 'indent', 'blocks', 'align', 'bidi', 'paragraph' ] },
			{ name: 'editing', groups: [ 'find', 'selection', 'spellchecker', 'editing', 'others', 'tools' ] },
			'/',
			{ name: 'styles', groups: [ 'styles' ] },
			{ name: 'colors', groups: [ 'colors' ] },
			{ name: 'document', groups: [ 'mode', 'document', 'doctools' ] },
		];
	}

	return config;
};

function init_inline_ckeditors() {
	var editors = document.getElementsByClassName("ckeditor");

	for (var i = editors.length - 1; i >= 0; i--)
	{
		var editorID = editors[i].getAttribute("id");
		var type = editors[i].getAttribute("type");
		var editor = CKEDITOR.inline(editorID, getConfig(type));
	}
}

init_inline_ckeditors();