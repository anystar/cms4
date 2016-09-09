$(function() {


	<exclude>
	var side_toolbar = $('<div>').attr('id', 'webworkscms_testtoolbar');
	$('body').append(side_toolbar);
	</exclude>

	var top_div = $('<div>').attr('id', 'webworkscms_admintoolbar');
	$('body').append(top_div);

	var r_div = $('<span>').attr('id', 'right_div');
	top_div.append(r_div);

	var save = $('<input class="textinput" id="savebutton" type="button" value="Save"/>');
	save.click(function () {

		Object.keys(CKEDITOR.instances).forEach(function(key,index) {
			
			var editor = CKEDITOR.instances[key];
			if (!editor.checkDirty()) return;

			var postData = {},
			    payload = '',
			    contentType = 'application/x-www-form-urlencoded; charset=UTF-8';

			postData.editabledata = editor.getData();
			postData.editorID = editor.container.getId();
			postData.page = editor.cms.page;
			postData.contentBlock = editor.cms.contentBlock;

			// Convert postData object to multi-part form data query string for post like jQuery does by default.
			var formData = '';
			for (var key in postData) { // Must encode data to handle special characters
					formData += '&' + key + '=' + encodeURIComponent(postData[key]);
			}
			payload = formData.slice(1); // Remove initial '&'

			// Use pure javascript (no dependencies) and send the data in json format
			var xhttp = new XMLHttpRequest();

			xhttp.onreadystatechange = function() {
			    if (xhttp.readyState == 1) { 
					$("#savebutton").val("saving...");
					
					setTimeout(
					  function() 
					  {
					    $("#savebutton").val("Saved!").css("background", "#3cab4e");

						setTimeout(
						  function() { $("#savebutton").val("Save"); }, 1500);
					  	}, 2000);
			    }
			};

			xhttp.open("POST", "{{@BASE}}/admin/content/save", true);
			xhttp.setRequestHeader("Content-type", 'application/x-www-form-urlencoded');
			xhttp.send(payload);
			editor.resetDirty()
		});

		return true;
	});

	var inputBox = $('<input id="addContentBoxInput" class="inputbox" placeholder="add content block" type="text">');
	inputBox.keypress(function(e) {
		if(e.which === 32) return false;
	});

	var add = $('<input id="addContentBoxBtn" class="textinput" type="button" value="+"/>');
	add.click(function (data) {
		var val = $("#addContentBoxInput").val();
		if (val != "") {
			$("#addContentBoxInput").prop('readonly', true);
			$.ajax({
				method: "POST",
				url: "{{@BASE}}/admin/page/add_content",
				data: { content_name: val, page:window.location.pathname }
			}).done(function () {
				$("#addContentBoxInput").val("");
				$("#addContentBoxInput").prop('readonly', false);
			});
		}
	});

	<check if="{{@webmaster}}">
	r_div.append(inputBox);
	r_div.append(add);
	</check>

	r_div.append(save);
	r_div.append($('<div class="spacer"></div>'));
	<check if="{{@webmaster}}">r_div.append($('<a href="{{@BASE}}/admindb" target="_cmswindow" type="button">DB</a>'));</check>
	r_div.append($('<a href="{{@BASE}}/admin" target="_cmswindow" type="button">CMS Panel</a>'));

	var toggleBtn = $('<input class="textinput" type="button" value="~"/>');
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
			r_div.show();
			init_inline_ckeditors();
			Object.keys(CKEDITOR.instances).forEach(function(key,index) {
				var editor = CKEDITOR.instances[key];
				editor.readOnly = false;
				$(editor.element.$).attr('contenteditable',"true");
			});
		}
	});

	top_div.append(toggleBtn);
});