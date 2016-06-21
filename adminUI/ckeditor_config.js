CKEDITOR.editorConfig = function( config ) {
  config.toolbarGroups = [
    { name: 'clipboard', groups: [ 'clipboard', 'undo' ] },
    { name: 'forms', groups: [ 'forms' ] },
    { name: 'links', groups: [ 'links' ] },
    { name: 'insert', groups: [ 'insert' ] },
    { name: 'paragraph', groups: [ 'list', 'indent', 'blocks', 'align', 'bidi', 'paragraph' ] },
    { name: 'editing', groups: [ 'find', 'selection', 'spellchecker', 'editing', 'others', 'tools' ] },
    '/',
    { name: 'basicstyles', groups: [ 'basicstyles', 'cleanup' ] },
    { name: 'styles', groups: [ 'styles' ] },
    { name: 'colors', groups: [ 'colors' ] },
    { name: 'document', groups: [ 'mode', 'document', 'doctools' ] },
  ];

  config.removeButtons = 'Source,Save,NewPage,Preview,Print,Templates,Cut,Copy,Paste,PasteText,PasteFromWord,Find,Replace,SelectAll,Form,Checkbox,Radio,TextField,Textarea,Select,Button,ImageButton,HiddenField,Strike,Subscript,Superscript,Outdent,Indent,Blockquote,CreateDiv,BidiLtr,BidiRtl,Language,Anchor,Flash,HorizontalRule,SpecialChar,Smiley,PageBreak,Iframe,Styles,Format,Maximize,ShowBlocks,About';

  config.inlinesave = {
    postUrl: '{{@BASE}}/admin/page/save',
    useJSON: false,
    useColorIcon: true
  };

  config.extraPlugins = 'uploadimage';
  config.removePlugins = 'scayt';
  config.uploadUrl = '{{@BASE}}/image_upload';

  config.filebrowserBrowseUrl = '{{@BASE}}/admin/browse_files';
  config.filebrowserUploadUrl = '{{@BASE}}/admin/file_upload';
};