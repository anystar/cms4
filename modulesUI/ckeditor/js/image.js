
CKEDITOR.plugins.addExternal("cmssave", "{{@BASE}}/ckeditor/cms_save.js");

CKEDITOR.editorConfig = function( config ) {
  config.toolbarGroups = [
    { name: 'styles', groups: [ 'styles' ] },
    { name: 'insert', groups: [ 'insert' ] },
    { name: 'paragraph', groups: [ 'align' ] },
    { name: 'document', groups: [ 'mode', 'document', 'doctools' ] },
  ];

  config.removeButtons = 'GoogleWebFonts,font,Justify,Table,Inlinesave,Save,NewPage,Preview,Print,Templates,Cut,Copy,Paste,PasteText,PasteFromWord,Find,Replace,SelectAll,Form,Checkbox,Radio,TextField,Textarea,Select,Button,ImageButton,HiddenField,Strike,Subscript,Superscript,Outdent,Indent,Blockquote,CreateDiv,BidiLtr,BidiRtl,Language,Anchor,Flash,HorizontalRule,SpecialChar,Smiley,PageBreak,Iframe,Styles,Format,Maximize,ShowBlocks,About';

  config.extraPlugins = "cmssave";
  config.removePlugins = 'font,ckeditor-gwf-plugin,scayt,inlinesave,Font,GoogleWebFonts';
  config.uploadUrl = '{{@BASE}}/admin/file_manager/image_upload';

  config.filebrowserBrowseUrl = '{{@BASE}}/admin/file_manager/browse_files';
  config.filebrowserUploadUrl = '{{@BASE}}/admin/file_manager/image_upload_via_dialog';

  config.readOnly = false;
  config.skin = '{{@SETTINGS.ckeditor_skin}}';
  config.enterMode = CKEDITOR.ENTER_BR;
};