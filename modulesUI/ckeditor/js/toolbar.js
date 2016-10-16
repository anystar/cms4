$(function() {

	var top_div = $('<div>').attr('id', 'webworkscms_admintoolbar');
	$('body').append(top_div);

	var r_div = $('<span>').attr('id', 'right_div');
	top_div.append(r_div);

	var save = $('<input id="savebutton" type="button" value="Save"/>');
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
                $("#savebutton").val("Saved!").css("background", "#3cab4e");

                    setTimeout(
                      function() { $("#savebutton").val("Save"); }, 1500);
                    }, 2000);

		});

		return true;
	});


	r_div.append(save);
	r_div.append($('<div class="spacer"></div>'));

	r_div.append($('<a href="{{@BASE}}/admin" target="_cmswindow" type="button">CMS Panel</a>'));

	var toggleBtn = $('<input type="button" value="~"/>');
	toggleBtn.click(function () {
		
		if (r_div.is(":visible"))
		{

			Object.keys(CKEDITOR.instances).forEach(function(key,index) {
				var editor = CKEDITOR.instances[key];
				editor.destroy();
				$(editor.container.$).attr('contenteditable',"false");
			});

			r_div.hide();
		} else
		{
			init_inline_ckeditors();
			Object.keys(CKEDITOR.instances).forEach(function(key,index) {
				var editor = CKEDITOR.instances[key];
				editor.readOnly = false;
				$(editor.element.$).attr('contenteditable',"true");
			});

			r_div.show();
		}
	});

	top_div.append(toggleBtn);
});