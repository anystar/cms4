CKEDITOR.editorConfig = function( config ) {
  // Define changes to default configuration here.
  // For complete reference see:
  // http://docs.ckeditor.com/#!/api/CKEDITOR.config

  // The toolbar groups arrangement, optimized for two toolbar rows.
  config.toolbarGroups = [
    { name: 'clipboard',   groups: [ 'clipboard', 'undo' ] },
    { name: 'editing',     groups: [ 'find', 'selection', 'spellchecker' ] },
    { name: 'links' },
    { name: 'insert' },
    { name: 'forms' },
    { name: 'tools' },
    { name: 'document',    groups: [ 'mode', 'document', 'doctools' ] },
    { name: 'others' },
    '/',
    { name: 'basicstyles', groups: [ 'basicstyles', 'cleanup' ] },
    { name: 'paragraph',   groups: [ 'list', 'indent', 'blocks', 'align', 'bidi' ] },
    { name: 'styles' },
    { name: 'colors' },
    { name: 'about' }
  ];

  config.inlinesave = {
    postUrl: '{{@BASE}}/admin/page/save',
    useJSON: false,
    useColorIcon: true
  };

  // Remove some buttons provided by the standard plugins, which are
  // not needed in the Standard(s) toolbar.
  config.removeButtons = 'Underline,Subscript,Superscript';

  // Set the most common block elements.
  config.format_tags = 'p;h1;h2;h3;pre';

  // Simplify the dialog windows.
  config.removeDialogTabs = 'image:advanced;link:advanced';
};


// The "instanceCreated" event is fired for every editor instance created.
CKEDITOR.on( 'instanceCreated', function ( event ) {

  var editor = event.editor,
      element = editor.element;
      
  // Customize editors for headers and tag list.
  // These editors do not need features like smileys, templates, iframes etc.
  if ( element.is( 'h1', 'h2', 'h3' ) || element.getAttribute( 'id' ) == 'taglist' ) {
    // Customize the editor configuration on "configLoaded" event,
    // which is fired after the configuration file loading and
    // execution. This makes it possible to change the
    // configuration before the editor initialization takes place.
    editor.on( 'configLoaded', function () {

      // Remove redundant plugins to make the editor simpler.
      editor.config.removePlugins = 'colorbutton,find,flash,font,' +
          'forms,iframe,image,newpage,removeformat,' +
          'smiley,specialchar,stylescombo,templates';

      // Rearrange the toolbar layout.
      editor.config.toolbarGroups = [
        { name: 'editing', groups: [ 'basicstyles', 'links' ] },
        { name: 'undo' },
        { name: 'clipboard', groups: [ 'selection', 'clipboard' ] },
        { name: 'about' }
      ];
    } );
  }

} );