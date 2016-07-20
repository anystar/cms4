<script>
	CKEDITOR.disableAutoInline = true;

	<repeat group="{{@ck_instances}}" value="{{@instance}}">

		if (document.getElementById("{{@instance.id}}")) {
			<switch expr="{{@instance.type}}">
				<default>var editor = CKEDITOR.inline( '{{@instance.id}}',{ customConfig: '{{ @BASE }}/admin/ckeditor_config.js' } );</default>
			</switch>}
	</repeat>
</script>