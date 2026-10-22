jQuery(function($){
    const wrap = $('.lad-rf-wrap');
    if (!wrap.length || typeof ladRfData === 'undefined') return;

    function showStep(name) {
        wrap.find('.lad-rf-step').removeClass('is-active');
        wrap.find('.lad-rf-step-' + name).addClass('is-active');
    }

    wrap.on('click', '.lad-rf-star', function(){
        const rating = parseInt($(this).data('rating'), 10);

        wrap.find('.lad-rf-star').removeClass('is-selected').each(function(){
            if (parseInt($(this).data('rating'), 10) <= rating) {
                $(this).addClass('is-selected');
            }
        });

        wrap.find('.lad-rf-status').removeClass('is-error').text('');

        $.ajax({
            url: ladRfData.ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: {
                action: ladRfData.ratingAction || 'rw_save_rating',
                nonce: ladRfData.nonce,
                rating: rating,
                page_url: window.location.href,
                ref: ladRfData.context.ref || '',
                customer_id: ladRfData.context.customer_id || '',
                service_type: ladRfData.context.service_type || '',
                order_id: ladRfData.context.order_id || '',
                tech_name: ladRfData.context.tech_name || '',
                source: ladRfData.context.source || 'WhatsApp',
                location_slug: ladRfData.context.location_slug || '',
                location_name: ladRfData.context.location_name || ''
            }
        }).done(function(response){
            if (!response || !response.success) {
                const msg = response && response.data && response.data.message ? response.data.message : ladRfData.messages.error;
                wrap.find('.lad-rf-step-rating .lad-rf-status').text(msg).addClass('is-error');
                return;
            }

            wrap.find('.lad-rf-feedback-form [name="entry_id"]').val(response.data.entry_id);

            if (response.data.is_low) showStep('low');
            else showStep('high');
        }).fail(function(){
            wrap.find('.lad-rf-step-rating .lad-rf-status').text(ladRfData.messages.error).addClass('is-error');
        });
    });

    wrap.on('click', '.lad-rf-reason-btn', function(){
        wrap.find('.lad-rf-reason-btn').removeClass('is-selected');
        $(this).addClass('is-selected');
        wrap.find('.lad-rf-feedback-form [name="feedback_reason"]').val($(this).data('reason'));
    });

    wrap.on('submit', '.lad-rf-feedback-form', function(e){
        e.preventDefault();

        const form = $(this);
        const entryId = form.find('[name="entry_id"]').val();
        const submitBtn = form.find('.lad-rf-submit-btn');
        const statusBox = form.find('.lad-rf-status');

        if (!entryId) {
            statusBox.text('Falta el identificador de la valoración.').addClass('is-error');
            return;
        }

        submitBtn.prop('disabled', true).text(ladRfData.messages.sending);
        statusBox.removeClass('is-error').text('');

        $.ajax({
            url: ladRfData.ajaxUrl,
            method: 'POST',
            dataType: 'json',
            timeout: 15000,
            data: {
                action: ladRfData.feedbackAction || 'rw_save_feedback',
                nonce: ladRfData.nonce,
                entry_id: entryId,
                feedback_reason: form.find('[name="feedback_reason"]').val(),
                feedback_text: form.find('[name="feedback_text"]').val()
            }
        }).done(function(response){
            if (!response || !response.success) {
                const msg = response && response.data && response.data.message ? response.data.message : ladRfData.messages.error;
                statusBox.text(msg).addClass('is-error');
                submitBtn.prop('disabled', false).text(ladRfData.messages.send);
                return;
            }

            showStep('thanks');

            // Cierra el ciclo de feedback bajo:
            // muestra agradecimiento y vuelve al inicio listo para otra valoración.
            setTimeout(function(){
                window.location.reload();
            }, 1500);
        }).fail(function(){
            statusBox.text(ladRfData.messages.error).addClass('is-error');
            submitBtn.prop('disabled', false).text(ladRfData.messages.send);
        });
    });

    wrap.on('click', '.lad-rf-google-btn', function(){
        // Google se abre en una nueva pestaña mediante target="_blank".
        // La página de Reseñas WordPress se reinicia para dejar el flujo listo
        // para una nueva valoración.
        setTimeout(function(){
            window.location.reload();
        }, 700);
    });
});
