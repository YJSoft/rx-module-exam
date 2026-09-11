jQuery(function($) {
    'use strict';

    // Select one or more answers and keep the hidden form value in sync.
    $('.qanswer_list a.ans_check').on('click', function(event) {
        event.preventDefault();
        if ($(this).closest('[data-practice-graded="Y"]').length) return;

        var values = $(this).attr('data-value').split(',');
        var $answer = $('#answer' + values[0]);
        var $option = $(this).closest('li');
        var $list = $(this).closest('.qanswer_list');

        if (!$answer.length) {
            return;
        }

        if (values[2] === 'Y') {
            var selected = $answer.val() ? $answer.val().split(',') : [];
            var index = selected.indexOf(values[1]);

            if (index === -1) {
                selected.push(values[1]);
                $option.addClass('is-selected');
                $(this).attr('aria-pressed', 'true');
            } else {
                selected.splice(index, 1);
                $option.removeClass('is-selected');
                $(this).attr('aria-pressed', 'false');
            }
            $answer.val(selected.join(','));
        } else {
            $answer.val(values[1]);
            $list.find('li').removeClass('is-selected');
            $list.find('a.ans_check').attr('aria-pressed', 'false');
            $option.addClass('is-selected');
            $(this).attr('aria-pressed', 'true');
        }
    });

    // Per-exam management menu. Kept independent of Bootstrap JS for host compatibility.
    $('a.exam_manager').on('click', function(event) {
        event.preventDefault();
        event.stopPropagation();

        var $group = $(this).closest('.exam_manager_group');
        var $menu = $group.find('.exam_manager_wrap').first();

        $('.exam_manager_group').not($group).removeClass('on').find('a.exam_manager').attr('aria-expanded', 'false');
        $group.toggleClass('on');
        $(this).attr('aria-expanded', $group.hasClass('on') ? 'true' : 'false');

        if ($group.hasClass('on')) {
            var rect = this.getBoundingClientRect();
            var menuWidth = $menu.outerWidth();
            var menuHeight = $menu.outerHeight();
            var viewportWidth = $(window).width();
            var viewportHeight = $(window).height();
            var gap = 6;
            var left = Math.min(rect.left, viewportWidth - menuWidth - 8);
            var top = rect.bottom + gap;

            if (top + menuHeight > viewportHeight - 8) {
                top = Math.max(8, rect.top - menuHeight - gap);
            }

            $menu.css({
                top: top,
                left: Math.max(8, left)
            });
        }
    });

    $(document).on('click', function() {
        $('.exam_manager_group').removeClass('on').find('a.exam_manager').attr('aria-expanded', 'false');
    });

    $('.exam_manager_wrap').on('click', function(event) {
        event.stopPropagation();
    });

    $(window).on('resize scroll', function() {
        $('.exam_manager_group').removeClass('on').find('a.exam_manager').attr('aria-expanded', 'false');
    });
});
