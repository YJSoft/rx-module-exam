/* search */
function completeSearch(ret_obj, response_tags, params, fo_obj)
{
	fo_obj.submit();
}
/**
 * @brief 팝업으로 띄우기
 **/
function examopen(url, target) {
	if(typeof(target) == "undefined") target = "_blank";
	if(typeof(xeVid)!='undefined' && xeVid != null && url.indexOf(request_uri)>-1 && !url.getQuery('vid')) url = url.setQuery('vid',xeVid);
	winopen(url, target, "width=910,height=700,scrollbars=yes,resizable=yes,toolbars=no");
}
/**
 * @brief 문제 유효성 검사
 **/
jQuery(function($) {
    var $pageTypes = $('input[name="page_type"]');
    if ($pageTypes.length) {
        var wasPractice = null;
        var savedTimeEnabled = false;
        var savedExamTime = '0';
        var syncExamTypeOptions = function() {
            var isPractice = $pageTypes.filter(':checked').val() === '1';
            var isQuestionBank = $pageTypes.filter(':checked').val() === '2';
            var $isTime = $('#is_time');
            var $examTime = $('#exam_time');
            if (isPractice && wasPractice !== true) {
                savedTimeEnabled = $isTime.prop('checked');
                savedExamTime = $examTime.val();
                $isTime.prop('checked', false);
                $examTime.val('0');
                $('#exam_time_txt').text('');
            } else if (!isPractice && wasPractice === true) {
                $isTime.prop('checked', savedTimeEnabled);
                $examTime.val(savedExamTime);
            }
            $isTime.prop('disabled', isPractice);
            $examTime.prop('disabled', isPractice);
            $('.question-bank-only').toggle(isQuestionBank).find(':input').prop('disabled', !isQuestionBank);
            $('.non-question-bank-only').toggle(!isQuestionBank);
            $('#random_question_order').prop('disabled', isQuestionBank);
            wasPractice = isPractice;
        };
        $pageTypes.on('change', syncExamTypeOptions);
        syncExamTypeOptions();
    }

    $('#examJoin').submit(function(event) {
        if ($(this).attr('data-practice') === 'Y') {
            if ($(this).find('[data-practice-question="Y"][data-practice-graded!="Y"]').length) {
                event.preventDefault();
                alert($(this).attr('data-msg-practice-incomplete'));
                return false;
            }
            return true;
        }
        var valid_check = ($("input[name=valid_check]").val()=='N')? 'N' : 'Y';
        var total_cnt = $('#total_q_count').text();
        if(total_cnt)
        {
            for(var i=1;i<=total_cnt;i++)
            {
                var obj = $('#answer'+i);
                if(!obj.length) continue;
                if(valid_check=='N')
                {
                    if(!obj.val()) $('#answer'+i).val('　');
                } else {
                    if(obj.attr('type')=="text")
                    {
                        if(!obj.val())
                        {
                            alert(i+msg_not_answer_text);
                            obj.focus();
                            return false;
                        }
                    } else if(obj.attr('type')=="hidden")
                    {
                        if(!obj.val())
                        {
                            alert(i+msg_not_answer_radio);
                            return false;
                        }
                    }
                }
            }
        }
        return true;
    });
});
