CKEDITOR.editorConfig = function( config ) {

  config.toolbar = [ [ 'Image', 'Inlinesave' ] ];

  config.inlinesave = {
    postUrl: '{{@BASE}}/admin/page/save',
    useJSON: false,
    useColorIcon: true
  };

  config.removePlugins = 'scayt,uploadimage';
  config.uploadUrl = '{{@BASE}}/admin/image_upload';

  config.filebrowserBrowseUrl = '{{@BASE}}/admin/browse_files';
  config.filebrowserUploadUrl = '{{@BASE}}/admin/file_upload';
};