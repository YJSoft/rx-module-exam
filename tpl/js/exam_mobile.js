(function($, window, document) {
    'use strict';

    function storageKey(examSrl, memberSrl) {
        return 'rx-exam-draft:' + examSrl + ':' + memberSrl;
    }

    function storageGet(key) {
        try {
            return window.localStorage.getItem(key);
        } catch (e) {
            return null;
        }
    }

    function storageSet(key, value) {
        try {
            window.localStorage.setItem(key, value);
            return true;
        } catch (e) {
            return false;
        }
    }

    function storageRemove(key) {
        try {
            window.localStorage.removeItem(key);
        } catch (e) {
            // Storage may be unavailable in private browsing modes.
        }
    }

    function isMobileDevice() {
        return window.matchMedia('(max-width: 767px), (pointer: coarse)').matches ||
            /Android|iPhone|iPad|iPod|Mobile/i.test(window.navigator.userAgent || '');
    }

    $(function() {
        var $context = $('[data-exam-srl]').first();
        var examSrl = String($context.attr('data-exam-srl') || '');
        var memberSrl = String($context.attr('data-member-srl') || '0');
        if (!examSrl) {
            return;
        }

        var key = storageKey(examSrl, memberSrl);
        if ($context.attr('data-exam-completed') === 'Y') {
            storageRemove(key);
        }

        var $form = $('#examJoin');
        if (!$form.length) {
            return;
        }

		var timed = $form.attr('data-timed') === 'Y';
		var allowResume = $form.attr('data-allow-resume') === 'Y';
		var canSaveDraft = !timed || allowResume;
		var $connection = $form.find('[data-exam-connection]');
		var $draftStatus = $form.find('[data-exam-draft-status]');
		var saveTimer = null;
		var wasOffline = !window.navigator.onLine;
		var submitting = false;
		var abandonmentSent = false;
		$form[0].addEventListener('formdata', function() {
			submitting = true;
		});

        function showConnection(online) {
            $connection
                .prop('hidden', false)
                .toggleClass('is-offline', !online)
                .toggleClass('is-online', online)
                .text(online ? $form.attr('data-online') : $form.attr('data-offline'));
            $form.find('[type=submit]').prop('disabled', !online);

            if (online) {
                window.setTimeout(function() {
                    if (window.navigator.onLine) {
                        $connection.prop('hidden', true);
                    }
                }, 3000);
            }
        }

        function collectAnswers() {
            var answers = {};
            $form.find('[data-question-srl]').each(function() {
                answers[String($(this).attr('data-question-srl'))] = $(this).val();
            });
            return answers;
        }

        function updateAnswerMarks($answer) {
            var selected = String($answer.val() || '').split(',').filter(Boolean);
            var $list = $answer.closest('.qanswer_list');
            if (!$list.length) {
                return;
            }

            $list.find('a.ans_check').each(function() {
                var values = String($(this).attr('data-value') || '').split(',');
                var active = selected.indexOf(values[1]) !== -1;
                $(this).attr('aria-pressed', active ? 'true' : 'false');
                $(this).closest('li')
                    .toggleClass('is-selected', active)
                    .find('.marking').toggleClass('show', active);
            });
        }

        function saveDraft() {
			if (!canSaveDraft) {
				return;
            }

            var draft = {
                version: 1,
                examSrl: examSrl,
                updatedAt: Date.now(),
                answers: collectAnswers()
            };
            if (storageSet(key, JSON.stringify(draft))) {
                $draftStatus.prop('hidden', false).text($form.attr('data-draft-saved'));
            }
        }

        function scheduleSave() {
			if (!canSaveDraft) {
				return;
            }
            window.clearTimeout(saveTimer);
            saveTimer = window.setTimeout(saveDraft, 250);
        }

        function restoreDraft() {
            var raw = storageGet(key);
            if (!raw) {
                return;
            }

            try {
                var draft = JSON.parse(raw);
                if (!draft || draft.version !== 1 || String(draft.examSrl) !== examSrl || !draft.answers) {
                    storageRemove(key);
                    return;
                }

                var restored = false;
                $form.find('[data-question-srl]').each(function() {
                    var questionSrl = String($(this).attr('data-question-srl'));
                    if (Object.prototype.hasOwnProperty.call(draft.answers, questionSrl)) {
                        $(this).val(draft.answers[questionSrl]);
                        updateAnswerMarks($(this));
                        restored = restored || draft.answers[questionSrl] !== '';
                    }
                });
                if (restored) {
                    $draftStatus.prop('hidden', false).text($form.attr('data-draft-restored'));
                }
            } catch (e) {
                storageRemove(key);
            }
        }

		if (timed && !allowResume) {
			storageRemove(key);
		} else {
			restoreDraft();
			$form.on('input change', '[data-question-srl]', scheduleSave);
            $form.on('click', 'a.ans_check', function() {
                window.setTimeout(scheduleSave, 0);
			});
		}
		if (timed && isMobileDevice()) {
			window.alert($form.attr('data-mobile-warning'));
		}

		function submitAbandonment() {
			if (allowResume || abandonmentSent || !window.navigator.sendBeacon || !window.FormData) {
				return;
			}
			var formData = new window.FormData($form[0]);
			formData.set('abandon_attempt', 'Y');
			formData.set('valid_check', 'N');
			abandonmentSent = window.navigator.sendBeacon($form[0].action, formData);
		}

		$(window).on('beforeunload', function(event) {
			if (submitting) {
				return;
			}
			saveDraft();
			var warning = $form.attr('data-leave-warning') || '';
			event.preventDefault();
			(event.originalEvent || event).returnValue = warning;
			return warning;
		});
		$(window).on('pagehide', function() {
			if (!submitting) {
				saveDraft();
				submitAbandonment();
			}
		});

		$form.on('submit', function(event) {
			if (!window.navigator.onLine) {
				event.preventDefault();
				showConnection(false);
			} else {
				saveDraft();
				// 다른 제출 검증 핸들러가 preventDefault()를 호출하는지 모두 확인한 뒤 표시한다.
				window.queueMicrotask(function() {
					if (!event.isDefaultPrevented()) {
						submitting = true;
					}
				});
			}
        });

        $(window).on('offline', function() {
            wasOffline = true;
            showConnection(false);
        });
        $(window).on('online', function() {
            showConnection(true);
            if (wasOffline && $form.find('[name=valid_check]').val() === 'N') {
                $form.trigger('submit');
            }
            wasOffline = false;
        });

        if (!window.navigator.onLine) {
            showConnection(false);
        }
    });
})(jQuery, window, document);
