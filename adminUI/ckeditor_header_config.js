CKEDITOR.editorConfig = function( config ) {
	config.removePlugins = 'scayt,uploadimage';
  	config.toolbar = [ [ 'TextColor', 'Inlinesave' ] ];
	config.inlinesave = {
		postUrl: '{{@BASE}}/admin/page/save',
		useJSON: false,
		useColorIcon: true
	};
};