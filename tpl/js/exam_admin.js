/**
 * @편집모드에서 사용하는 자바스크립트
 **/
jQuery(function($) {
	$('form[data-invalidation-warning]').on('submit', function(event) {
		var $form = $(this);
		if($form.data('invalidation-confirmed')) return;
		if(!confirm($form.attr('data-invalidation-warning'))) {
			event.preventDefault();
			return false;
		}
		$form.data('invalidation-confirmed', true);
	});

    $('a.question_delete').click(function(event) {
        event.preventDefault();
        var val = $(this).attr('data-value').split('|');
        var document_srl = val[0];
        var question_srl = val[1];
        if(!document_srl || !question_srl) return;
		var message = '이 문제를 삭제하시겠습니까?';
		var invalidationWarning = $('#questions').attr('data-invalidation-warning');
		if(invalidationWarning) message += '\n\n' + invalidationWarning;
		if(!confirm(message)) return;

        var params = new Array();
        params['mid'] = current_mid ;
        params['document_srl'] = document_srl;
        params['question_srl'] = question_srl;
        exec_xml('exam','procExamQuestionDelete',params, function(ret_obj) {
            alert(ret_obj['message']);
            location.reload();
        });
    });
	function setQuestionType(type) {
		type = parseInt(type, 10) || 0;
		$('.question-only').toggle(type !== 2);
		$('.passage-only').toggle(type === 2);
		$('input[name=passage_start], input[name=passage_end]').prop('disabled', type !== 2);
		if(type === 2) {
			$('#use_description').prop('checked', true);
			$('#description_box').show();
		}
		$('#answer_type0').toggle(type === 0);
		$('#answer_type1').toggle(type === 1);
		var answerCount = ($('#q_answer').val() || '').split(',').filter(Boolean).length;
		$('#answer_check_type').toggle(type === 0 && answerCount > 1);
	}
	$(document).on('change', 'input[name=q_type]', function() {
		setQuestionType($(this).val());
	});
	setQuestionType($('input[name=q_type]:checked').val());
	$(document).on('click', 'a.answer_marking', function(e){
        e.preventDefault();
        var ans_list = ($('#q_answer').val())? $('#q_answer').val().split(',') : new Array();
        var idx = ans_list.indexOf($(this).attr('data-value'));

        if(idx==-1) {
            ans_list.push($(this).attr('data-value'));
            $(this).addClass('on');
        } else {
            ans_list.splice(idx,1);
            $(this).removeClass('on');
        }
        // 답이 2개이상일경우 옵션 보여줌
        if(ans_list.length>1) {
            $("#answer_check_type").show();
        } else {
            $("#answer_check_type").hide();
        }
        $('#q_answer').val(ans_list.join(','));
   });
});
