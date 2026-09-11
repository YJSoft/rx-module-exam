(function($, window) {
    'use strict';

    $(function() {
        var $form = $('#examJoin[data-practice="Y"]');
        if (!$form.length) {
            return;
        }

        var $questions = $form.find('[data-practice-question="Y"]');
        var $passages = $form.find('[data-practice-passage="Y"]');
        var $current = $form.find('[data-practice-current]');
        var $submitted = $form.find('[data-practice-submitted]');
        var $progress = $form.find('[data-practice-progress]');
        var $finish = $form.find('[data-practice-finish]');
        var activeIndex = 0;

        function syncAnswerMarks($question) {
            var $answer = $question.find('[data-question-srl]').first();
            var selected = String($answer.val() || '').split(',').filter(Boolean);
            $question.find('a.ans_check').each(function() {
                var values = String($(this).attr('data-value') || '').split(',');
                var isSelected = selected.indexOf(values[1]) !== -1;
                $(this).attr('aria-pressed', isSelected ? 'true' : 'false');
                $(this).closest('li')
                    .toggleClass('is-selected', isSelected)
                    .find('.marking').toggleClass('show', isSelected);
            });
        }

        function lockQuestion($question) {
            $question.attr('data-practice-graded', 'Y').addClass('is-practice-locked');
            $question.find('input[type="text"][data-question-srl]').prop('readonly', true);
            $question.find('a.ans_check').attr('aria-disabled', 'true');
            $question.find('[data-practice-submit]').prop('disabled', true);
        }

        function updateCompletion(submittedCount, completed) {
            submittedCount = parseInt(submittedCount, 10) || 0;
            $submitted.text(submittedCount);
            $progress.val(submittedCount);
            $finish.prop('disabled', completed !== 'Y');
        }

        function showQuestion(index) {
            if (!$questions.length) {
                return;
            }
            activeIndex = Math.max(0, Math.min(index, $questions.length - 1));
            var $question = $questions.eq(activeIndex);
            var number = parseInt($question.attr('data-practice-number'), 10) || (activeIndex + 1);

            $questions.removeClass('is-active').prop('hidden', true);
            $question.addClass('is-active').prop('hidden', false);
            $passages.each(function() {
                var start = parseInt($(this).attr('data-practice-start'), 10) || 0;
                var end = parseInt($(this).attr('data-practice-end'), 10) || 0;
                $(this).prop('hidden', number < start || number > end);
            });
            $current.text(number);
            $form.find('[data-practice-prev]').prop('disabled', activeIndex === 0);
            $form.find('[data-practice-next]').prop('disabled', activeIndex === $questions.length - 1);
        }

        $questions.each(function(index) {
            var $question = $(this);
            syncAnswerMarks($question);
            if ($question.attr('data-practice-graded') === 'Y') {
                lockQuestion($question);
            } else if (activeIndex === 0) {
                activeIndex = index;
            }
        });

        var firstUngraded = $questions.filter('[data-practice-graded="N"]').first();
        if (firstUngraded.length) {
            activeIndex = $questions.index(firstUngraded);
        }
        showQuestion(activeIndex);

        $form.on('click', '[data-practice-prev]', function() {
            showQuestion(activeIndex - 1);
        });
        $form.on('click', '[data-practice-next]', function() {
            showQuestion(activeIndex + 1);
        });

        $form.on('click', '[data-practice-submit]', function() {
            var $button = $(this);
            var $question = $button.closest('[data-practice-question="Y"]');
            if ($question.attr('data-practice-graded') === 'Y') {
                return;
            }

            var $answer = $question.find('[data-question-srl]').first();
            var answer = String($answer.val() || '');
            if (!$.trim(answer)) {
                window.alert($form.attr('data-msg-answer-required'));
                if ($answer.attr('type') === 'text') $answer.trigger('focus');
                return;
            }
            if (!window.navigator.onLine) {
                window.alert($form.attr('data-offline'));
                return;
            }

            $button.prop('disabled', true);
            exec_json('exam.procExamPracticeSubmit', {
				mid: $form.find('[name="mid"]').val() || window.current_mid || '',
                module_srl: $form.find('[name="module_srl"]').val(),
                document_srl: $form.find('[name="document_srl"]').val(),
                question_srl: $answer.attr('data-question-srl'),
                answer: answer
            }, function(response) {
                if (!response || Number(response.error || 0) !== 0) {
                    $button.prop('disabled', false);
                    return;
                }

                $answer.val(response.answer);
                syncAnswerMarks($question);
                lockQuestion($question);

                var $result = $question.find('[data-practice-result]');
                $result.removeClass('is-O is-P is-X')
                    .addClass('is-' + response.answer_result)
                    .prop('hidden', false);
                $result.find('[data-practice-result-label]').text(response.result_label || '');
                $result.find('[data-practice-correct]').html(response.correct_answer || '');
                $result.find('[data-practice-explanation]').html(response.explanation || '');
                $button.text(response.result_label || '').prop('disabled', true);
                updateCompletion(response.submitted_count, response.completed);
			}, function() {
				$button.prop('disabled', false);
            });
        });
    });
})(jQuery, window);
