<script>
	CKEDITOR.disableAutoInline = true;

	<repeat group="{{@ck_instances}}" value="{{@instance}}">


		if (document.getElementById("{{@instance.id}}")) {
			
			<switch expr="{{@instance.type}}">
				<case value="img">
				CKEDITOR.inline( '{{@instance.id}}', { customConfig: '{{ @BASE }}/admin/ckeditor_imgs_config.js' } );
				</case>

				<case value="header">
				CKEDITOR.inline( '{{@instance.id}}', { customConfig: '{{ @BASE }}/admin/ckeditor_header_config.js' } );
				</case>

				<default>
				CKEDITOR.inline( '{{@instance.id}}', { customConfig: '{{ @BASE }}/admin/ckeditor_config.js' } );
				</default>
			</switch>
		}
	</repeat>
</script>