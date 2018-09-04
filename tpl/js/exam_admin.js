/**
 * @편집모드에서 사용하는 자바스크립트
 **/
jQuery(function($) {
    $('a.question_delete').click(function(event) {
        event.preventDefault();
        var val = $(this).attr('data-value').split('|');
        var document_srl = val[0];
        var question_srl = val[1];
        if(!document_srl || !question_srl) return;
        if(!confirm('이 문제를 삭제하시겠습니까?')) return;

        var params = new Array();
        params['mid'] = current_mid ;
        params['document_srl'] = document_srl;
        params['question_srl'] = question_srl;
        exec_xml('exam','procExamQuestionDelete',params, function(ret_obj) {
            alert(ret_obj['message']);
            location.reload();
        });
    });
    $(document).on('change', 'input[name=q_type]', function(e){
        var i =0;
        for(i=0;i<2;i++) {
            if(i==$(this).val()) $('#answer_type'+$(this).val()).show();
            else $('#answer_type'+i).hide();
        }
   });
	$(document).on('click', 'a.answer_marking', function(e){
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
