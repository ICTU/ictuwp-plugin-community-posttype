jQuery(document).ready(function ($) {
    $(".tab_content").hide();
    $(".tab_content:first").show();
    $("ul.tabs li").click(function () {
        $("ul.tabs li").removeClass("active");
        $(this).addClass("active");
        $(".tab_content").hide();
        var activeTab = $(this).attr("rel");
        $("#" + activeTab).show();
    });
});

function rssrtvr_lite_CheckAllLs(form) {
    for (i = 0, n = form.elements.length; i < n; i++) {
        if (form.elements[i].type === "checkbox" && !(form.elements[i].getAttribute('onclick', 2))) {
            if (form.elements[i].checked === true)
                form.elements[i].checked = false;
            else
                form.elements[i].checked = true;
        }
    }
}

function rssrtvr_lite_ChangeMode() {
    var mode = $('.rssrtvr-lite-pull-mode').val();
    if (mode === 'auto') {
        $('#auto').show();
        $('#cron').hide();
    } else {
        $('#auto').hide();
        $('#cron').show();
    }
}

function rssrtvr_lite_ChangeTranslator() {
    yandex_translate_settings.style.display = 'none';
    google_translate_settings.style.display = 'none';
    deepl_translate_settings.style.display = 'none';
    if (feed_settings.translator.value === 'yandex_translate') {
        yandex_translate_settings.style.display = 'inline';
    }
    if (feed_settings.translator.value === 'google_translate') {
        google_translate_settings.style.display = 'inline';
    }
    if (feed_settings.translator.value === 'deepl_translate') {
        deepl_translate_settings.style.display = 'inline';
    }
}

function rssrtvr_lite_ChangePostType() {
    var form = document.forms['feed_settings'];
    if (!form) return;

    var postType = form.post_type.value;
    var undef = document.getElementById("custom_taxonomy_undefined");
    if (undef) undef.style.display = "block";

    if (rssrtvr_lite_vars.post_type_map) {
        Object.values(rssrtvr_lite_vars.post_type_map).forEach(function (taxList) {
            taxList.forEach(function (tax) {
                var el = document.getElementById("custom_taxonomy_" + tax);
                if (el) el.style.display = "none";
            });
        });
    }

    if (rssrtvr_lite_vars.post_type_map[postType]) {
        rssrtvr_lite_vars.post_type_map[postType].forEach(function (tax) {
            var el = document.getElementById("custom_taxonomy_" + tax);
            if (el) el.style.display = "block";
        });
        if (undef) undef.style.display = "none";
    }
}

function rssrtvr_lite_ChangePreviewMode() {
    switch (preview_mode_switch.value) {
        case "post_view":
            post_view.style.display = "block";
            attachment_view.style.display = "none";
            xml_view.style.display = "none";
            break;
        case "attachment_view":
            post_view.style.display = "none";
            attachment_view.style.display = "block";
            xml_view.style.display = "none";
            break;
        case "xml_view":
            post_view.style.display = "none";
            attachment_view.style.display = "none";
            xml_view.style.display = "block";
            break;
    }
}

jQuery(document).ready(function ($) {
    $(document).on('click', '.rssrtvr-lite-copy', function (e) {
        e.preventDefault();
        var copyText = document.getElementById("rssrtvr-lite-log");
        if (copyText) {
            copyText.focus();
            copyText.select();
            copyText.setSelectionRange(0, copyText.value.length);
            document.execCommand("copy");
        }
    });

    function rssrtvr_lite_ChangePreviewMode() {
        var val = $('#preview_mode_switch').val();
        $('#post_view, #full_text_view, #xml_view, #attachment_view').hide();
        if (val === 'post_view') {
            $('#post_view').show();
        } else if (val === 'attachment_view') {
            $('#attachment_view').show();
        } else if (val === 'xml_view') {
            $('#xml_view').show();
        }
    }
    $(document).on('change', '#preview_mode_switch', rssrtvr_lite_ChangePreviewMode);
    if ($('#preview_mode_switch').length) {
        rssrtvr_lite_ChangePreviewMode();
    }

    function rssrtvr_toggleCompressionQuality() {
        if ($('#image_format').val() === 'keep') {
            $('#compression_quality, label[for="compression_quality"]').hide();
        } else {
            $('#compression_quality, label[for="compression_quality"]').show();
        }
    }
    $('#image_format').on('change', rssrtvr_toggleCompressionQuality);
    rssrtvr_toggleCompressionQuality();

    $('#store_images').on('change', function () {
        var imageFormatRow = $('#image_format_selector').closest('tr');
        if ($(this).is(':checked')) {
            imageFormatRow.show();
        } else {
            imageFormatRow.hide();
        }
    }).trigger('change');

    function initCodeMirror(id, mode, height) {
        var $el = $('#' + id);
        if (!$el.length || typeof wp.codeEditor === 'undefined') {
            return null;
        }
        var settings = _.clone(wp.codeEditor.defaultSettings || {});
        settings.codemirror = Object.assign({}, settings.codemirror, {
            mode: mode,
            lineNumbers: true,
            lineWrapping: true,
            matchBrackets: true,
            indentUnit: 8,
            indentWithTabs: true,
            gutters: ["CodeMirror-linenumbers"],
            autoRefresh: true
        });
        var editor = wp.codeEditor.initialize($el, settings);
        if (editor && editor.codemirror) {
            editor.codemirror.setSize("100%", height);
            var value = $el.val();
            if (value) {
                editor.codemirror.setValue(value);
            }
            return editor.codemirror;
        }
        return null;
    }
    initCodeMirror("post_content_template", "text/html", "20em");
    initCodeMirror("post_excerpt_template", "text/html", "20em");

    function rssrtvr_lite_ChangeMode(el) {
        var mode = $(el).val();
        $('#auto, #cron').hide();
        $('#' + mode).show();
    }
    $('.rssrtvr-lite-pull-mode').each(function () {
        rssrtvr_lite_ChangeMode(this);
    });
    $(document).on('change', '.rssrtvr-lite-pull-mode', function () {
        rssrtvr_lite_ChangeMode(this);
    });

    $(document).on('change', '#rssrtvr-lite-post-type', function () {
        if (typeof rssrtvr_lite_ChangePostType === 'function') {
            rssrtvr_lite_ChangePostType();
        }
    });
    if ($('#rssrtvr-lite-post-type').length && typeof rssrtvr_lite_ChangePostType === 'function') {
        rssrtvr_lite_ChangePostType();
    }

    $(document).on('change', '#rssrtvr-lite-translator', function () {
        if (typeof rssrtvr_lite_ChangeTranslator === 'function') {
            rssrtvr_lite_ChangeTranslator();
        }
    });
    if ($('#rssrtvr-lite-translator').length && typeof rssrtvr_lite_ChangeTranslator === 'function') {
        rssrtvr_lite_ChangeTranslator();
    }

});