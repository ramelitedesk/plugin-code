jQuery(document).ready(function($) {
    $('#import-products-btn').on('click', function(e) {
        e.preventDefault();
        $('#import-loader').show();
        $('#import-result').html('');

        $.post(wcApiImporter.ajax_url, {
            action: 'wc_api_import_products',
            security: wcApiImporter.nonce
        }, function(response) {
            $('#import-loader').hide();
            $('#import-result').html(response.data);
        }).fail(function() {
            $('#import-loader').hide();
            $('#import-result').html('<p style="color:red;">Failed to import products.</p>');
        });
    });
});
