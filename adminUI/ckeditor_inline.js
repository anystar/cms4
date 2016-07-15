<script>
	CKEDITOR.disableAutoInline = true;

	<repeat group="{{@ck_instances}}" value="{{@instance}}">

		if (document.getElementById("{{@instance.id}}")) {

			<switch expr="{{@instance.type}}">
				<case value="img">
				var editor = CKEDITOR.inline( '{{@instance.id}}', { customConfig: '{{ @BASE }}/admin/ckeditor_imgs_config.js' } );
				editor.on( 'blur', function( e ) {
					if (confirm("*** Save these changes? ***")) {
						var editor = e.editor;
						var postData = {};

						postData.editabledata = editor.getData();
						postData.editorID = editor.container.getId();

						payload = JSON.stringify(postData);

						
						var formData = '';
						
						for (var key in postData) { // Must encode data to handle special characters
								formData += '&' + key + '=' + encodeURIComponent(postData[key]);
						}

						payload = formData.slice(1); // Remove initial '&'
						
						// Use pure javascript (no dependencies) and send the data in json format...
						var xhttp = new XMLHttpRequest();

						xhttp.open("POST", "{{@BASE}}/admin/page/save", true);
						// Send as form data encoded to handle special characters.
						xhttp.setRequestHeader("Content-type", 'application/x-www-form-urlencoded');
						xhttp.send(payload);
					}
				} );
				</case>

				<case value="header">
				var editor = CKEDITOR.inline( '{{@instance.id}}', { customConfig: '{{ @BASE }}/admin/ckeditor_header_config.js' } );
				editor.on( 'blur', function( e ) {
					if (confirm("*** Save these changes? ***")) {
						var editor = e.editor;
						var postData = {};

						postData.editabledata = editor.getData();
						postData.editorID = editor.container.getId();

						payload = JSON.stringify(postData);

						
						var formData = '';
						
						for (var key in postData) { // Must encode data to handle special characters
								formData += '&' + key + '=' + encodeURIComponent(postData[key]);
						}

						payload = formData.slice(1); // Remove initial '&'
						
						// Use pure javascript (no dependencies) and send the data in json format...
						var xhttp = new XMLHttpRequest();

						xhttp.open("POST", "{{@BASE}}/admin/page/save", true);
						// Send as form data encoded to handle special characters.
						xhttp.setRequestHeader("Content-type", 'application/x-www-form-urlencoded');
						xhttp.send(payload);
					}
				} );
				</case>

				<default>
				var editor = CKEDITOR.inline( '{{@instance.id}}', { customConfig: '{{ @BASE }}/admin/ckeditor_config.js' } );

				editor.on( 'blur', function( e ) {
					if (confirm("*** Save these changes? ***")) {
						var editor = e.editor;
						var postData = {};

						postData.editabledata = editor.getData();
						postData.editorID = editor.container.getId();

						payload = JSON.stringify(postData);

						
						var formData = '';
						
						for (var key in postData) { // Must encode data to handle special characters
								formData += '&' + key + '=' + encodeURIComponent(postData[key]);
						}

						payload = formData.slice(1); // Remove initial '&'
						
						// Use pure javascript (no dependencies) and send the data in json format...
						var xhttp = new XMLHttpRequest();

						xhttp.open("POST", "{{@BASE}}/admin/page/save", true);
						// Send as form data encoded to handle special characters.
						xhttp.setRequestHeader("Content-type", 'application/x-www-form-urlencoded');
						xhttp.send(payload);
					}
				} );
				</default>
			</switch>
		}
	</repeat>
</script>