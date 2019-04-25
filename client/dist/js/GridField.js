(function($){

    $.entwine('ss', function($) {
        $('.ss-gridfield .field .gridfield-listedpages.gridfield-dropdown').entwine({

            onchange: function() {
                var gridField = this.getGridField();
                var state = gridField.getState().GridFieldListedPagesAddNewButton;

                state.pageType = this.val();
                gridField.setState("GridFieldListedPagesAddNewButton", state);
            }
        });
    });

}(jQuery));
