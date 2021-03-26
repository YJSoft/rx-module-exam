jQuery(function($) {
    // 객관식 문제에서 답 클릭시 입력과 함께 표시
    $('.qanswer_list a.ans_check').click(function(event) {
        event.preventDefault();
        var chk_val = $(this).attr('data-value').split(',');

        var $answer = $('#answer'+chk_val[0]);
        if($answer.length < 1) return;
        if(chk_val[2]=='Y') {
            var _ans_list = ($answer.val())? $answer.val().split(',') : new Array();
            var idx = _ans_list.indexOf(chk_val[1]);
            if(idx==-1) {
                _ans_list.push(chk_val[1]);
                $answer.parent().find('li').eq(chk_val[1]-1).find('.marking').addClass('show');
            } else {
                _ans_list.splice(idx,1);
                $answer.parent().find('li').eq(chk_val[1]-1).find('.marking').removeClass('show');
            }
            $answer.val(_ans_list.join(','));
        } else {
            $answer.val(chk_val[1]);
            $answer.parent().find('.marking').removeClass('show');
            $answer.parent().find('li').eq(chk_val[1]-1).find('.marking').addClass('show');
        }
    });

	// 문제 순서 변경 이벤트
    $(document).on('click', 'a.question_swap', function(event) {
        event.preventDefault();
        var selected_srl = $(this).data('ssrl');
        var targeted_srl = $(this).data('tsrl');
        if ( !selected_srl || !targeted_srl ) return;
        if ( !confirm('문제의 순서를 변경하시겠습니까?') ) return;

		var params = {
			selected_srl : selected_srl,
			targeted_srl : targeted_srl
		};
		exec_json('exam.procExamQuestionSwap', params, function(ret_obj) {
			location.hash = params.targeted_srl;
			location.reload();
		});
    });
	// 문제 순서 변경 후 블링크
	$(window).on('load', function() {
		if ( location.href.indexOf('#') === -1 ) return false;

		var offset = $(location.hash).offset().top;
		$('html, body').animate({scrollTop : offset}, 0);

		var target = $(location.hash).children('.qtitle').children('.txt');
		target.css({
			backgroundColor : 'lightblue',
			'transition' : 'all 1.5s ease'
		}).delay(800).queue(function() {
			target.css({
				backgroundColor : ''
			});
			target.dequeue();
		}).delay(1500).queue(function() {
			target.css({
				'transition' : ''
			});
			target.dequeue();
		}); 
	});

	var question_list = [];
	// 문제 불러오기 이벤트
    $(document).on('click', 'a.btn_load', function(event) {
        event.preventDefault();
		var container = $(this).parent().next('.question_load_area');
		container.fadeIn();
		if ( !container.is(':empty') ) return false;

        var module_srl = $(this).data('msrl');
		var params = {
			module_srl : module_srl,
			status : 'Y'
		};
		exec_json('exam.getQuestions', params, function(ret_obj) {
			question_list = ret_obj.question_list;
			container.html('<div class="question_load"><h3>문제 불러오기<i class="xi-close"></i></h3>' +
				'<ul class="load_selection"></ul>' +
				'<div class="btn_search">' +
					'<a href="javascript:void(0);" class="exam_btn btn_close" type="button">취소</a>' +
					'<a href="javascript:void(0);" class="exam_btn btn_submit" type="submit">덮어쓰기</a>' +
				'</div>'+
			'</div>');
			$.each(question_list, function(i, v) {
				$('.load_selection').append('<li class="load_target" id="question_'+ i +'" title="'+ v.content +'">' +
					'<h4>' + v.document_title + '</h4>' +
					'<p>' +
						'<strong>' + v.title + '</strong>' +
						'<small>' + v.regdate.substr(0, 8) + '</small>' +
					'</p>' +
				'</li>');
				if ( v.category_title ) $('#question_' + i).children('h4').append('<span> (' + v.category_title +')</span>');
			});
		});
    });
	// 불러온 문제 선택
	$(document).on('click', '.load_target', function() {
		$(this).parent().children('.load_target').not(this).removeClass('selected');
		$(this).addClass('selected');
	});
	// 선택된 문제 덮어쓰기
	$(document).on('click', '.question_load_area .exam_btn.btn_submit', function(event) {
		event.preventDefault();
		if ( !$('.load_target.selected').length ) alert('문제를 선택해주세요');

		var idx = $('.load_target.selected').attr('id').replace('question_', '');
		var source = question_list[idx];

		// 난이도
		$('#q_level' + source.question_level).prop('checked', true);
		// 문제유형
		$('#q_type' + source.question_type).prop('checked', true);
		// 문제
		$('#q_title').val(source.title);
		// 지문 사용?
		if ( source.use_description === 'Y' ) $('#use_description').prop('checked', true);
		// 지문 머리글
		$('#q_description_title').val(source.description_title);
		// 지문 내용 -> CK에디터
		if ( $('.q_editor').find('.cke').length )
		{
			$('.q_editor').find('iframe').contents().find('.xe_content').html(source.description);
		}
		// 지문 내용 -> 프로알라
		else if ( $('.q_editor').children('.fr-box').length )
		{
			$('.fr-placeholder').hide();
			$('.fr-element').html(source.description);
		}
		// 지문 내용 -> textarea
		else
		{
			$('input[name="q_description_content"]').val(source.description);
			$('.q_editor').find('textarea').val(source.description);
		}
		// 정답 : 객관식
		if ( !source.question_type )
		{
			$('#answer_type0').show();
			$('#answer_type1').hide();
			$('input[name="q_answer1"]').val(source.answer1);
			$('input[name="q_answer2"]').val(source.answer2);
			$('input[name="q_answer3"]').val(source.answer3);
			$('input[name="q_answer4"]').val(source.answer4);
			$('input[name="q_answer5"]').val(source.answer5);
			$('.answer_marking').removeClass('on');
			var answers = source.answer.split(',')
			$.each(answers, function(i, v) {
				$('.answer_marking').eq(Number($.trim(v)) - 1).addClass('on');
			});
		}
		// 정답 : 주관식
		else
		{
			$('#answer_type0').hide();
			$('#answer_type1').show();
			$('input[name="q_answer6"]').val(source.answer);
		}
		$('input[name="q_answer"]').val(source.answer);
		// 복수답안 처리
		$('select[name="answer_check_type"]').val(source.answer_check_type);
		// 해설
		$('#q_content').val(source.content);
		// 배점
		$('#q_point').val(source.point);

		// 모달 윈도우 닫기
		$('.question_load_area').hide();
	});
	// 문제 불러오기 모달 창 닫기
	$(document).on('click', '.question_load_area, .question_load .xi-close, .question_load .btn_close', function(event) {
		$('.question_load_area').hide();
		return false;
	});
	// 문제 불러오기 모달 창 닫기 - 예외
	$(document).on('click', '.question_load', function(event) {
		return false;
	});

	// 시험지별 관리 팝업창 띄우기
    $(document).on('click', 'a.exam_manager', function(event) {
        $('.exam_manager_group').not($(this).parent()).removeClass('on');
        $(this).parent().toggleClass('on');
		var trigger_width = $(this).outerWidth();
		var target_width = $(this).next().outerWidth();
		var right_gap = $(window).width() - $(this).offset().left - trigger_width;
		var right_cond = ( target_width > trigger_width + right_gap ) ? target_width - trigger_width - right_gap : 0;
		$(this).next().css({
			top : $(this).offset().top + $(this).outerHeight(),
			left : $(this).offset().left - right_cond
		});
		return false;
    });
	$(document).on('click', function(event) {
		if ( $(event.target).hasClass('exam_manager') || $(event.target).hasClass('exam_manager_wrap') ) return false;
		$('.exam_manager_group').removeClass('on');
	});

	// 검색 모달창 띄우기
    $(document).on('click', 'a.btn_search', function(event) {
        event.preventDefault();
		var target = $(this).next();
		if ( target.is(':visible') ) target.hide();
        else target.fadeIn('fast');
    });
	// 검색 모달창 닫기
	$(document).on('click', '.exam_search_area, .exam_search .xi-close, .exam_search .btn_close', function(event) {
		$('.exam_search_area').hide();
		return false;
	});
	// 검색 모달창 닫기 - 예외
	$(document).on('click', '.exam_search', function(event) {
		return false;
	});
	// 검색 실행
	$(document).on('click', '.exam_search .btn_submit', function(event) {
		$('#exam_search').submit();
		return false;
	});

	// 시간 입력시 분단위까지만
	$(document).on('input', 'input[name="start_date"], input[name="end_date"]', function(event) {
		if ( this.value.length > this.maxLength ) this.value = this.value.slice(0, this.maxLength);
	});

	// 유뷰트 아이프레임 삽입시 반응형 만들기
	if ( $('iframe[src*="youtube.com"]').length > 0 )
	{
		var $youtube = $('iframe[src*="youtube.com"]');
		var max_width = $youtube.attr('width');
		var $container = $youtube.parent();
		$youtube.css({
			'position' : 'absolute',
			'top' : 0,
			'left' : 0,
			'width' : '100%',
			'height' : '100%'
		});
		$container.css({
			'position' : 'relative',
			'width' : '100%',
			'height' : 'auto',
			'padding-bottom' : '56.25%'
		}).wrap('<div style="margin: 0 auto; max-width: '+ max_width +'px;" />');
	}
});

// 이전 페이지가 있으면 뒤로 가기, 없으면 창 닫기
function cancel()
{
	var hasHistory = false;

	window.history.back();
	jQuery(window).on('beforeunload', function(event) {
		hasHistory = true;
	});

	setTimeout(function() {
		if ( !hasHistory ) window.close();
	}, 100);
}
