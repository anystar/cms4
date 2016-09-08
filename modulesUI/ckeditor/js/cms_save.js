CKEDITOR.plugins.add( 'cmssave',
{
	init: function( editor )
	{
		var config = editor.config.cmssave,
		    iconName;

		editor.cms = {
			page : config.page,
			contentBlock : config.contentBlock
		};

		var postUrl = "{{@BASE}}/admin/content/save";

		if (typeof config == "undefined") { // Give useful error message if user doesn't define config.cmssave
			config = {}; // default to empty object
		}

		iconName = 'inlinesave-color.svg';

		editor.addCommand( 'cmssave',
		{
				exec : function( editor )
				{
					var postData = {},
					    payload = '',
					    contentType = 'application/x-www-form-urlencoded; charset=UTF-8';

					postData.editabledata = editor.getData();
					postData.editorID = editor.container.getId();
					postData.page = config.page;
					postData.contentBlock = config.contentBlock;

					// Convert postData object to multi-part form data query string for post like jQuery does by default.
					var formData = '';
					for (var key in postData) { // Must encode data to handle special characters
							formData += '&' + key + '=' + encodeURIComponent(postData[key]);
					}
					payload = formData.slice(1); // Remove initial '&'

					// Use pure javascript (no dependencies) and send the data in json format...
					var xhttp = new XMLHttpRequest();
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
