$(function() {

	var main_div = $('<div>').attr('id', 'webworkscms_admintoolbar');
	$('body').append(main_div);


	var configmenu = $('<ul>').attr('id', 'webworkscms_configmenu');

	var menuitem = $('<li><a target="_blank" href="{{ @BASE }}/admin/">Dashboard</a></li>');
	configmenu.append(menuitem);

	/*<repeat group="{{@installed_modules}}" value="{{@module}}">*/
		var menuitem = $('<li><a target="_blank" href="{{ @BASE }}/admin/{{@module.namespace}}">{{@module.name}}</a></li>');
		configmenu.append(menuitem);
	/*</repeat>*/
	
	$('body').append(configmenu);

	var left_div = $('<div>').attr('id', 'left_div');
	main_div.append(left_div);

	var center_div = $('<div>').attr('id', 'center_div');
	main_div.append(center_div);

	var right_div = $('<div>').attr('id', 'right_div');
	main_div.append(right_div);

	var save = $('<input id="savebutton" type="button" value="Save Content"/>');
	save.click(function () {

		Object.keys(CKEDITOR.instances).forEach(function(key,index) {

			var editor = CKEDITOR.instances[key];
			if (!editor.checkDirty()) return;

			editor.execCommand("cmssave");
			editor.resetDirty();
            $("#savebutton").val("saving...");

            setTimeout(
              function()
              {
                $("#savebutton").val("Saved!").css("background", "#F4F4F4");

                    setTimeout(
                      function() { $("#savebutton").val("Save Content"); }, 1500);
                    }, 2000);

		});

		return true;
	});


	center_div.append(save);

	var help_div = $('<div>').attr('id', 'help_div');
	help_div.append("If you have any trouble do not hesitate to call us on 5446 3371");

	// var toggleBtn = $('<input type="button" value="Editors off"/>');

	// toggleBtn.click(function () {

	// 	// Turn editors off
	// 	if ($(this).val() == "Editors off")
	// 	{
	// 		Object.keys(CKEDITOR.instances).forEach(function(key,index) {
	// 			var editor = CKEDITOR.instances[key];
	// 			editor.destroy();
	// 			$(editor.container.$).attr('contenteditable',"false");
	// 		});
			
	// 		$(this).val("Editors on");
	// 	} else {
	// 		init_inline_ckeditors();
	// 		Object.keys(CKEDITOR.instances).forEach(function(key,index) {
	// 			var editor = CKEDITOR.instances[key];
	// 			editor.readOnly = false;
	// 			$(editor.element.$).attr('contenteditable',"true");
	// 		});

	// 		$(this).val("Editors off");
	// 	}
	// });

	// main_div.append(toggleBtn);

	left_div.append($('<img src="{{ @SETTINGS.cdn.cms }}/admin/imgs/logo_toolbar.png" height="100%">'));

	var configbutton = $('<button>Admin Menu</button>');
	configbutton.click(function () {
		$('#webworkscms_configmenu').toggle();
	});

	right_div.append(configbutton);


});