jQuery(function($){
    const wrap = $('.lad-rf-wrap');
    if (!wrap.length || typeof ladRfData === 'undefined') return;

    const reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const message = function(key, fallback){
        return ladRfData.messages && ladRfData.messages[key] ? ladRfData.messages[key] : fallback;
    };

    function setStepAccessibility() {
        wrap.find('.lad-rf-step').each(function(){
            const step = $(this);
            const active = step.hasClass('is-active');
            step.attr('aria-hidden', active ? 'false' : 'true');
            step.find('h2').attr('tabindex', '-1');
        });

        wrap.find('.lad-rf-status')
            .attr('role', 'status')
            .attr('aria-live', 'polite')
            .attr('aria-atomic', 'true');

        wrap.find('.lad-rf-stars')
            .attr('role', 'radiogroup')
            .attr('aria-label', message('ratingGroupLabel', 'Valora tu experiencia de 1 a 5 estrellas'));

        wrap.find('.lad-rf-star').each(function(index){
            $(this)
                .attr('role', 'radio')
                .attr('aria-checked', 'false')
                .attr('tabindex', index === 0 ? '0' : '-1');
        });

        wrap.find('.lad-rf-reason-btn')
            .attr('aria-pressed', 'false');

        const textarea = wrap.find('.lad-rf-feedback-form textarea[name="feedback_text"]');
        if (textarea.length) {
            const id = textarea.attr('id') || 'lad-rf-feedback-text';
            textarea.attr('id', id);
            if (!wrap.find('label[for="' + id + '"]').length) {
                $('<label/>', {
                    'class': 'lad-rf-field-label',
                    'for': id,
                    text: message('feedbackLabel', 'Cuéntanos tu experiencia')
                }).insertBefore(textarea);
            }
        }
    }

    function focusActiveStep(step) {
        const heading = step.find('h2').first();
        if (!heading.length) return;
        if (reducedMotion) {
            heading.trigger('focus');
            return;
        }
        window.requestAnimationFrame(function(){
            heading.trigger('focus');
        });
    }

    function showStep(name) {
        wrap.find('.lad-rf-step')
            .removeClass('is-active')
            .attr('aria-hidden', 'true');

        const next = wrap.find('.lad-rf-step-' + name).first();
        next.addClass('is-active').attr('aria-hidden', 'false');
        focusActiveStep(next);
    }

    function setStarsDisabled(disabled) {
        wrap.find('.lad-rf-star')
            .prop('disabled', disabled)
            .attr('aria-disabled', disabled ? 'true' : 'false');
        wrap.find('.lad-rf-stars').toggleClass('is-loading', disabled);
    }

    function selectRating(rating) {
        wrap.find('.lad-rf-star').each(function(){
            const value = parseInt($(this).data('rating'), 10);
            const selected = value === rating;
            $(this)
                .toggleClass('is-selected', value <= rating)
                .attr('aria-checked', selected ? 'true' : 'false')
                .attr('tabindex', selected ? '0' : '-1');
        });
    }

    function prepareLowRatingThanks() {
        const thanks = wrap.find('.lad-rf-step-thanks');
        if (!thanks.length || thanks.find('.lad-rf-thanks-actions').length) return;

        const actions = $('<div/>', {'class': 'lad-rf-thanks-actions'});
        const googleSource = wrap.find('.lad-rf-step-high .lad-rf-google-btn').first();

        $('<p/>', {
            'class': 'lad-rf-followup-copy',
            text: message('followupSaved', 'Hemos registrado tu comentario para que el equipo pueda revisarlo y hacer seguimiento.')
        }).appendTo(thanks);

        if (googleSource.length) {
            const googleLink = googleSource.clone(false)
                .removeClass('lad-rf-btn')
                .addClass('lad-rf-secondary-link lad-rf-thanks-google')
                .text(message('shareGoogleSecondary', 'Compartir también mi experiencia en Google'))
                .attr('aria-label', message('shareGoogleSecondaryAria', 'Compartir también mi experiencia en Google. Se abre en una pestaña nueva.'));
            actions.append(googleLink);
        }

        $('<button/>', {
            type: 'button',
            'class': 'lad-rf-reset-btn',
            text: message('finish', 'Finalizar')
        }).appendTo(actions);

        actions.appendTo(thanks);
    }

    setStepAccessibility();

    wrap.on('keydown', '.lad-rf-star', function(e){
        const keys = ['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End'];
        if (keys.indexOf(e.key) === -1) return;

        e.preventDefault();
        const stars = wrap.find('.lad-rf-star:not(:disabled)');
        let index = stars.index(this);

        if (e.key === 'Home') index = 0;
        else if (e.key === 'End') index = stars.length - 1;
        else if (e.key === 'ArrowRight' || e.key === 'ArrowUp') index = Math.min(index + 1, stars.length - 1);
        else index = Math.max(index - 1, 0);

        stars.eq(index).trigger('focus').trigger('click');
    });

    wrap.on('click', '.lad-rf-star', function(){
        if ($(this).prop('disabled')) return;

        const rating = parseInt($(this).data('rating'), 10);
        selectRating(rating);
        setStarsDisabled(true);

        wrap.find('.lad-rf-status').removeClass('is-error').text('');
        wrap.find('.lad-rf-step-rating .lad-rf-status').text(message('savingRating', 'Guardando tu valoración…'));

        $.ajax({
            url: ladRfData.ajaxUrl,
            method: 'POST',
            dataType: 'json',
            timeout: 15000,
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
                setStarsDisabled(false);
                return;
            }

            wrap.find('.lad-rf-feedback-form [name="entry_id"]').val(response.data.entry_id);
            wrap.find('.lad-rf-step-rating .lad-rf-status').text('');

            if (response.data.is_low) showStep('low');
            else showStep('high');
        }).fail(function(){
            wrap.find('.lad-rf-step-rating .lad-rf-status').text(ladRfData.messages.error).addClass('is-error');
            setStarsDisabled(false);
        });
    });

    wrap.on('click', '.lad-rf-reason-btn', function(){
        wrap.find('.lad-rf-reason-btn')
            .removeClass('is-selected')
            .attr('aria-pressed', 'false');

        $(this)
            .addClass('is-selected')
            .attr('aria-pressed', 'true');

        wrap.find('.lad-rf-feedback-form [name="feedback_reason"]').val($(this).data('reason'));
    });

    wrap.on('submit', '.lad-rf-feedback-form', function(e){
        e.preventDefault();

        const form = $(this);
        const entryId = form.find('[name="entry_id"]').val();
        const submitBtn = form.find('.lad-rf-submit-btn');
        const statusBox = form.find('.lad-rf-status');

        if (!entryId) {
            statusBox.text(message('missingEntry', 'Falta el identificador de la valoración.')).addClass('is-error');
            return;
        }

        submitBtn.prop('disabled', true).attr('aria-busy', 'true').text(ladRfData.messages.sending);
        statusBox.removeClass('is-error').text(message('sendingFeedback', 'Enviando tu comentario…'));

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
                submitBtn.prop('disabled', false).attr('aria-busy', 'false').text(ladRfData.messages.send);
                return;
            }

            prepareLowRatingThanks();
            showStep('thanks');
        }).fail(function(){
            statusBox.text(ladRfData.messages.error).addClass('is-error');
            submitBtn.prop('disabled', false).attr('aria-busy', 'false').text(ladRfData.messages.send);
        });
    });

    wrap.on('click', '.lad-rf-reset-btn', function(){
        window.location.reload();
    });

    wrap.on('click', '.lad-rf-google-btn, .lad-rf-thanks-google', function(){
        setTimeout(function(){
            window.location.reload();
        }, 700);
    });
});
