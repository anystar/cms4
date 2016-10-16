CKEDITOR.plugins.add( 'cmssave',
{
	init: function( editor )
	{
		editor.cms = {
			path : editor.element.getAttribute("path"),
			id : editor.element.getAttribute("id"),
			hash : editor.element.getAttribute("hash")
		};

		var postUrl = "{{@BASE}}/admin/ckeditor/save";

		iconName = 'inlinesave-color.svg';

		editor.addCommand( 'cmssave',
		{
				exec : function( editor )
				{
					var postData = {},
					    payload = '',
					    contentType = 'application/x-www-form-urlencoded; charset=UTF-8';

					postData.contents = editor.getData();
					postData.id = editor.container.getId();
					postData.path = editor.element.getAttribute("path");
					postData.file = editor.element.getAttribute("file");
					postData.hash = editor.element.getAttribute("hash");

					// Convert postData object to multi-part form data query string for post like jQuery does by default.
					var formData = '';
					for (var key in postData) { // Must encode data to handle special characters
							formData += '&' + key + '=' + encodeURIComponent(postData[key]);
					}
					payload = formData.slice(1); // Remove initial '&'

					// Use pure javascript (no dependencies) and send the data in json format...
					var xhttp = new XMLHttpRequest();
					xhttp.onreadystatechange = function () {
						if (this.readyState == 4 && this.status == 200) {
							console.log(this.responseText);
							editor.element.setAttribute("hash", this.responseText);
						}
					};

					xhttp.open("POST", postUrl, true);
					// Send as form data encoded to handle special characters.
					xhttp.setRequestHeader("Content-type", contentType);
					xhttp.send(payload);
				}
		});
		
		editor.ui.addButton( 'cmssave',
		{
			toolbar: 'document',
			label: 'Save',
			command: 'cmssave',
			icon: this.path + 'images/' + iconName
		} );
	}
} );
